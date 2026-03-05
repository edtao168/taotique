<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Purchase extends Model
{
    protected $fillable = [
        'purchase_number', 'supplier_id', 'user_id', 
        'currency', 'exchange_rate', 'total_foreign', 'total_twd', 
        'purchased_at', 'remark'
    ];

    public function items() { return $this->hasMany(PurchaseItem::class); }

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
}