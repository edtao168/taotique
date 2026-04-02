<?php // app/Models/SalesReturnItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    protected $fillable = [
        'shop_id',
        'sales_return_id', 
        'sales_order_item_id',
        'product_id', 
        'quantity', 
        'unit_price', 
        'subtotal', 
        'tax_rate',
        'tax_amount',
        'condition',
        'is_restock',
        'return_reason'
    ];
    
    protected $casts = [        
		'quantity' 	 => 'decimal:4',
		'unit_price' => 'decimal:4',
		'subtotal'   => 'decimal:4',		
		'tax_rate'   => 'decimal:2',
		'tax_amount' => 'decimal:4',
    ];

    public function saleItem(): BelongsTo
	{		
		return $this->belongsTo(SaleItem::class, 'sale_item_id'); 
	}
	
	// 關聯到退單主表
    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class);
    }

    // 關聯到產品
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}