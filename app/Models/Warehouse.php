<?php

// app/Models/Warehouse.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    // 確保這裡對應你剛才改名的 shop_id
    protected $fillable = ['name', 'shop_id', 'is_active'];

    /**
     * 庫別屬於哪一個店鋪
     */
    public function shop(): BelongsTo
    {
        // 這裡會尋找 warehouses 表中的 shop_id 欄位
        return $this->belongsTo(Shop::class);
    }
	
	// app/Models/Warehouse.php 定義一個靜態方法
	public static function getOptions(): array
	{
		return self::with('shop')
			->get()
			->map(fn($w) => [
				'id'   => $w->id,
				'name' => "{$w->shop->name} - {$w->name}"
			])->toArray();
	}
}