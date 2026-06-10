<?php

namespace App\Models;

use App\Traits\HasAccountAndDynamicSearch;
use App\Traits\HasAccounting;
use App\Traits\HasShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesReturn extends Model
{
    use HasShop, HasAccounting, HasAccountAndDynamicSearch;

    protected $table = 'sales_returns';

    protected $fillable = [
        'shop_id',
        'warehouse_id',
        'sale_id',
        'return_no',
        'items_total_amount',
        'fees_total_amount',
        'total_refund_amount',
        'exchange_rate',
        'status',
        'return_reason',
        'created_by',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [
        'shop_id'             => 'integer',
        'warehouse_id'        => 'integer',
        'sale_id'             => 'integer',
        'items_total_amount'  => 'decimal:4',
        'fees_total_amount'   => 'decimal:4',
        'total_refund_amount' => 'decimal:4',
        'exchange_rate'       => 'decimal:6',
        'status'              => 'string',
        'return_reason'       => 'string',
        'created_by'          => 'integer',
        'approved_by'         => 'integer',
        'approved_at'         => 'datetime',
    ];

    private const DECIMAL_PRECISION = 4;

    // =========================================================================
    // SECTION: Trait 所需實作方法
    // =========================================================================

    /**
     * 🎯 實作 HasAccountAndDynamicSearch Trait 的抽象方法
     * 解析動態會計科目
     * 
     * @param string $dynamicSpec 動態規格（例如 'sales_return:refund' 或 'auto:inventory'）
     * @param array|null $context 上下文
     * @return string 實際會計科目代碼
     */
    public function resolveDynamicAccount(string $dynamicSpec, ?array $context = null): string
    {
        $parts = explode(':', $dynamicSpec);
        $prefix = $parts[0] ?? '';
        
        // 🎯 核心修正：處理各種前綴
        return match($prefix) {
            'sales_return' => $this->resolveSalesReturnDynamicAccount($parts[1] ?? null),
            'sale'         => $this->resolveSaleDynamicAccount($parts[1] ?? null, $parts[2] ?? null),
            'auto'         => $this->resolveAutoDynamicAccount($parts[1] ?? null),
            default        => $this->resolveDefaultAccount($dynamicSpec),
        };
    }

    /**
     * 🎯 實作 HasAccountAndDynamicSearch Trait 的抽象方法
     * 獲取指定金額來源的數值
     */
    public function getAmountFromSource(string $source, mixed $context = null): string
    {
        return match($source) {
            // 退貨商品總額（未扣除費用前）
            'subtotal_after_discount', 'items_total_amount' 
                => (string)($this->items_total_amount ?? '0.0000'),
            
            // 最終應退淨額
            'total_refund_amount' 
                => (string)($this->total_refund_amount ?? '0.0000'),
            
			// 退貨退款總額（原幣，已經過費用加減）
			'return_total' => (string)($this->total_refund_amount ?? '0.0000'),
			
			// 退貨成本（原幣）- 用於原幣計價的採購退貨
			'return_cost' => $this->getTotalCostOriginal(),
			
			// 🎯 退貨成本（本幣）- 用於多幣別換算後的會計分錄
			'return_cost_base' => $this->getTotalCostBase(),
            
            // 總成本（用於成本沖減分錄）
            'total_cost_twd', 'return_cost' 
                => $this->getTotalCostTwd(),
            
            // 稅額（如不需要稅務計算，回傳 0）
            'tax_amount' 
                => '0.0000',
            
            default => '0.0000',
        };
    }
	
	/**
	 * 取得退貨成本（原幣）
	 */
	public function getTotalCostOriginal(): string
	{
		$totalCost = '0.0000';
		
		foreach ($this->items as $item) {
			$unitCost = $item->unit_cost ?? $item->product?->cost ?? '0.0000';
			$itemCost = bcmul($unitCost, (string)$item->quantity, self::DECIMAL_PRECISION);
			$totalCost = bcadd($totalCost, $itemCost, self::DECIMAL_PRECISION);
		}
		
		return $totalCost;
	}

	/**
	 * 取得退貨成本（本幣，已換算）
	 */
	public function getTotalCostBase(): string
	{
		$totalCost = $this->getTotalCostOriginal();
		$rate = (string)($this->exchange_rate ?? '1.0000');
		
		return bcmul($totalCost, $rate, self::DECIMAL_PRECISION);
	}

    /**
     * 取得單號欄位名稱（AccountingService 所需）
     */
    public static function getDocumentNumberField(): string
    {
        return 'return_no';
    }

    /**
     * 取得單號值
     */
    public function getDocumentNumber(): string
    {
        return $this->return_no ?? 'SR-' . $this->id;
    }

    /**
     * 取得參考類型（AccountingService 所需）
     */
    public static function getReferenceType(): string
    {
        return 'sales_return';
    }

    // =========================================================================
    // SECTION: 內部科目解析方法
    // =========================================================================

    /**
     * 解析退貨專用動態科目
     * 規格格式：sales_return:refund
     */
    private function resolveSalesReturnDynamicAccount(?string $subType): string
    {
        return match($subType) {
            'refund' => $this->getRefundAccount(),
            default  => config('business.accounting_accounts.default', '1122'),
        };
    }

    /**
     * 解析銷售相關動態科目（相容原 Sale 模塊的科目）
     * 規格格式：sale:payment, sale:return_fee:shipping
     */
    private function resolveSaleDynamicAccount(?string $subType, ?string $thirdLevel = null): string
    {
        return match($subType) {
            'payment'     => $this->getRefundAccount(),
            'return_fee'  => $this->resolveReturnFeeAccount($thirdLevel),
            default       => config('business.accounting_accounts.default', '1122'),
        };
    }

    /**
     * 解析自動化動態科目
     * 規格格式：auto:inventory, auto:cost
     */
    private function resolveAutoDynamicAccount(?string $subType): string
    {
        return match($subType) {
            'inventory' => config('business.accounting_accounts.cost.inventory', '1405'),
            'cost'      => config('business.accounting_accounts.cost.cost_of_goods_sold', '5401'),
            default     => config('business.accounting_accounts.cost.inventory', '1405'),
        };
    }

    /**
     * 解析退貨費用科目
     */
    private function resolveReturnFeeAccount(?string $subType): string
    {
        return match($subType) {
            'shipping' => config('business.accounting_accounts.expenses.shipping_fee', '560106'),
            default    => config('business.accounting_accounts.expenses.other_expense', '560108'),
        };
    }

    /**
     * 預設科目解析（向後相容）
     */
    private function resolveDefaultAccount(string $dynamicSpec): string
    {
        Log::warning("未預期的動態科目規格: {$dynamicSpec}", [
            'model' => get_class($this),
            'id' => $this->id,
        ]);
        
        return config('business.accounting_accounts.default', '1122');
    }

    /**
     * 取得退款科目
     * 依據原銷售單的付款方式決定
     */
    protected function getRefundAccount(): string
    {
        $sale = $this->sale;
        
        if ($sale && $sale->payment_method) {
            // 優先使用 payment_accounts 設定
            $paymentAccount = config("business.payment_accounts.{$sale->payment_method}");
            if ($paymentAccount) {
                return $paymentAccount;
            }
            
            // 備援：硬編碼對應
            return match($sale->payment_method) {
                'cash'          => config('business.accounting_accounts.assets.cash', '100101'),
                'bank_transfer' => config('business.accounting_accounts.assets.bank_twd', '100201'),
                'shopee_pay'    => '101202',
                'line_pay'      => '101203',
                'taiwan_pay'    => '101201',
                'credit_card'   => config('business.accounting_accounts.assets.bank_twd', '100201'),
                default         => config('business.accounting_accounts.receivables.default', '1131'),
            };
        }
        
        return config('business.accounting_accounts.receivables.default', '1131');
    }

    // =========================================================================
    // SECTION: 金額計算方法
    // =========================================================================

    /**
     * 獲取退貨處理費總計（買家負擔，從退款中扣除）
     */
    public function getRestockingFeeTotal(): string
    {
        return (string)($this->fees()->where('fee_type', 'restocking_fee')->sum('amount') ?: '0.0000');
    }

    /**
     * 獲取退貨運費總計（賣家負擔，額外支出）
     */
    public function getReturnShippingFeeTotal(): string
    {
        return (string)($this->fees()->where('fee_type', 'return_shipping_fee')->sum('amount') ?: '0.0000');
    }

    /**
     * 計算退貨明細本幣總成本（用於成本沖減）
     */
    public function getTotalCostTwd(): string
    {
        $totalCostTwd = '0.0000';
        $rate = (string)($this->exchange_rate ?? '1.0000');

        $this->loadMissing(['items.product', 'items.saleItem']);
        
        foreach ($this->items as $item) {
            // 多層成本來源備援
            $unitCost = $item->unit_cost ?? '0.0000';
            
            if (bccomp($unitCost, '0.0000', self::DECIMAL_PRECISION) === 0 && $item->product) {
                $unitCost = (string)($item->product->cost ?? '0.0000');
            }
            
            if (bccomp($unitCost, '0.0000', self::DECIMAL_PRECISION) === 0 && $item->saleItem) {
                $unitCost = (string)($item->saleItem->unit_cost ?? '0.0000');
            }
            
            $itemCostTwd = bcmul($unitCost, (string)$item->quantity, self::DECIMAL_PRECISION);
            $itemCostTwd = bcmul($itemCostTwd, $rate, self::DECIMAL_PRECISION);
            $totalCostTwd = bcadd($totalCostTwd, $itemCostTwd, self::DECIMAL_PRECISION);
        }

        return $totalCostTwd;
    }

    /**
     * 重新計算並更新主表匯總金額
     */
    public function updateTotals(): void
    {
        $itemsSum = (string)($this->items()->sum('subtotal') ?: '0.0000');
        $feesSum = (string)($this->fees()->sum('amount') ?: '0.0000');
        
        $this->items_total_amount = $itemsSum;
        $this->fees_total_amount = $feesSum;
        $this->total_refund_amount = bcsub($itemsSum, $feesSum, self::DECIMAL_PRECISION);
        
        $this->saveQuietly();
    }

    // =========================================================================
    // SECTION: 業務邏輯方法
    // =========================================================================

    /**
     * 生成高併發安全的退貨單號
     * 格式：{sr_prefix}{YYYYMMDD}{4碼流水號}
     */
    public function generateReturnNo(): string
    {
		return DB::transaction(function () {
			$prefix = config('business.settings.sr_prefix', 'SR');
			$digits = (int) Setting::get('number_digits', 4);
			$datePart = now()->format('Ymd');
			$fullPrefix = $prefix . $datePart;

			$lastOrder = self::where('return_no', 'like', "{$fullPrefix}%")
				->lockForUpdate()
				->orderBy('return_no', 'desc')
				->first();

			$nextNumber = $lastOrder ? (int) substr($lastOrder->invoice_number, -$digits) + 1 : 1;
			return $fullPrefix . str_pad($nextNumber, $digits, '0', STR_PAD_LEFT);
		});
    }

    /**
     * 檢查是否可以執行過帳
     */
    public function canBePosted(): bool
    {
        return $this->status === 'approved' 
            && !empty($this->approved_by) 
            && $this->approved_at !== null
            && $this->status !== 'completed';
    }

    /**
     * 狀態轉換
     */
    public function transitionTo(string $newStatus): void
    {
        $this->status = match($this->status) {
            'pending' => match($newStatus) {
                'approved' => (function() {
                    $this->approved_by = auth()->id();
                    $this->approved_at = now();
                    return 'approved';
                })(),
                'cancelled' => 'cancelled',
                default => throw new \Exception('待處理單據僅能審核或取消'),
            },
            'approved' => $newStatus === 'completed' 
                ? 'completed' 
                : throw new \Exception('已審核單據僅能過帳'),
            'completed' => throw new \Exception('已結案單據不可變更狀態'),
            default => throw new \Exception('未定義的狀態轉換'),
        };
        
        $this->save();
    }

    /**
     * 過帳與庫存異動（核心業務）
     */
	public function post(): void
	{
		DB::transaction(function () {
			$frozenReturn = self::where('id', $this->id)->lockForUpdate()->firstOrFail();

			if (!$frozenReturn->canBePosted()) {
				throw new \Exception("單據 #{$frozenReturn->return_no} 目前狀態為 {$frozenReturn->status}，不符合過帳條件。");
			}

			if (empty($frozenReturn->approved_by) || empty($frozenReturn->approved_at)) {
				throw new \Exception('單據缺少審核人資訊，無法執行財務過帳。');
			}

			$frozenReturn->load(['items.product', 'sale']);

			// 1. 庫存異動（退貨入庫）
			foreach ($frozenReturn->items as $item) {
				if (!$item->is_restock) continue;

				$stock = Inventory::where('shop_id', $frozenReturn->shop_id)
					->where('product_id', $item->product_id)
					->where('warehouse_id', $frozenReturn->warehouse_id)
					->lockForUpdate()
					->first();

				if (!$stock) {
					$stock = Inventory::create([
						'shop_id'      => $frozenReturn->shop_id,
						'product_id'   => $item->product_id,
						'warehouse_id' => $frozenReturn->warehouse_id,
						'quantity'     => '0.0000',
					]);
				}

				$newQty = bcadd((string)$stock->quantity, (string)$item->quantity, self::DECIMAL_PRECISION);
				$stock->update(['quantity' => $newQty]);

				// 記錄庫存流水
				DB::table('inventory_movements')->insert([
					'shop_id'       => $frozenReturn->shop_id,
					'warehouse_id'  => $frozenReturn->warehouse_id,
					'product_id'    => $item->product_id,
					'quantity'      => $item->quantity,
					'cost_snapshot' => $item->product->cost ?? '0.0000',
					'type'          => 'SALES_RETURN',
					'reference'     => $frozenReturn->return_no,
					'remark'        => '退貨入庫 (單號: ' . $frozenReturn->return_no . ')',
					'user_id'       => auth()->id(),
					'created_at'    => now(),
					'updated_at'    => now(),
				]);
			}

			// 2. 🎯 會計過帳（參照銷售模塊，每個事件產生獨立 Journal）
			$this->postJournal('sales_return_refund');  // 退款分錄
			$this->postJournal('sales_return_cost');    // 成本沖減分錄

			// 3. 更新單據狀態
			$frozenReturn->status = 'completed';
			$frozenReturn->save();
		}, 3);
	}

    // =========================================================================
    // SECTION: Accessors
    // =========================================================================

    public function getAttribute($key)
    {
        static $returnReasons = null;
        static $returnFeeTypes = null;
        
        if ($returnReasons === null) {
            $returnReasons = config('business.return_reasons', []);
        }
        
        if ($returnFeeTypes === null) {
            $returnFeeTypes = config('business.return_fee_types', []);
        }

        if ($key === 'return_reason') {
            $value = parent::getAttribute($key);
            return $returnReasons[$value] ?? $value;
        }

        if ($key === 'fee_type' && isset($this->attributes['fee_type'])) {
            $value = $this->attributes['fee_type'];
            return $returnFeeTypes[$value] ?? $value;
        }

        return parent::getAttribute($key);
    }

    public function getSubtotalAfterDiscountAttribute(): string
    {
        return (string)($this->items_total_amount ?? '0.0000');
    }

    // =========================================================================
    // SECTION: 關聯關係
    // =========================================================================

    public function fees(): HasMany
    {
        return $this->hasMany(SalesReturnFee::class, 'sales_return_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // =========================================================================
    // SECTION: Boot 事件
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->return_no)) {
                $model->return_no = $model->generateReturnNo();
            }
        });

        static::deleting(function ($salesReturn) {
            if ($salesReturn->status === 'completed') {
                throw new \Exception('已結案的退貨單無法刪除');
            }
        });
    }
}