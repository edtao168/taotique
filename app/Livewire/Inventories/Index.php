<?php //C:\laragon\www\taotique\app\Livewire\Inventories\Index.php

namespace App\Livewire\Inventories;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Shop;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use WithPagination, Toast;

    public string $search = '';
    public ?int $selectedShop = null;
    public ?int $selectedWarehouse = null;
    public bool $showLowStockOnly = false;	

    // 當搜尋條件改變時，回到第一頁
    public function updated($property)
    {
        if (in_array($property, ['search', 'selectedShop', 'selectedWarehouse', 'showLowStockOnly'])) {
            $this->resetPage();
        }
    }	
	
    public function render()
    {
        $products = Product::query()		
            ->with(['inventories.warehouse.shop'])
            ->when($this->search, function ($q) {
                $q->where(fn($query) => 
                    $query->where('name', 'like', "%{$this->search}%")
                          ->orWhere('sku', 'like', "%{$this->search}%")
                );
            })
            // 處理「僅顯示低庫存」邏輯
            ->when($this->showLowStockOnly, function ($q) {
                // 這邊假設您的 inventories 總量計算邏輯在資料庫層面或 Model 屬性
                // 較簡單的做法是在 Collection 過濾，但若資料量大建議在 query 處理
                $q->whereRaw(' (SELECT SUM(quantity) FROM inventories WHERE product_id = products.id) <= min_stock ');
            })
			->when($this->selectedShop, function ($q) {
                $q->whereHas('inventories.warehouse', fn($w) => $w->where('shop_id', $this->selectedShop));
            })
            ->when($this->selectedWarehouse, function ($q) {
                $q->whereHas('inventories', fn($i) => $i->where('warehouse_id', $this->selectedWarehouse));
            })
            ->orderBy('sku')
            ->paginate(8);

        return view('livewire.inventories.index', [
            'products' => $products,
            'shops' => Shop::all(),
            'warehouses' => Warehouse::when($this->selectedShop, fn($q) => $q->where('shop_id', $this->selectedShop))->get(),
            'headers' => [
                ['key' => 'sku', 'label' => 'SKU', 'class' => 'w-32'],
                ['key' => 'name', 'label' => '商品名稱'],
                ['key' => 'inventory_details', 'label' => '分倉水位 (營業點 > 庫別)'],
                ['key' => 'total_stock', 'label' => '總庫存', 'class' => 'text-right font-bold'],                
			]
		]);
		
        return view('livewire.inventories.index', [
            'products' => $products,
            'shops' => Shop::all(),
            'warehouses' => Warehouse::when($this->selectedShop, fn($q) => $q->where('shop_id', $this->selectedShop))->get(),
            'headers' => $headers
        ]);
    }
}