<?php
// 檔案路徑：app/Traits/HasProductSearch.php

namespace App\Traits;

use App\Models\Product;

trait HasProductSearch
{
    /**
     * 使用者輸入的搜尋關鍵字（由 wire:model.live.debounce 綁定）
     */
    public string $productSearch = '';

    /**
     * 搜尋結果選項（給下拉選單用）
     */
    public array $productOptions = [];

    /**
     * 監聽 productSearch 變化，重新查詢
     */
    public function updatedProductSearch(): void
    {
        $this->refreshProductOptions();
    }

    /**
     * 重新查詢商品選項（name / sku 模糊搜尋，以 sku 排序）
     */
    public function refreshProductOptions(): void
    {
        $keyword = trim($this->productSearch);

        $this->productOptions = Product::query()
            ->where('is_active', true)
            ->when($keyword, function ($q) use ($keyword) {
                $q->where(fn($sub) =>
                    $sub->where('sku', 'like', "%{$keyword}%")
                        ->orWhere('name', 'like', "%{$keyword}%")
                );
            })
            ->orderBy('sku')          // ← 改成以 sku 排序
            ->take(15)
            ->get()
            ->map(fn($p) => [
                'id'   => $p->id,
                'name' => $p->full_display_name,
            ])
            ->toArray();
    }

    /**
     * 選定商品後，填充名稱等資料
     */
    public function fillProductData($index, $productId, $targetArray = 'items')
    {
        if (!$productId) return;
        $product = Product::find($productId);
        if ($product) {
            $this->{$targetArray}[$index]['product_id'] = $product->id;
            $this->{$targetArray}[$index]['name'] = $product->name;
            $currentPrice = $this->{$targetArray}[$index]['price'] ?? 0;
            if (empty($currentPrice) || $currentPrice == 0) {
                $this->{$targetArray}[$index]['price'] = $product->price ?? 0;
            }
        }
    }
}