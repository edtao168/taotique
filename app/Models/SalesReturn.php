<?php // app/Models/SalesReturn.php

namespace App\Models;

use App\Traits\HasShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SalesReturn extends Model
{
    use HasShop;
	
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
        'remark',
        'created_by',
        'approved_by',
        'approved_at'
    ];

    protected $casts = [        
		'total_refund_amount' => 'decimal:4',
		'exchange_rate'       => 'decimal:6',
        'approved_at'         => 'datetime',
    ];

    // 關聯費用明細
    public function fees(): HasMany
    {
        return $this->hasMany(SalesReturnFee::class, 'sales_return_id');
    }
	
	// 關聯客戶
	public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
	
	// 關聯營業點
	public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
	
	// 關聯庫別
	public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
	
	// 關聯明細
    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    // 關聯原訂單
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
	
	/**
     * 建立者
     */
	 public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}

	/**
	 * 動態攔截退貨費用屬性
	 */
	public function getAttribute($key)
	{
		$returnFeeConfigs = config('business.return_fee_types', []);
		
		if (isset($returnFeeConfigs[$key])) {
			if ($this->relationLoaded('fees')) {
				return (string) $this->fees->where('fee_type', $key)->sum('amount');
			}
			return (string) $this->fees()->where('fee_type', $key)->sum('amount');
		}
		
		return parent::getAttribute($key);
	}

    /**
     * 計算應退金額 (厚 Model 邏輯)
     * Refund = Σ(items.subtotal) - restocking_fee + shipping_fee
     */
    public function calculateRefund(): string
    {
        $sum = $this->items->sum('subtotal');
        
        // 規範 1: 強制 BCMath
        $net = bcsub($sum, $this->restocking_fee, 4);
        return bcadd($net, $this->shipping_fee, 4);
    }

	// 檢查是否可以執行過帳
	public function canBePosted(): bool
	{
		return $this->status === 'approved' 
			&& !empty($this->approved_by) 
			&& $this->status !== 'completed';
	}
	
	public function transitionTo(string $newStatus)
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
			'approved'  => $newStatus === 'completed' ? 'completed' : throw new \Exception('已審核單據僅能過帳'),
			'completed' => throw new \Exception('已結案單據不可變更狀態'),
			default     => throw new \Exception('未定義的狀態轉換'),
		};
		$this->save();
	}

	/**
     * 重新計算並更新主表匯總金額
     * 公式：Net Refund = Σ(items.subtotal) - Σ(fees.amount)
     */
    public function updateTotals(): void
    {
        // 取得商品小計 (這裡假設 items 已經存入資料庫)
        $itemsSum = $this->items()->sum('subtotal') ?: '0.0000';
        
        // 取得費用小計
        $feesSum = $this->fees()->sum('amount') ?: '0.0000';
        
        $this->items_total_amount = $itemsSum;
        $this->fees_total_amount = $feesSum;
        
        // 規範 1: 強制 BCMath 運算
        // 注意：費用通常是從退款中扣除，所以用 sub
        $this->total_refund_amount = bcsub($itemsSum, $feesSum, 4);
        
        $this->save();
    }
	
    /**
     * 過帳與庫存異動
     */
    public function post()
    {
		if (!$this->canBePosted()) {
        throw new \Exception("單據 #{$this->return_no} 目前狀態為 {$this->status}，不符合過帳條件。");
    }

		// 3. 安全審計檢查：確保有審核人與時間 (這對應了您 Migration 中的欄位)
		if (empty($this->approved_by) || empty($this->approved_at)) {
			throw new \Exception('單據缺少審核人資訊，無法執行財務過帳。');
		}

		return DB::transaction(function () {
			// 規範 2: 針對相關記錄進行 lockForUpdate()
			$this->load(['items.product']);
			
			foreach ($this->items as $item) {
				if (!$item->is_restock) continue;

				// 鎖定特定倉庫的產品庫存
				$stock = Stock::where('product_id', $item->product_id)
					->where('warehouse_id', $this->warehouse_id)
					->lockForUpdate()
					->firstOrFail();

				// 規範 1: 強制 BCMath 運算
				$newQty = bcadd($stock->quantity, $item->quantity, 4);
				
				$stock->update(['quantity' => $newQty]);

				// 紀錄庫存流水 (StockLog)
				StockLog::create([
					'shop_id'      => $this->shop_id, // 規範 5: 多店預留
					'warehouse_id' => $this->warehouse_id,
					'product_id'   => $item->product_id,
					'type'         => 'SALES_RETURN',
					'reference_id' => $this->id,
					'change_qty'   => $item->quantity,
					'after_qty'    => $newQty,
					'created_by'   => auth()->id(),
				]);
			}

			// 4. 更新單據最終狀態
			$this->status = 'completed';
			return $this->save();
		});
    }
}