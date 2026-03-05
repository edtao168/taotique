<?php

namespace App\Livewire\Products;

use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use WithPagination, Toast;

    public string $search = '';
    public bool $drawer = false; // 控制查詢抽屜
    public ?Product $selectedProduct = null;

    public function render()
    {
        return view('livewire.products.index', [
            'products' => Product::query()
                ->inCurrentShop()
				->with(['images'])
                ->with(['inventories'])
                ->when($this->search, function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('sku', 'like', "%{$this->search}%");
                })
                ->orderBy('sku')
                ->paginate(9),
            'headers' => [
                ['key' => 'sku', 'label' => 'SKU', 'class' => 'w-32'],
				['key' => 'image', 'label' => '預覽', 'class' => 'w-20', 'sortable' => false],
                ['key' => 'name', 'label' => '品名'],
                ['key' => 'price', 'label' => '零售價', 'class' => 'text-right'],
                ['key' => 'total_stock', 'label' => '庫存', 'class' => 'text-center'],
            ]    
        ]);
    }
	
	/**
     * 開啟快速查詢抽屜
     */
    public function showDetail(Product $product)
    {
        $this->selectedProduct = Product::with('images')->find($product->id);
        $this->drawer = true;
    }

    /**
     * 刪除商品邏輯
     */
    public function delete(Product $product)
    {
        if ($product->saleItems()->exists()) {
            $this->error("此商品已有銷售紀錄，無法刪除。");
            return;
        }
        
        $product->delete();
        $this->success("商品 [{$product->sku}] 已移除");
    }
    
}