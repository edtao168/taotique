<?php

namespace App\Livewire\Purchases;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\Purchase;
use App\Traits\HasProductSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class Create extends Component
{
    use HasProductSearch, Toast;

    public $supplier_id;
    public $purchased_at;
    public $currency = 'CNY';
    public $exchange_rate = '4.5';
    public $remark;
    public array $items = [];
    public bool $showScanner = false;
    public $scannedBarcode;
    
    // 商品選項列表 - 必須為公開屬性才能傳給視圖
    public array $productOptions = [];

    public function mount()
    {
        $this->purchased_at = now()->format('Y-m-d');
        $this->addRow();
        
        // 初始化時載入商品選項
        $this->productOptions = $this->search();
    }
    
    public function updated($property, $value)
    {
        // 處理 x-choices 選擇商品 (使用 selected_product_id)
        if (str_contains($property, 'selected_product_id') && $value) {
            $parts = explode('.', $property);
            $index = $parts[1];
            
            // 將選中的商品 ID 存入 product_id
            $this->items[$index]['product_id'] = $value;
            $this->fillProductData($index, $value, 'items');
        }
        
        // 處理原本的 product_id 直接選擇
        if (str_contains($property, 'product_id') && $value) {
            $parts = explode('.', $property);
            $index = $parts[1];
            $this->fillProductData($index, $value, 'items');
        }
    }

    // ... 其他方法保持不變（save, addRow, removeRow, handleScannedBarcode, render）
    
    public function save()
    {
        $this->validate([
            'supplier_id' => 'required',
            'purchased_at' => 'required|date',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.foreign_price' => 'required|numeric',
        ]);

        $validItems = array_filter($this->items, fn($item) => !empty($item['product_id']) && $item['quantity'] > 0);
        if (empty($validItems)) {
            $this->error('請至少新增一個有效商品');
            return;
        }

        $purchase = Purchase::create([
            'purchase_number' => 'PO' . now()->format('YmdHis'),
            'supplier_id' => $this->supplier_id,
            'user_id' => auth()->id(),
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate,
            'purchased_at' => $this->purchased_at,
            'remark' => $this->remark,
        ]);

        $purchase->processInbound($validItems);

        $this->success('採購單已入庫，商品成本已自動更新', redirectTo: route('purchases.index'));
    }
    
    public function addRow()
    {
        $this->items[] = [
            'product_id' => null,
            'selected_product_id' => null,
            'name' => '',
            'warehouse_id' => Warehouse::first()?->id ?? 1,
            'quantity' => 1,
            'foreign_price' => 0,
        ];
    }

    public function removeRow($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }
    
    public function handleScannedBarcode(string $barcode)
    {
        $product = Product::where('sku', $barcode)
            ->where('is_active', true)
            ->first();

        if (!$product) {
            $this->error("找不到條碼為 {$barcode} 的商品");
            $this->scannedBarcode = '';
            return;
        }

        foreach ($this->items as $index => $item) {
            if ($item['product_id'] == $product->id) {
                $this->items[$index]['quantity'] = bcadd($this->items[$index]['quantity'], '1', 4);
                $this->success("已增加 {$product->name} 的數量");
                $this->scannedBarcode = '';
                $this->showScanner = false;
                return;
            }
        }

        $this->items[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'warehouse_id' => Warehouse::first()?->id ?? 1,
            'quantity' => 1,
            'foreign_price' => $product->last_purchase_price ?? 0,
        ];

        $this->success("已加入商品：{$product->name}");
        $this->scannedBarcode = '';
        $this->showScanner = false;
    }

    public function render()
    {
        return view('livewire.purchases.create', [
            'suppliers' => Supplier::all(),
            'warehouses' => Warehouse::all(),
			//'productOptions' => $this->search() 
		]);
    }
}