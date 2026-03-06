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
		// 1. 先處理查詢與分頁 (將結果存入變數，不要在這裡直接 return)
		$products = Product::query()
			->with('images')
			->when($this->search, function($q) {
				$q->where('name', 'like', "%{$this->search}%")
				  ->orWhere('sku', 'like', "%{$this->search}%");
			})
			->paginate(9);

		// 2. 定義表頭
		$headers = [
			['key' => 'id', 'label' => '#', 'class' => 'w-1'],
			['key' => 'image', 'label' => '圖片', 'class' => 'w-16', 'sortable' => false],
			['key' => 'sku', 'label' => '商品SKU'],
			['key' => 'name', 'label' => '商品名稱'],
		];

		// 2. 插入成本：如果身分是 Owner，先將成本加入陣列
		if (auth()->user()->role === 'owner') {
			$headers[] = ['key' => 'cost', 'label' => '平均成本', 'class' => 'text-error font-bold'];
		}

		// 3. 接著加入售價與庫存
		$headers[] = ['key' => 'price', 'label' => '售價'];
		$headers[] = ['key' => 'total_stock', 'label' => '當前庫存', 'class' => 'w-24'];

		// 4. 最後才執行 return view
		return view('livewire.products.index', [
			'products' => $products,
			'headers' => $headers,
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