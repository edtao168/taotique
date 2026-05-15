<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversionItem extends Model
{
    protected $fillable = [
        'conversion_id', 
        'product_id', 
        'warehouse_id', 
        'type', 
        'quantity', 
        'cost_snapshot'
    ];

    /**
     * 數值嚴謹性：標註 AsDecimal:4
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'cost_snapshot' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(Conversion::class);
    }
}