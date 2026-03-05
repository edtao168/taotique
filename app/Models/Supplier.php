<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        // 如果未來要擴展多店，建議在此處預留多店關聯欄位，如 shop_id
    ];
	protected $casts = [
		'contact_json' => 'array', // 自動在 Array 與 JSON 間轉換
	];

    /**
     * 關聯：供應商擁有的庫存紀錄
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * 業務邏輯：獲取該供應商提供的商品種類數量
     */
    public function getProductsCountAttribute(): int
    {
        return $this->inventories()->distinct('product_id')->count();
    }
}