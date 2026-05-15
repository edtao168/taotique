<?php // app/Livewire/Inventories/Index.php

namespace App\Livewire\Inventories;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Shop;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;

class Index extends Component
{
    use WithPagination, Toast;

    public string $search = '';
    public ?int $selectedShop = null;
    public ?int $selectedWarehouse = null;
    public bool $showLowStockOnly = false;
	public int $perPage = 8;

	// 手機端點擊或滾動觸發
	public function loadMore()
	{
		$this->perPage += 10;
	}
	
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
            // 使用 selectRaw 預先計算總庫存，優化手機端與 PC 端的顯示效能
            ->select('products.*')
            ->selectSub(function ($query) {
                $query->from('inventories')
                    ->selectRaw('SUM(quantity)')
                    ->whereColumn('product_id', 'products.id');
            }, 'total_stock')
            ->when($this->search, function ($q) {
                $q->where(fn($query) => 
                    $query->where('name', 'like', "%{$this->search}%")
                          ->orWhere('sku', 'like', "%{$this->search}%")
                );
            })
            ->when($this->showLowStockOnly, function ($q) {
                $q->havingRaw('total_stock <= min_stock');
            })
            ->when($this->selectedShop, function ($q) {
                $q->whereHas('inventories.warehouse', fn($w) => $w->where('shop_id', $this->selectedShop));
            })
            ->when($this->selectedWarehouse, function ($q) {
                $q->whereHas('inventories', fn($i) => $i->where('warehouse_id', $this->selectedWarehouse));
            })
            ->orderBy('sku')
            ->paginate($this->perPage);

        $headers = [
            ['key' => 'sku', 'label' => 'SKU', 'class' => 'w-32'],
            ['key' => 'name', 'label' => '商品名稱'],
            ['key' => 'inventory_details', 'label' => '分倉水位 (營業點 > 庫別)', 'sortable' => false],
            ['key' => 'total_stock', 'label' => '總庫存', 'class' => 'text-right font-bold'],
        ];

        return view('livewire.inventories.index', [
            'products' => $products,
            'shops' => Shop::all(),
            'warehouses' => Warehouse::when($this->selectedShop, fn($q) => $q->where('shop_id', $this->selectedShop))->get(),
            'headers' => $headers
        ]);
    }
}