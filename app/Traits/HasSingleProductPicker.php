<?php
// 檔案路徑：app/Traits/HasSingleProductPicker.php
// 單選組件專用

namespace App\Traits;

use App\Models\Product;

trait HasSingleProductPicker
{
    use HasProductSearch;

    /**
     * 單選商品：選擇商品
     */
    public function fillProduct(int $productId): void
	{
		$product = Product::find($productId);
		if ($product) {
            $this->product_id = $product->id;
            $this->product_name = $product->full_display_name;
            
            $this->productSearch = '';
            $this->productOptions = [];
            // ✅ 直接呼叫 Livewire 的 updated 魔術方法
            //    前提：使用此 Trait 的元件必須有 updatedProductId() 方法
            if (method_exists($this, 'updatedProductId')) {
                $this->updatedProductId($product->id);
            }
        }
	}

    /**
     * 單選商品：清除/重置選取
     */
    public function resetProduct(): void
    {
        if (property_exists($this, 'product_id')) {
            $this->product_id = null;
        }
        if (property_exists($this, 'product_name')) {
            $this->product_name = '';
        }
        $this->productSearch = '';
        $this->refreshProductOptions();
    }
}