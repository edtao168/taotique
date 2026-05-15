<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    // 允許批次寫入的欄位
    protected $fillable = ['product_id', 'path', 'is_primary'];
	
	// 強制轉型確保邏輯正確
	protected $casts = [
		'is_primary' => 'boolean',
	];

    // 反向關聯商品
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
