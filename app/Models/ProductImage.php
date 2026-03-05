<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    // 允許批次寫入的欄位
    protected $fillable = ['product_id', 'path'];

    // 反向關聯商品
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
