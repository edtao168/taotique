<?php

namespace App\Traits;

use App\Models\Product;

trait HasProductSearch
{
    public $products = [];

    /**
     * 共用的商品搜尋邏輯
     */
    public function search(string $value = '')
    {
        $this->products = Product::query()
			->withSum(['inventories as stock' => function($query) {
				$query->where('status', 'in_stock'); 
			}], 'quantity')
            ->where('sku', 'like', "%{$value}%")
            ->orWhere('name', 'like', "%{$value}%")
            ->take(10)
            ->get()
			->map(fn($p) => [
			'id'   => $p->id,
				// 格式：SKU：商品名稱 = 庫存量
				'name' => "{$p->sku}：{$p->name} = " . number_format($p->stock ?? 0, 0), 
			]);
    }

    /**
     * 當選中商品時的共用填充邏輯
     * $index: 陣列索引, $productId: 選中的 ID, $targetArray: 它是 items 還是其他
     */
    public function fillProductData($index, $productId, $targetArray = 'items')
    {
        if (!$productId) return;

        $product = Product::find($productId);
        if ($product) {
            $this->{$targetArray}[$index]['product_id'] = $product->id;
            $this->{$targetArray}[$index]['name'] = $product->name;
            // 採購可以帶入上次成本，銷售可以帶入售價
            if (isset($this->{$targetArray}[$index]['price'])) {
                $this->{$targetArray}[$index]['price'] = $product->price;
            }
        }
    }
}