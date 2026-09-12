<?php
// 檔案路徑：app/Traits/HasProductSearch.php
// 僅負責搜尋邏輯

namespace App\Traits;

use App\Models\Product;

trait HasProductSearch
{
    public string $productSearch = '';
    public array $productOptions = [];

    public function updatedProductSearch(): void
    {
        $this->refreshProductOptions();
    }

    public function refreshProductOptions(): void
    {
        $keyword = trim($this->productSearch);

        $query = Product::query()
            ->where('is_active', true)
            ->orderBy('sku')
            ->take(15);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('sku', 'like', "%{$keyword}%")
                  ->orWhere('name', 'like', "%{$keyword}%");
            });
        }

        $this->productOptions = $query->get()
            ->map(fn($p) => [
                'id'   => $p->id,
                'name' => $p->full_display_name,
            ])
            ->toArray();
    }
}