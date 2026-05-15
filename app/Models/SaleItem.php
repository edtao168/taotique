<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
		'warehouse_id',		
        'price',
        'quantity',
        'subtotal',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * 獲取所屬的銷售
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * 獲取所屬的商品
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
	
	/**
     * 出貨倉庫
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}