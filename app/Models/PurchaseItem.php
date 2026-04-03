<?php // 檔案路徑：app/Models/PurchaseItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    /**
     * 允許批次賦值的欄位
     */
    protected $fillable = [
        'store_id',
        'purchase_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'foreign_price',
        'cost_twd',
        'subtotal_twd',
    ];

    /**
     * 數值嚴謹性規範：強制轉換為 4 位小數的字串以利 BCMath 運算
     */
    protected function casts(): array
    {
        return [
            'quantity'      => 'decimal:4',
            'foreign_price' => 'decimal:4',
            'cost_twd'      => 'decimal:4',
            'subtotal_twd'  => 'decimal:4',			
        ];
    }

    /**
     * 關聯：所屬採購單
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * 關聯：對應商品
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}