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
    
    // 掃描相關
    public bool $showCameraScanner = false;  // 相機掃描 Modal
    public bool $showManualInput = false;    // 手動輸入 Modal（保留掃碼槍功能）
    public $scannedBarcode;
    public ?int $currentScanIndex = null;    // 當前掃描的行索引
    
    // 商品選項列表
    public array $productOptions = [];

    public function mount()
    {
        $this->purchased_at = now()->format('Y-m-d');
        $this->addRow();
        $this->productOptions = $this->search();
    }
    
    public function updated($property, $value)
    {
        if (str_contains($property, 'selected_product_id') && $value) {
            $parts = explode('.', $property);
            $index = $parts[1];
            $this->items[$index]['product_id'] = $value;
            $this->fillProductData($index, $value, 'items');
        }
        
        if (str_contains($property, 'product_id') && $value) {
            $parts = explode('.', $property);
            $index = $parts[1];
            $this->fillProductData($index, $value, 'items');
        }
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
    
    // 🔧 開啟相機掃描（手機相機）
    public function openCameraScanner($index)
    {
        $this->currentScanIndex = $index;
        $this->showCameraScanner = true;
        $this->dispatch('start-camera-scan');
    }
    
    // 🔧 開啟手動輸入（掃碼槍/手動輸入）
    public function openManualInput($index)
    {
        $this->currentScanIndex = $index;
        $this->showManualInput = true;
        $this->scannedBarcode = '';
        $this->dispatch('focus-manual-input');
    }

    // 🔧 處理掃描結果（來自前端相機掃描）
    public function handleCameraScan($barcode)
    {
        $this->processBarcode($barcode);
        $this->showCameraScanner = false;
    }
    
    // 🔧 處理手動輸入（掃碼槍或鍵盤輸入）
    public function handleManualInput()
    {
        $barcode = trim($this->scannedBarcode);
        if (empty($barcode)) {
            $this->error('請輸入條碼');
            return;
        }
        
        $this->processBarcode($barcode);
        $this->showManualInput = false;
        $this->scannedBarcode = '';
    }

    // 🔧 統一處理條碼邏輯
    private function processBarcode($barcode)
    {
        $product = Product::where('sku', $barcode)
            ->where('is_active', true)
            ->first();

        if (!$product) {
            $this->error("找不到條碼為 {$barcode} 的商品");
            return;
        }

        // 如果有指定行索引，填入該行
        if ($this->currentScanIndex !== null && isset($this->items[$this->currentScanIndex])) {
            $this->items[$this->currentScanIndex]['product_id'] = $product->id;
            $this->items[$this->currentScanIndex]['name'] = $product->name;
            $this->items[$this->currentScanIndex]['foreign_price'] = $product->last_purchase_price ?? 0;
            
            $this->success("已選擇商品：{$product->name}");
            $this->productOptions = $this->search();
            $this->currentScanIndex = null;
            return;
        }

        // 檢查是否已存在於 items 中（自動增加數量）
        foreach ($this->items as $index => $item) {
            if ($item['product_id'] == $product->id) {
                $this->items[$index]['quantity'] = bcadd($this->items[$index]['quantity'], '1', 4);
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

    public function render()
    {
        return view('livewire.purchases.create', [
            'suppliers' => Supplier::all(),
            'warehouses' => Warehouse::all(),
        ]);
    }
}