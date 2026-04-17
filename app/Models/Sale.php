<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Sale extends Model
{
    protected $guarded = [];
    
    protected $casts = [
        'sold_at' => 'datetime',
    ];

    // 快取費用類型配置
    private static ?array $feeTypesCache = null;

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

    public static function createWithCalculations(array $data, array $items)
    {
        return DB::transaction(function () use ($data, $items) {
            $allowNegative = Setting::get('allow_negative_stock', false);

			foreach ($items as $item) {
				// 鎖定庫存紀錄
				$inventory = Inventory::where('product_id', $item['product_id'])
					->where('warehouse_id', $formData['warehouse_id'])
					->lockForUpdate()
					->first();

				$currentQty = $inventory ? $inventory->quantity : 0;

				// 如果不允許負庫存且數量不足，拋出異常觸發 Transaction 回滾
				if (!$allowNegative && $currentQty < $item['quantity']) {
					throw new \Exception("商品 ID {$item['product_id']} 庫存不足，無法出庫。");
				}

				// 執行扣除 (即使結果是負數，bcsub 也能處理)
				$newQty = bcsub($currentQty, $item['quantity'], 4);

				Inventory::updateOrCreate(
					['product_id' => $item['product_id'], 'warehouse_id' => $formData['warehouse_id']],
					['quantity' => $newQty]
				);
			}
		
			// 分離費用資料與主表資料
            $feeConfigs = config('business.fee_types');
            $saleData = array_diff_key($data, $feeConfigs);
            
            // 確保必要欄位存在
            //$saleData['subtotal'] = $data['subtotal'] ?? '0';
            //$saleData['customer_total'] = $data['customer_total'] ?? '0';
            //$saleData['final_net_amount'] = $data['final_net_amount'] ?? '0';
            
            $sale = self::create($saleData);

            // 儲存費用到 sale_fees
            foreach ($data as $key => $value) {
                if (isset($feeConfigs[$key]) && (float)$value != 0) {
                    $sale->fees()->create([
                        'shop_id'  => auth()->user()->shop_id ?? 1,
                        'sale_id'  => $sale->id,
                        'fee_type' => $key,
                        'amount'   => $value,
                        'note'     => $feeConfigs[$key]['name'],
                    ]);
                }
            }

            $sale->processItemsAndStock($items);
            
            return $sale;
        });
    }

    public function updateWithCalculations(array $data, array $items)
    {
        return DB::transaction(function () use ($data, $items) {
            $oldItems = $this->items->keyBy(function ($item) {
                return $item->product_id . '-' . $item->warehouse_id;
            });

            // 分離費用資料
            $feeConfigs = config('business.fee_types');
            $saleData = array_diff_key($data, $feeConfigs);
            
            //self::prepareFinancials($saleData);
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

                $this->items()->create([
                    'product_id'   => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'price'        => $item['price'],
                    'quantity'     => $item['quantity'],
                    'subtotal'     => bcmul((string)$item['quantity'], (string)$item['price'], 2),
                ]);

                $key = $item['product_id'] . '-' . $item['warehouse_id'];
                $oldQty = $oldItems->has($key) ? (float)$oldItems[$key]->quantity : 0;
                $newQty = (float)$item['quantity'];
                $diff = $newQty - $oldQty;

                if ($diff > 0) {
                    $this->processStockReduction($item['product_id'], $item['warehouse_id'], $diff);
                } elseif ($diff < 0) {
                    $this->restoreStock($item['product_id'], $item['warehouse_id'], abs($diff));
                }
            }

            return $this;
        });
    }

    protected function processItemsAndStock(array $items)
    {
        foreach ($items as $item) {
            if (empty($item['product_id'])) continue;

            $this->items()->create([
                'product_id'   => $item['product_id'],
                'warehouse_id' => $item['warehouse_id'],
                'price'        => $item['price'],
                'quantity'     => $item['quantity'],
                'subtotal'     => bcmul((string)$item['quantity'], (string)$item['price'], 2),
            ]);

            $needed = $item['quantity'];
            $stocks = Inventory::where('product_id', $item['product_id'])
                ->where('warehouse_id', $item['warehouse_id'])
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
            if ($needed > 0) throw new \Exception("庫存不足");
        }
    }
    
    public function getTotalFeeByTarget(string $target): string
    {
        $feeConfigs = config('business.fee_types');
        $total = '0.0000';
        
        foreach ($this->fees as $fee) {
            $config = $feeConfigs[$fee->fee_type] ?? null;
            if (!$config || $config['target'] !== $target) continue;
            
            if ($config['operator'] === 'add') {
                $total = bcadd($total, (string)$fee->amount, 4);
            } else {
                $total = bcsub($total, (string)$fee->amount, 4);
            }
        }
        return $total;
    }
    
    private static function prepareFinancials(array &$data) {
        $subtotal = (string)($data['subtotal'] ?? '0');
        $discount = (string)($data['discount'] ?? '0');
        $platCoupon = (string)($data['platform_coupon'] ?? '0');
        $shipCust = (string)($data['shipping_fee_customer'] ?? '0');
        $shipPlat = (string)($data['shipping_fee_platform'] ?? '0');
        $platFee  = (string)($data['platform_fee'] ?? '0');
        $adj      = (string)($data['order_adjustment'] ?? '0');

        $buyTotal = bcadd($subtotal, $shipCust, 2);
        $buyTotal = bcsub($buyTotal, $discount, 2);
        $data['customer_total'] = bcsub($buyTotal, $platCoupon, 2);

        $net = bcsub($subtotal, $shipPlat, 2);
        $net = bcsub($net, $platFee, 2);
        $data['final_net_amount'] = bcsub($net, $adj, 2);
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
}