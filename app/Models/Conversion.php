<?php // app/Models/Conversion.php

namespace App\Models;

use App\Traits\HasShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversion extends Model
{
    use SoftDeletes, HasShop;

    protected $fillable = [
        'shop_id', 
        'warehouse_id',
        'conversion_no', 
        'process_date', 
        'user_id', 
        'remark'
    ];

    protected $casts = [
        'process_date' => 'date',
    ];

    public function items(): HasMany { return $this->hasMany(ConversionItem::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }

    public function post(): void
    {
        \DB::transaction(function () {
            foreach ($this->items as $item) {
                // 使用 shop_id 進行庫存鎖定
                $inventory = Inventory::where('shop_id', $this->shop_id)
                    ->where('warehouse_id', $item->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->firstOrCreate([
                        'shop_id' => $this->shop_id,
                        'warehouse_id' => $item->warehouse_id,
                        'product_id' => $item->product_id,
                    ], ['quantity' => '0.0000', 'cost' => '0.0000']);

                if ($item->type === 1) { 
                    // 投入：扣庫存
                    $inventory->quantity = bcsub($inventory->quantity, $item->quantity, 4);
                } else {
                    // 產出：WAC 計算
                    $newQty = $item->quantity;
                    $newPrice = $item->cost_snapshot;

                    $currentValue = bcmul($inventory->quantity, $inventory->cost, 4);
                    $addedValue = bcmul($newQty, $newPrice, 4);
                    $totalQty = bcadd($inventory->quantity, $newQty, 4);
                    
                    if (bccomp($totalQty, '0', 4) > 0) {
                        $totalValue = bcadd($currentValue, $addedValue, 4);
                        $inventory->cost = bcdiv($totalValue, $totalQty, 4);
                    }
                    $inventory->quantity = $totalQty;
                }
                $inventory->save();

                // 紀錄異動流水
                InventoryMovement::create([
                    'shop_id' => $this->shop_id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'quantity' => ($item->type === 1) ? bcmul($item->quantity, '-1', 4) : $item->quantity,
                    'cost_snapshot' => ($item->type === 1) ? $inventory->cost : $item->cost_snapshot,
                    'type' => 'CONVERSION',
                    'reference' => $this->conversion_no,
                    'user_id' => $this->user_id,
                ]);
            }
        });
    }
}