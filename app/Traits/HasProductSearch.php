<?php
// 檔案路徑：app/Traits/HasProductSearch.php
// 僅供多列型單據使用

namespace App\Traits;

use App\Models\Product;

trait HasProductSearch
{
    /**
     * 供 Mary UI x-choices 呼叫
     */
    public function search(string $value = '')
    {
        $results = Product::query()
            ->where('is_active', true)
            ->when($value, function($q) use ($value) {
                $q->where(fn($sub) => 
                    $sub->where('sku', 'like', "%{$value}%")
                        ->orWhere('name', 'like', "%{$value}%")
                );
            })
            ->take(10)
            ->get()
            ->map(fn($p) => [
                'id'   => $p->id,
                'name' => $p->full_display_name, 
            ])
            ->toArray();

        $this->productOptions = $results;
		
        return $results;
    }

    public function fillProductData($index, $productId, $targetArray = 'items')
    {
        if (!$productId) return;
        $product = Product::find($productId);
        if ($product) {
            $this->{$targetArray}[$index]['product_id'] = $product->id;
            // 僅填充金額，不再顯示多餘的名稱輸入框
            if (isset($this->{$targetArray}[$index]['foreign_price']) && empty($this->{$targetArray}[$index]['foreign_price'])) {
                $this->{$targetArray}[$index]['foreign_price'] = $product->last_purchase_price ?? 0;
            }
        }
    }
}