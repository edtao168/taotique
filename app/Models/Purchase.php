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
        'total_foreign',
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
            'total_foreign' => 'decimal:4',
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
     * 產生採購單號碼 (使用統一的 Setting 方法)
     */
    public static function generatePurchaseNumber(): string
    {
        return DB::transaction(function () {
            // 使用統一的 Setting::get() 方法
            $prefix = Setting::get('po_prefix', 'PO-'); // 讀取採購前綴，默認為 PO-
            $digits = (int) Setting::get('number_digits', 5);
            $datePart = now()->format('Ymd');
            $fullPrefix = $prefix . $datePart;

            // 鎖定資料表取得最新流水號 (防止並發衝突)
            $lastOrder = self::where('purchase_number', 'like', "{$fullPrefix}%")
                ->lockForUpdate()
                ->orderBy('purchase_number', 'desc')
                ->first();

            if ($lastOrder) {
                // 從最後一筆單號中截取流水號部分並遞增
                $lastNumberStr = substr($lastOrder->purchase_number, strlen($fullPrefix));
                $lastNumber = (int) $lastNumberStr;
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            return $fullPrefix . str_pad($nextNumber, $digits, '0', STR_PAD_LEFT);
        });
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
                'total_foreign' => $grandTotalForeign
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