<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    // 允許批量寫入的欄位
    protected $fillable = [
        'product_id', 
        'warehouse_id', 
        'quantity', 
        'type', 
        'reference', 
        'remark', 
        'user_id'
    ];

    // 關聯商品
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }	

    // 關聯倉庫
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // 關聯操作人員
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
	
	public function getTypeNameAttribute(): string
	{
		return match ($this->type) {
			'sale' => '銷售',
			'purchase' => '採購',
			'transfer' => '調撥',
			'stocktake' => '盤點',
			default => $this->type,
		};
	}
	
	public function getTypeColorAttribute(): string
	{
		return match($this->type) {
			'transfer' => 'badge-info',
			'stocktake' => 'badge-warning',
			'sale' => 'badge-error',
			'purchase' => 'badge-success',
			default => 'badge-neutral',
		};
	}
}