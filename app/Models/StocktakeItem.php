<?php // app/Models/StocktakeItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsDecimal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StocktakeItem extends Model
{
    protected $fillable = [
        'stocktake_id', 'product_id', 'system_quantity', 
        'actual_quantity', 'cost_price'
    ];

    // 強制數值嚴謹性
    protected $casts = [
        'system_quantity' => 'decimal:4',
        'actual_quantity' => 'decimal:4',
        'cost_price'      => 'decimal:4',
    ];
	
	public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}