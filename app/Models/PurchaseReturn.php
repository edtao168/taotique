<?php

// 檔案路徑：app/Models/PurchaseReturn.php

namespace App\Models;

use App\Traits\HasAccounting;
use App\Traits\HasAccountAndDynamicSearch;
use App\Traits\HasShop;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PurchaseReturn extends Model
{
    use HasShop, HasAccounting, HasAccountAndDynamicSearch;

    protected $table = 'purchase_returns';

    protected $fillable = [
        'shop_id',
        'purchase_id',
        'warehouse_id',
        'return_no',
        'items_total_amount',
        'fees_total_amount',
        'total_return_amount',
        'exchange_rate',
        'status',
        'reason',
        'created_by',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'shop_id'             => 'integer',
        'purchase_id'         => 'integer',
        'warehouse_id'        => 'integer',
        'items_total_amount'  => 'decimal:4',
        'fees_total_amount'   => 'decimal:4',
        'total_return_amount' => 'decimal:4',
        'exchange_rate'       => 'decimal:6',
        'status'              => 'string',
        'reason'              => 'string',
        'created_by'          => 'integer',
        'approved_by'         => 'integer',
        'approved_at'         => 'datetime',
    ];

    private const DECIMAL_PRECISION = 4;

    // =========================================================================
    // SECTION: HasAccounting Trait 所需方法
    // =========================================================================

    public static function getDocumentNumberField(): string
    {
        return 'return_no';
    }

    public function getDocumentNumber(): string
    {
        return $this->return_no;
    }

    public static function getReferenceType(): string
    {
        return 'purchase_return';
    }

    // =========================================================================
    // SECTION: HasAccountAndDynamicSearch Trait 所需方法
    // =========================================================================

    /**
     * 解析動態會計科目
     */
    public function resolveDynamicAccount(string $dynamicSpec, ?array $context = null): string
    {
        $parts = explode(':', $dynamicSpec);
        $prefix = $parts[0] ?? '';

        return match ($prefix) {
            'auto'           => $this->resolveAutoDynamicAccount($parts[1] ?? null),
            'purchase_return'=> $this->resolvePurchaseReturnDynamicAccount($parts[1] ?? null),
            default          => $this->resolveDefaultAccount($dynamicSpec),
        };
    }

    /**
     * 解析金額來源
     */
    public function getAmountFromSource(string $source, mixed $context = null): string
    {
        return match ($source) {
            'total_return_amount' => (string) ($this->total_return_amount ?? '0.0000'),
            'return_cost_base'    => $this->getTotalCostBase(),
            default               => $this->getAttribute($source) ?? '0.0000',
        };
    }

    // =========================================================================
    // SECTION: 內部科目解析方法
    // =========================================================================

    private function resolveAutoDynamicAccount(?string $subType): string
    {
        return match ($subType) {
            'inventory' => config('business.accounting_accounts.cost.inventory', '1405'),
            'cost'      => config('business.accounting_accounts.cost.cost_of_goods_sold', '5401'),
            default     => '1405',
        };
    }

    private function resolvePurchaseReturnDynamicAccount(?string $subType): string
    {
        return match ($subType) {
            'refund' => $this->getRefundAccount(),
            default  => '2202',
        };
    }

    private function resolveDefaultAccount(string $dynamicSpec): string
    {
        \Illuminate\Support\Facades\Log::warning("未預期的動態科目規格: {$dynamicSpec}", [
            'model' => get_class($this),
            'id'    => $this->id,
        ]);

        return '2202';
    }

    /**
     * 取得退款科目（依據原採購的付款方式）
     */
    protected function getRefundAccount(): string
    {
        $paymentMethod = $this->purchase?->payment_method ?? 'china_ap';

        return match ($paymentMethod) {
            'cash_twd', 'cash'    => config('business.accounting_accounts.assets.cash', '100101'),
            'bank_cathay'         => config('business.accounting_accounts.assets.bank_twd', '100201'),
            'wechat_pay'          => '100202',
            'alipay'              => '100202',
            'china_ap'            => '220201',
            default               => '2202',
        };
    }

    // =========================================================================
    // SECTION: 金額計算方法
    // =========================================================================

    /**
     * 取得退貨成本（本幣）
     */
    public function getTotalCostBase(): string
    {
        $total = '0.0000';
        $rate = (string) ($this->exchange_rate ?? '1.0000');

        $this->loadMissing(['items']);

        foreach ($this->items as $item) {
            $cost = bcmul($item->unit_price ?? '0.0000', (string) $item->quantity, self::DECIMAL_PRECISION);
            $total = bcadd($total, $cost, self::DECIMAL_PRECISION);
        }

        return bcmul($total, $rate, self::DECIMAL_PRECISION);
    }

    /**
     * 重新計算總額
     */
    public function updateTotals(): void
    {
        $itemsSum = (string) ($this->items()->sum('subtotal') ?: '0.0000');

        $this->items_total_amount = $itemsSum;
        $this->total_return_amount = bcsub($itemsSum, $this->fees_total_amount ?? '0.0000', self::DECIMAL_PRECISION);

        $this->saveQuietly();
    }

    // =========================================================================
    // SECTION: 業務方法
    // =========================================================================

    /**
     * 產生採購退貨單號
     */
    public static function generatePurchaseReturnNumber(): string
    {
        $prefix = Setting::get('pr_prefix', 'PR-');
        $date = now()->format('Ymd');

        $lastOrder = self::where('return_no', 'like', $prefix . $date . '%')
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastOrder ? (int) substr($lastOrder->return_no, -4) + 1 : 1;
        $digits = Setting::get('number_digits', 4);

        return $prefix . $date . str_pad($sequence, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * 檢查是否可以審核
     */
    public function canBeApproved(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * 審核通過
     */
    public function approve(): void
    {
        if (!$this->canBeApproved()) {
            throw new \Exception("單據狀態為 {$this->status}，無法審核");
        }

        $this->status = 'approved';
        $this->approved_by = auth()->id();
        $this->approved_at = now();
        $this->save();
    }

    /**
     * 檢查是否可以過帳
     */
    public function canBePosted(): bool
    {
        return $this->status === 'approved'
            && !empty($this->approved_by)
            && $this->approved_at !== null
            && $this->status !== 'completed';
    }

    /**
     * 過帳（庫存異動 + 會計分錄）
     */
    public function post(): void
    {
        DB::transaction(function () {
            if (!$this->canBePosted()) {
                throw new \Exception("單據 #{$this->return_no} 目前狀態為 {$this->status}，不符合過帳條件。");
            }

            $this->load(['items.product', 'purchase']);

            // 1. 庫存異動（退貨出庫）
            foreach ($this->items as $item) {
				
				 $productLabel = ($item->product->sku ?: '') . ' - ' . ($item->product->name ?: '未知商品');
				 
                $inventory = Inventory::where([
                    'shop_id'      => $this->shop_id,
                    'warehouse_id' => $this->warehouse_id,
                    'product_id'   => $item->product_id,
                ])->lockForUpdate()->first();

                if (!$inventory) {
					 $productName = $item->product->name ?? '未知商品';
					$productSku = $item->product->sku ?? '';
                    throw new \Exception("商品 {$productLabel} 庫存記錄不存在，無法執行退貨出庫。");
                }

                $newQty = bcsub((string) $inventory->quantity, (string) $item->quantity, self::DECIMAL_PRECISION);

                if (bccomp($newQty, '0', self::DECIMAL_PRECISION) < 0) {
                    throw new \Exception("商品 {$productLabel} 庫存不足。當前庫存：{$inventory->quantity}，退貨數量：{$item->quantity}");;
                }

                $inventory->quantity = $newQty;
                $inventory->save();

                // 庫存流水
                DB::table('inventory_movements')->insert([
                    'shop_id'       => $this->shop_id,
                    'warehouse_id'  => $this->warehouse_id,
                    'product_id'    => $item->product_id,
                    'quantity'      => - (float) $item->quantity,
                    'cost_snapshot' => $item->unit_price ?? '0.0000',
                    'type'          => 'PURCHASE_RETURN',
                    'reference'     => $this->return_no,
                    'remark'        => '採購退貨出庫',
                    'user_id'       => auth()->id(),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            // 2. 會計過帳（兩筆傳票）
            $this->postJournal('purchase_return_refund');
            $this->postJournal('purchase_return_cost');

            // 3. 更新狀態
            $this->status = 'completed';
            $this->save();

            logger("採購退貨單過帳完成", [
                'purchase_return_id'  => $this->id,
                'return_no'           => $this->return_no,
                'total_return_amount' => $this->total_return_amount,
                'total_cost_base'     => $this->getTotalCostBase(),
            ]);
        });
    }
	
	/**
	 * 使用 BCMath 計算總額
	 */
	public function refreshTotal()
	{
		$itemsSum = $this->items()->sum('subtotal');
		$feesSum = $this->fees_total_amount ?? '0.0000';
		
		// 採購退回邏輯：退回金額 = 商品總價 - 費用
		$this->total_return_amount = bcsub($itemsSum, $feesSum, 4);
		$this->save();
	}

    // =========================================================================
    // SECTION: Accessors
    // =========================================================================

    public function getSubtotalAfterDiscountAttribute(): string
    {
        return (string) ($this->items_total_amount ?? '0.0000');
    }

    // =========================================================================
    // SECTION: 關聯關係
    // =========================================================================

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class, 'purchase_return_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // =========================================================================
    // SECTION: Boot 事件
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->return_no)) {
                $model->return_no = $model->generatePurchaseReturnNumber();
            }
        });

        static::deleting(function ($purchaseReturn) {
            if ($purchaseReturn->status === 'completed') {
                throw new \Exception('已結案的退貨單無法刪除');
            }
        });
    }
}