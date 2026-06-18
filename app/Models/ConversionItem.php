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
	
	// 驗證成本不為空
    protected static function booted(): void
    {
        static::creating(function (self $item) {
            // 投入項目必須有成本
            if ($item->type === 1 && bccomp($item->cost_snapshot, '0', 4) <= 0) {
                $productName = $item->product?->name ?? '商品#' . $item->product_id;
                throw new \Exception("投入項目「{$productName}」必須有單位成本，請確認庫存成本是否有值");
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(Conversion::class);
    }
	
	public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}