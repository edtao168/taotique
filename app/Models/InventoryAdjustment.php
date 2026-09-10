<?php

// [路徑]: app/Models/InventoryAdjustment.php

namespace App\Models;

use App\Enums\AmountSource;
use App\Services\AccountingService;
use App\Traits\HasAccounting;
use App\Traits\HasShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryAdjustment extends Model
{
    use HasShop, HasAccounting;

    private const DECIMAL_PRECISION = 4;

    protected $fillable = [
        'shop_id',
        'warehouse_id',
        'user_id',
        'adjustment_no',
        'type',
        'status',
        'remark',
        'adjusted_at',
    ];

    // =========================================================================
    // HasAccounting 實作
    // =========================================================================

    public static function getDocumentNumberField(): string { return 'adjustment_no'; }
    public function getDocumentNumber(): string { return $this->adjustment_no; }
    public static function getReferenceType(): string { return 'inventory_adjustment'; }

    /**
     * 🎯 依規則線上的 amount_source 決定要回傳「虧損總額」還是「盈餘總額」
     *
     * $source 的值來自 accounting_rule_lines.amount_source：
     *   - 'inventory_adjustment_loss' → 回傳負數項目的 total_amount 合計
     *   - 'inventory_adjustment_gain' → 回傳正數項目的 total_amount 合計
     *   - 'inventory_adjustment_amount' → 回傳全部（向後兼容）
     */
    public function getAmountFromSource(string $source, mixed $context = null): string
    {
        $this->loadMissing('items');

        return match ($source) {
            AmountSource::INVENTORY_ADJUSTMENT_LOSS->value => $this->sumAmountBySign(-1),
            AmountSource::INVENTORY_ADJUSTMENT_GAIN->value => $this->sumAmountBySign(1),
            AmountSource::INVENTORY_ADJUSTMENT_AMOUNT->value => bcadd(
                $this->sumAmountBySign(-1),
                $this->sumAmountBySign(1),
                self::DECIMAL_PRECISION
            ),
            default => '0.0000',
        };
    }

    /**
     * 依 quantity 的正負號累加 total_amount
     *
     * @param int $sign -1 = 只加負數項, 1 = 只加正數項
     */
    private function sumAmountBySign(int $sign): string
    {
        $total = '0.0000';

        foreach ($this->items as $item) {
            $qty = (string) $item->quantity;

            $isMatch = $sign < 0
                ? bccomp($qty, '0.0000', self::DECIMAL_PRECISION) < 0
                : bccomp($qty, '0.0000', self::DECIMAL_PRECISION) > 0;

            if ($isMatch) {
                $total = bcadd($total, (string) $item->total_amount, self::DECIMAL_PRECISION);
            }
        }

        return $total;
    }

    public function resolveDynamicAccount(string $dynamicSpec, ?array $context = null): string
    {
        // 目前庫存調整的存貨科目固定用 config 預設值
        // 若未來要依商品類別細分，可在這裡讀 $this->items->first()->product 的分類
        return config('business.accounting_accounts.cost.inventory', '1405');
    }

    // =========================================================================
    // 核心領域邏輯
    // =========================================================================

    /**
	 * 產生 adjustment_no：IA-YYYYMMDD + 4 碼當日流水號
	 * 例：IA-202609100001
	 *
	 * 使用 lockForUpdate 鎖定當日最大單號，避免併發撞號。
	 * 注意：若當日尚無任何單，lockForUpdate 鎖不到列，
	 *       仍可能有極小機率撞號，此時靠 unique 索引 + 重試機制處理。
	 */
	public static function generateAdjustmentNo(): string
	{
		$date   = now()->format('Ymd');
		$prefix = 'IA-' . $date;

		// 取當日最大單號（含 lockForUpdate 避免同時寫入）
		$lastNo = static::query()
			->where('adjustment_no', 'like', $prefix . '%')
			->orderByDesc('adjustment_no')
			->lockForUpdate()
			->value('adjustment_no');

		$nextSeq = $lastNo
			? ((int) substr($lastNo, -4)) + 1
			: 1;

		return $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
	}

	public static function createWithAccounting(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $shopId      = $data['shop_id'] ?? auth()->user()->shop_id ?? 1;
            $warehouseId = $data['warehouse_id'];
            $type        = $data['type'] ?? 'miscellaneous_out';
            $remark      = $data['remark'] ?? '庫存調整';
            $itemsData   = $data['items'] ?? [];

            if (empty($itemsData)) {
                throw new \InvalidArgumentException('調整單據必須包含至少一項商品');
            }

            // A. 建立庫存調整單主表
            $adjustment = static::create([
                'shop_id'       => $shopId,
                'warehouse_id'  => $warehouseId,
                'user_id'       => auth()->id(),
                'adjustment_no' => static::generateAdjustmentNo(),
                'type'          => $type,
                'status'        => 'completed',
                'remark'        => $remark,
                'adjusted_at'   => now(),
            ]);

            $allowNegative = (bool) Setting::get('allow_negative_inventory', false);

            foreach ($itemsData as $item) {
                $productId = $item['product_id'];
                $adjustQty = (string) $item['quantity'];

                if (bccomp($adjustQty, '0.0000', self::DECIMAL_PRECISION) === 0) {
                    continue;
                }

                $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();
                $inventory = Inventory::where('shop_id', $shopId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->first();

                $beforeQty = $inventory ? (string) $inventory->quantity : '0.0000';
                $afterQty  = bcadd($beforeQty, $adjustQty, self::DECIMAL_PRECISION);

                if (!$allowNegative && bccomp($afterQty, '0.0000', self::DECIMAL_PRECISION) < 0) {
                    throw new \RuntimeException("調整阻斷：商品 [{$product->name}] 調整後庫存不能為負數！當前: {$beforeQty}，欲調整: {$adjustQty}");
                }

                $unitCost    = (string) ($product->cost ?? '0.0000');
                $absQty      = (string) abs((float) $adjustQty);
                $totalAmount = bcmul($absQty, $unitCost, self::DECIMAL_PRECISION);

                // B. 建立調整單明細
                InventoryAdjustmentItem::create([
                    'inventory_adjustment_id' => $adjustment->id,
                    'product_id'              => $productId,
                    'quantity'                => $adjustQty,
                    'unit_cost'               => $unitCost,
                    'total_amount'            => $totalAmount,
                ]);

                // C. 調整實體庫存
                if ($inventory) {
                    $inventory->quantity = $afterQty;
                    $inventory->save();
                } else {
                    Inventory::create([
                        'shop_id'      => $shopId,
                        'warehouse_id' => $warehouseId,
                        'product_id'   => $productId,
                        'quantity'     => $afterQty,
                        'cost'         => $unitCost,
                    ]);
                }

                // D. 寫入庫存流水紀錄
                InventoryMovement::create([
                    'shop_id'      => $shopId,
                    'warehouse_id' => $warehouseId,
                    'product_id'   => $productId,
                    'quantity'     => (float) $adjustQty,
                    'type'         => $type,
                    'reference'    => $adjustment->adjustment_no,
                    'remark'       => $remark,
                    'user_id'      => auth()->id(),
                ]);
            }

            // E. 生成日記帳傳票
            $adjustment->postAccountingJournal();

            Log::info("庫存調整單建立暨會計過帳成功 [單號: {$adjustment->adjustment_no}]");

            return $adjustment;
        }, 3);
    }

    /**
     * 🎯 不傳第三個參數（$context = null）
     * 金額由 getAmountFromSource 依 amount_source 自行計算
     */
    public function postAccountingJournal(): void
    {
        $this->loadMissing('items');
        $accountingService = app(AccountingService::class);

        $lossTotal = $this->sumAmountBySign(-1);
        $gainTotal = $this->sumAmountBySign(1);

        if (bccomp($lossTotal, '0.0000', self::DECIMAL_PRECISION) > 0) {
            $journal = $accountingService->postFromRules('inventory_adjustment_loss', $this);
            $this->ensureJournalValid($journal, 'inventory_adjustment_loss');
        }

        if (bccomp($gainTotal, '0.0000', self::DECIMAL_PRECISION) > 0) {
            $journal = $accountingService->postFromRules('inventory_adjustment_gain', $this);
            $this->ensureJournalValid($journal, 'inventory_adjustment_gain');
        }
    }

    private function ensureJournalValid($journal, string $eventType): void
    {
        if (!$journal || !$journal->exists) {
            throw new \RuntimeException("過帳失敗：無法生成傳票 [{$eventType}]");
        }
        if ($journal->items()->count() === 0) {
            throw new \RuntimeException("過帳失敗：傳票無分錄明細 [{$eventType}]");
        }
        $debitTotal  = (string) $journal->items()->sum('debit');
        $creditTotal = (string) $journal->items()->sum('credit');

        if (bccomp($debitTotal, $creditTotal, self::DECIMAL_PRECISION) !== 0) {
            throw new \RuntimeException("過帳失敗：傳票借貸不平衡 (借: {$debitTotal}, 貸: {$creditTotal})");
        }
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentItem::class);
    }
}