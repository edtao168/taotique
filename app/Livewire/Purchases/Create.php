<?php

namespace App\Livewire\Purchases;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\Purchase;
use App\Traits\HasBarcodeScanner;
use App\Traits\HasProductSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class Create extends Component
{
    use HasBarcodeScanner, HasProductSearch, Toast;

    public $supplier_id;
    public $purchased_at;
    public $currency = 'CNY';
    public $exchange_rate = '4.5';
    public $remark;
    public array $items = [];
    public array $productOptions = [];

    public function mount()
    {
        $this->purchased_at = now()->format('Y-m-d');
        $this->addRow();
        $this->productOptions = $this->search();
    }
    
    public function updated($property, $value)
    {
        if (str_contains($property, 'product_id') && $value) {
            $parts = explode('.', $property);
            $index = $parts[1];
            $this->fillProductData($index, $value, 'items');
        }
    }

    /**
     * 🔧 實現掃描回調（必須實現抽象方法）
     */
    public function onBarcodeScanned(string $barcode, ?int $index = null): void
    {
        $product = $this->findProductByBarcode($barcode);
        
        if (!$product) {
            $this->error("找不到條碼為 {$barcode} 的商品");
            return;
        }

        // 如果有指定行索引，填入該行
        if ($index !== null && isset($this->items[$index])) {
            $this->items[$index]['product_id'] = $product->id;
            $this->items[$index]['name'] = $product->name;
            $this->items[$index]['foreign_price'] = $product->last_purchase_price ?? 0;
            $this->success("已選擇商品：{$product->name}");
            $this->productOptions = $this->search();
            return;
        }

        // 檢查是否已存在於 items 中（自動增加數量）
        foreach ($this->items as $i => $item) {
            if ($item['product_id'] == $product->id) {
                $this->items[$i]['quantity'] = bcadd($this->items[$i]['quantity'], '1', 4);
                $this->success("已增加 {$product->name} 的數量");
                return;
            }
        }

        // 新增一行
        $this->items[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'warehouse_id' => Warehouse::first()?->id ?? 1,
            'quantity' => 1,
            'foreign_price' => $product->last_purchase_price ?? 0,
        ];

        $this->success("已加入商品：{$product->name}");
    }

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

    public function render()
    {
        return view('livewire.purchases.create', [
            'suppliers' => Supplier::all(),
            'warehouses' => Warehouse::all(),
        ]);
    }
}