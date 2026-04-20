<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Sale extends Model
{
    protected $guarded = [];
    
    protected $casts = [
        'sold_at' => 'datetime',
		'subtotal' => 'decimal:4',
        'customer_total' => 'decimal:4',
        'final_net_amount' => 'decimal:4',
    ];

    // 快取費用類型配置
    private static ?array $feeTypesCache = null;
	
	/**
     * 如果已有關聯的 SalesReturn 
	 * 且狀態為 pending 或 completed，則鎖定
     */
	public function isLocked(): bool
	{
		return $this->returns()->whereIn('status', ['pending', 'approved', 'completed'])->exists();
	}
	
	/**
     * 第一階段判斷：是否存在任何（非作廢）的退貨紀錄
     */
    public function hasReturnRecords(): bool
    {
        // 排除已取消 (cancelled) 的退貨單，僅鎖定處理中、已審核或已完成的單據
        return $this->returns()
            ->whereIn('status', ['pending', 'approved', 'completed'])
            ->exists();
    }
	
	/**
     * 第二階段：綜合判斷是否允許變動（刪除、修改、再次退貨）
     */
    public function canBeModified(): bool
    {
        // 如果已經有退貨紀錄，則不允許任何變動
        if ($this->hasReturnRecords()) {
            return false;
        }

        // 此外可增加其他判斷，例如：單據是否已結案 (completed)
        return $this->status !== 'completed';
    }

    /**
     * 動態攔截所有費用屬性
     */
    public function getAttribute($key)
    {
        // 初始化快取
        if (self::$feeTypesCache === null) {
            self::$feeTypesCache = config('business.fee_types', []);
        }
        
        // 如果是定義的費用類型，從 sale_fees 計算
        if (isset(self::$feeTypesCache[$key])) {
            // 關聯已載入時直接計算
            if ($this->relationLoaded('fees')) {
                return (string) $this->fees->where('fee_type', $key)->sum('amount');
            }
            // 未載入時使用查詢（避免 N+1）
            return (string) $this->fees()->where('fee_type', $key)->sum('amount');
        }
        
        // 其他屬性走預設邏輯
        return parent::getAttribute($key);
    }

    protected static function booted()
    {
        static::deleting(function ($sale) {
			if ($sale->hasReturnRecords()) {
				throw new \Exception('此銷售單已有退貨紀錄，禁止刪除。');
			}
		});

		static::updating(function ($sale) {
			if ($sale->hasReturnRecords()) {
				throw new \Exception('此銷售單已有退貨紀錄，禁止修改。');
			}
		});
		
		static::creating(function ($sale) {
            if (empty($sale->invoice_number)) {
                $sale->invoice_number = self::generateInvoiceNumber();
            }
        });
    }
    
    public static function generateInvoiceNumber(): string
    {
        return DB::transaction(function () {
            $prefix = Setting::get('so_prefix', 'SO-');
            $digits = (int) Setting::get('number_digits', 5);
            $datePart = now()->format('Ymd');
            $fullPrefix = $prefix . $datePart;

            $lastOrder = self::where('invoice_number', 'like', "{$fullPrefix}%")
                        ->lockForUpdate()
                        ->orderBy('invoice_number', 'desc')
                        ->first();

            if ($lastOrder) {
                $lastNumber = (int) substr($lastOrder->invoice_number, -$digits);
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            return $fullPrefix . str_pad($nextNumber, $digits, '0', STR_PAD_LEFT);
        });
    }
    
    public function getPaymentMethodNameAttribute(): string
    {
        return collect(config('business.payment_methods'))
            ->firstWhere('id', $this->payment_method)['name'] ?? $this->payment_method;
    }

    /**
	 * 【修正】解決 $sale 變數未定義的問題
	 */
	public static function createWithCalculations(array $data, array $items)
	{
		return DB::transaction(function () use ($data, $items) {
            $feeConfigs = config('business.fee_types', []);
            
            // 1. 過濾掉不屬於 sales 主表的欄位（即費用欄位）
            $saleFields = array_diff_key($data, $feeConfigs);
            
            // 2. 建立 Sale 主表紀錄
            $sale = self::create($saleFields);
            
            $allowNegative = Setting::get('allow_negative_stock', false);

            foreach ($items as $item) {
                $warehouseId = $item['warehouse_id'] ?? $data['warehouse_id'];
                
                $inventory = Inventory::where('product_id', $item['product_id'])
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->first();

                $currentQty = $inventory ? $inventory->quantity : 0;

                if (!$allowNegative && bccomp((string)$currentQty, (string)$item['quantity'], 4) === -1) {
                    throw new \Exception("商品 ID {$item['product_id']} 庫存不足。");
                }

                $newQty = bcsub((string)$currentQty, (string)$item['quantity'], 4);

                Inventory::updateOrCreate(
                    ['product_id' => $item['product_id'], 'warehouse_id' => $warehouseId],
                    ['quantity' => $newQty]
                );

                $sale->items()->create([
                    'product_id'   => $item['product_id'],
                    'warehouse_id' => $warehouseId,
                    'price'        => $item['price'],
                    'quantity'     => $item['quantity'],
                    'subtotal'     => bcmul((string)$item['quantity'], (string)$item['price'], 4),
                ]);
            }

            // 3. 儲存費用到 sale_fees 關聯表
            foreach ($data as $key => $value) {
                // 檢查是否為定義的費用類型，且金額不為 0
                if (isset($feeConfigs[$key]) && bccomp((string)$value, '0', 4) !== 0) {
                    $sale->fees()->create([
                        'shop_id'  => auth()->user()->shop_id ?? 1,
                        'fee_type' => $key,
                        'amount'   => $value,
                        'note'     => $feeConfigs[$key]['name'] ?? $key,
                    ]);
                }
            }

            return $sale;
        });
    } 

    public function updateWithCalculations(array $data, array $items)
    {
        return DB::transaction(function () use ($data, $items) {
            \Log::info('Sale::updateWithCalculations 開始', ['sale_id' => $this->id]);
            
            $oldItems = $this->items->keyBy(function ($item) {
                return $item->product_id . '-' . $item->warehouse_id;
            });

            $feeConfigs = config('business.fee_types');
            $saleData = array_diff_key($data, $feeConfigs);
            
            $this->update($saleData);

            // 更新費用明細
            $this->fees()->delete();
            foreach ($data as $key => $value) {
                if (isset($feeConfigs[$key]) && (float)$value != 0) {
                    $this->fees()->create([
                        'shop_id'  => auth()->user()->shop_id ?? 1,
                        'sale_id'  => $this->id,
                        'fee_type' => $key,
                        'amount'   => $value,
                        'note'     => $feeConfigs[$key]['name'],
                    ]);
                }
            }

            $this->items()->delete();
            
            foreach ($items as $item) {
                if (empty($item['product_id'])) continue;

                $warehouseId = $item['warehouse_id'] ?? $saleData['warehouse_id'];

                $this->items()->create([
                    'product_id'   => $item['product_id'],
                    'warehouse_id' => $warehouseId,
                    'price'        => $item['price'],
                    'quantity'     => $item['quantity'],
                    'subtotal'     => bcmul((string)$item['quantity'], (string)$item['price'], 2),
                ]);

                $key = $item['product_id'] . '-' . $warehouseId;
                $oldQty = $oldItems->has($key) ? (float)$oldItems[$key]->quantity : 0;
                $newQty = (float)$item['quantity'];
                $diff = $newQty - $oldQty;

                if ($diff > 0) {
                    $this->processStockReduction($item['product_id'], $warehouseId, $diff);
                } elseif ($diff < 0) {
                    $this->restoreStock($item['product_id'], $warehouseId, abs($diff));
                }
            }

            return $this;
        });
    }
    
    protected function processStockReduction($productId, $warehouseId, $amount)
    {
        $needed = $amount;
        $stocks = Inventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'in_stock')
            ->where('quantity', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($stocks as $inv) {
            if ($needed <= 0) break;
            if ($inv->quantity >= $needed) {
                $inv->decrement('quantity', $needed);
                $needed = 0;
            } else {
                $needed -= $inv->quantity;
                $inv->update(['quantity' => 0, 'status' => 'sold']);
            }
            if ($inv->fresh()->quantity <= 0) $inv->update(['status' => 'sold']);
        }

        if ($needed > 0) throw new \Exception("庫存不足，尚缺 {$needed} 單位");
    }

    protected function restoreStock($productId, $warehouseId, $amount)
    {
        $inv = Inventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'sold')
            ->orderBy('updated_at', 'desc')
            ->first();
        
        if ($inv) {
            $inv->increment('quantity', $amount);
            $inv->update(['status' => 'in_stock']);
        } else {            
            throw new \Exception("無法還原庫存：找不到對應的庫存紀錄");    
        }
    }
    
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany 
    {
        return $this->hasMany(SaleItem::class); 
    }
    
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'channel', 'id');
    }
    
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function fees(): HasMany
    {
        return $this->hasMany(SaleFee::class);
    }
	
	public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class, 'sale_id');
    }
}