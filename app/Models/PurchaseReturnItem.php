<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class PurchaseReturnItem extends Model
{
    protected $fillable = [
        'shop_id',
				'purchase_return_id',
				'purchase_item_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal',        
    ];

    protected $casts = [        
			'quantity' 	 => 'decimal:4',
			'unit_price' => 'decimal:4',
			'subtotal'   => 'decimal:4',
			
    ];

    public function purchaseItem(): BelongsTo
		{		
			return $this->belongsTo(PurchaseItem::class, 'purchase_item_id'); 
		}
	
	// 關聯到退單主表
    public function purchasesReturn()
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    // 關聯到產品
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}