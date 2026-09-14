<?php
// 檔案路徑：app/Traits/HasMultiProductPicker.php
// 多選組件專用

namespace App\Traits;

use App\Models\Product;

trait HasMultiProductPicker
{
    use HasProductSearch;

    /** 當前聚焦的明細行索引 */
    public ?int $activeRowIndex = null;

    /**
     * 設定當前聚焦行（由 product-picker 的 @focus 觸發）
     */
    public function setActiveRow(int $index): void
    {
        if ($this->activeRowIndex !== $index) {
            $this->activeRowIndex = $index;
            $this->productSearch = '';
            $this->productOptions = [];
        }
    }

    /**
     * 為指定行填充商品資訊
     */
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

        // 讓子類別決定「價格欄位怎麼填」（銷售填 price、採購填 cost）
        $this->applyProductPricing($this->items[$index], $product);

        $this->productSearch   = '';
        $this->productOptions  = [];
        $this->activeRowIndex  = null;

        $this->calculateAll();
    }

    /**
     * 清除指定行的商品選擇
     */
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

        $this->calculateAll();
    }

    /**
     * 子類別必須實作：商品選定後，價格欄位如何填寫
     * 
     * 銷售單：$row['price'] = $product->price
     * 採購單：$row['price'] = $product->cost
     */
    abstract protected function applyProductPricing(array &$row, Product $product): void;

    /**
     * 子類別必須實作：清除商品後，價格欄位如何歸零
     */
    abstract protected function clearProductPricing(array &$row): void;

    /**
     * 子類別必須實作：重算總額
     */
    abstract protected function calculateAll(): void;
}