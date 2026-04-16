<?php

namespace App\Models;

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
        'currency',
        'exchange_rate',
        'total_amount',
        'total_twd',
        'purchased_at',
        'remark'
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'exchange_rate' => 'decimal:4',
            'total_twd' => 'decimal:4',
            'total_amount' => 'decimal:4',
        ];
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
    public function processInbound(array $inputItems)
    {
        return DB::transaction(function () use ($inputItems) {
            $grandTotalTWD = '0';
            $grandTotalForeign = '0';

            foreach ($inputItems as $item) {
                // 1. 計算本幣單價 (BCMath)
                $costTwd = bcmul($item['foreign_price'], $this->exchange_rate, 4);
                $subtotalTwd = bcmul($costTwd, $item['quantity'], 4);
                
                // 2. 建立明細
                $this->items()->create([
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'quantity' => $item['quantity'],
                    'foreign_price' => $item['foreign_price'],
                    'cost_twd' => $costTwd,
                    'subtotal_twd' => $subtotalTwd,
                ]);

                // 3. 建立庫存紀錄 (Inventories)
                Inventory::create([
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'supplier_id' => $this->supplier_id,
                    'quantity' => $item['quantity'],
                    'cost' => $costTwd,
                    'status' => 'in_stock'
                ]);

                // 4. 更新產品加權平均成本 (厚 Model 呼叫)
                $product = Product::find($item['product_id']);
                $product->updateWeightedAverageCost($item['quantity'], $costTwd);

                // 累計總額
                $grandTotalTWD = bcadd($grandTotalTWD, $subtotalTwd, 4);
                $grandTotalForeign = bcadd($grandTotalForeign, bcmul($item['foreign_price'], $item['quantity'], 4), 4);
            }

            // 更新主表總額
            $this->update([
                'total_twd' => $grandTotalTWD,
                'total_amount' => $grandTotalForeign
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
}