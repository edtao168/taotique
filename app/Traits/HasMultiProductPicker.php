<?php
// app/Traits/HasMultiProductPicker.php

namespace App\Traits;

use App\Models\Product;

trait HasMultiProductPicker
{
    use HasProductSearch;

    /** 當前聚焦的明細行索引 */
    public ?int $activeRowIndex = null;

    public function setActiveRow(int $index): void
    {
        if ($this->activeRowIndex !== $index) {
            $this->activeRowIndex = $index;
            $this->productSearch = '';
            $this->productOptions = [];
        }
    }

    public function fillProductForRow(int $index, int $productId): void
    {
        if (!isset($this->items[$index])) {
            return;
        }

        $product = Product::find($productId);
        if (!$product) {
            $this->error("找不到商品 ID: {$productId}");
            return;
        }

        $this->items[$index]['product_id'] = $product->id;
        $this->items[$index]['name']       = $product->full_display_name;
        $this->items[$index]['sku']        = $product->sku;

        $this->applyProductPricing($this->items[$index], $product);

        $this->productSearch   = '';
        $this->productOptions  = [];
        $this->activeRowIndex  = null;

        // ✅ 可選鉤子：若子類別有 calculateAll() 就呼叫
        if (method_exists($this, 'calculateAll')) {
            $this->calculateAll();
        }
    }

    public function resetProductForRow(int $index): void
    {
        if (!isset($this->items[$index])) {
            return;
        }

        $this->items[$index]['product_id'] = null;
        $this->items[$index]['name']       = '';
        $this->items[$index]['sku']        = '';

        $this->clearProductPricing($this->items[$index]);

        $this->productSearch   = '';
        $this->productOptions  = [];
        $this->activeRowIndex  = null;

        if (method_exists($this, 'calculateAll')) {
            $this->calculateAll();
        }
    }

    /**
     * 子類別必須實作：商品選定後，價格欄位如何填寫
     */
    abstract protected function applyProductPricing(array &$row, Product $product): void;

    /**
     * 子類別可選覆寫：清除商品後，價格欄位如何歸零
     * 預設：清空 price 與 subtotal
     */
    protected function clearProductPricing(array &$row): void
    {
        $row['price']    = '0.0000';
        $row['subtotal'] = '0.0000';
    }
}