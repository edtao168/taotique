<?php

namespace App\Models;

use App\Models\Inventory;
use App\Models\PurchaseItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Purchase extends Model
{
    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'user_id',
		'warehouse_id',
        'currency',
        'exchange_rate',
		'subtotal',
		'shipping_fee',
        'tax',
        'other_fees',
        'discount',
        'total_amount',
        'total_twd',
        'purchased_at',
		'stocked_in_at',
        'remark'
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' 	=> 'datetime',
			'stocked_in_at' => 'datetime',
            'exchange_rate' => 'decimal:4',
            'total_twd' 	=> 'decimal:4',
			'subtotal' 		=> 'decimal:4',
            'total_amount' 	=> 'decimal:4',
        ];
    }
	
	/**
     * 判定採購單是否已鎖定 (不允許任何修改)
     */
	public function isLocked(): bool
	{
		return $this->returns()->whereIn('status', ['pending', 'approved', 'completed'])->exists();
	}
	
	/**
     * 判斷是否有採購退貨紀錄
     */
    public function hasReturnRecords(): bool
    {
        // 排除已取消的退貨單（若有狀態定義）
        return $this->returns()
            ->whereIn('status', ['pending', 'completed']) 
            ->exists();
    }

    /**
     * 判斷單據是否允許異動
     */
    public function canBeModified(): bool
    {
        return !$this->hasReturnRecords();
    }

    // --- 新增的單號生成邏輯 (參考 Sale.php) ---
    protected static function booted()
    {
        static::creating(function ($purchase) {
            // 如果儲存時 purchase_number 是空的，則自動生成
            if (empty($purchase->purchase_number)) {
                $purchase->purchase_number = self::generatePurchaseNumber();
            }
        });
    }

	/**
	 * 嚴謹的金額運算邏輯
	 */
	public function calculateAndSetTotals()
	{
		// 1. 計算原始幣別總額：(小計 + 運費) - 折扣
		$total = bcadd($this->subtotal_amount, $this->shipping_fee, 4);
		$this->total_amount = bcsub($total, $this->discount, 4);

		// 2. 換算本幣：total_amount * exchange_rate
		$this->total_twd = bcmul($this->total_amount, $this->exchange_rate, 4);
	}

    /**
     * 產生採購單號碼 (使用統一的 Setting 方法)
     */
    public static function generatePurchaseNumber(): string
    {
        // 從 settings 表抓取前綴，預設 PO-
		$prefix = Setting::get('po_prefix', 'PO-'); 
		$date = now()->format('Ymd');
		
		// 取得當日最後一筆序號
		$lastOrder = self::whereDate('created_at', now()->toDateString())
			->orderBy('id', 'desc')
			->first();
			
		$sequence = $lastOrder ? (int)substr($lastOrder->purchase_number, -4) + 1 : 1;
		$digits = Setting::get('number_digits', 4); // 根據設定檔決定流水號位數
		
		return $prefix . $date . str_pad($sequence, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * 執行採購單入庫：處理明細、換算匯率、更新庫存與加權成本
     */
    public function processInbound()
    {
        // 檢查是否已入庫
		if ($this->stocked_in_at) {
			throw new \Exception("此單據已入庫。");
		}
		
		return DB::transaction(function () {
            // 遍歷此採購單下所有的明細
			foreach ($this->items as $item) {
				// 1. 建立庫存紀錄 (Inventories)
				Inventory::create([
					'product_id'   => $item->product_id,
					'warehouse_id' => $item->warehouse_id,
					'supplier_id'  => $this->supplier_id,
					'quantity'     => $item->quantity,
					'cost'         => $item->cost_twd,
					'status'       => 'in_stock'
				]);

				// 2. 更新產品加權平均成本
				$product = Product::find($item->product_id);
				if ($product) {
					$product->updateWeightedAverageCost($item->quantity, $item->cost_twd);
				}
			}

			// 3. 標記主表為已入庫
			$this->update([
				'stocked_in_at' => now(),
			]);
        });
    }
	
	/**
     * 明細
     */	 
	public function items(): HasMany
	{
		return $this->hasMany(PurchaseItem::class); 
	}
	
	/**
     * 供應商
     */
	public function supplier(): BelongsTo
	{		
		return $this->belongsTo(Supplier::class);
	}
	
	/**
     * 倉庫
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
	
	/**
     * 建立者
     */
	 public function user(): BelongsTo
	{
		// 假設您的 sales 表中有 user_id 欄位
		return $this->belongsTo(User::class);
	}
	
	/**
     * 定義與採購退貨單的關聯
     */
    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class, 'purchase_id');
    }
}