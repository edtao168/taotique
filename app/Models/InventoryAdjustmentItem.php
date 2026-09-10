<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustmentItem extends Model
{
    use HasFactory;

    /**
     * 資料表名稱
     *
     * @var string
     */
    protected $table = 'inventory_adjustment_items';

    /**
     * 可批量賦值的屬性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'inventory_adjustment_id',
        'product_id',
        'quantity',
        'unit_cost',
        'total_amount',
    ];

    /**
     * 屬性型別轉換
     *
     * @var array<string, string>
     */
    protected $casts = [
        'inventory_adjustment_id' => 'integer',
        'product_id'              => 'integer',
        'quantity'                => 'decimal:4',
        'unit_cost'               => 'decimal:4',
        'total_amount'            => 'decimal:4',
    ];

    /**
     * 所屬的庫存調整單
     */
    public function inventoryAdjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class);
    }

    /**
     * 所屬的商品
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}