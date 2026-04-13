<?php

namespace App\Livewire\Purchases;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Traits\HasBarcodeScanner;
use App\Traits\HasProductSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class Create extends Component
{
    use HasBarcodeScanner, HasProductSearch, Toast;

    public ?Purchase $purchase = null;
    public bool $isEdit = false;
	
	public $supplier_id;
    public $purchased_at;
    public $currency = 'CNY';
    public $exchange_rate = '4.5';
    public $remark;
    public array $items = [];
    public array $productOptions = [];

    /**
     * 
     */
	public function mount(?Purchase $purchase = null)
    {
        if ($purchase && $purchase->exists) {
            $this->isEdit = true;
            $this->purchase = $purchase;
            $this->supplier_id = $purchase->supplier_id;
            $this->purchased_at = $purchase->purchased_at->format('Y-m-d');
            $this->currency = $purchase->currency;
            $this->exchange_rate = $purchase->exchange_rate;
            $this->remark = $purchase->remark;
            
            $this->items = $purchase->items->map(fn($item) => [
                'product_id' => $item->product_id,
                'warehouse_id' => $item->warehouse_id,
                'quantity' => (float)$item->quantity,
                'foreign_price' => (float)$item->foreign_price,
            ])->toArray();
        } else {
            $this->purchased_at = now()->format('Y-m-d');
            $this->addRow();
        }
    }
	
	/**
     * 
     */
	public function render()
    {
        return view('livewire.purchases.create', [
            'suppliers' => Supplier::all(),
            'warehouses' => Warehouse::all(),
        ]);
    }
	
	/**
     * 
     */
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
	
	/**
     * 
     */
	public function save()
    {
        $this->validate([
            'supplier_id' => 'required',
            'purchased_at' => 'required|date',
            'items.*.product_id' => 'required',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        DB::transaction(function () {
            if ($this->isEdit) {
                // 1. 修改模式：精確沖銷舊明細對應的庫存
                foreach ($this->purchase->items as $oldItem) {
                    Inventory::where('product_id', $oldItem->product_id)
                        ->where('warehouse_id', $oldItem->warehouse_id)
                        ->where('purchase_item_id', $oldItem->id)
                        ->delete();
                }
                $this->purchase->items()->delete();
                
                $this->purchase->update([
                    'supplier_id' => $this->supplier_id,
                    'exchange_rate' => $this->exchange_rate,
                    'purchased_at' => $this->purchased_at,
                    'remark' => $this->remark,
                ]);
                $target = $this->purchase;
            } else {
                // 2. 新增模式
                $target = Purchase::create([                  
                    'supplier_id' => $this->supplier_id,
                    'user_id' => auth()->id(),
                    'currency' => $this->currency,
                    'exchange_rate' => $this->exchange_rate,
                    'purchased_at' => $this->purchased_at,
                    'remark' => $this->remark,
                    'store_id' => 1,
                ]);
            }

            // 3. 呼叫 Model 層的進貨處理程序 (處理加權平均成本與新庫存寫入)
            $target->processInbound($this->items);
        });

        $this->success($this->isEdit ? '採購單修改完成' : '採購入庫成功', redirectTo: route('purchases.index'));
    }
	
    
    /**
     * 
     */
	public function updated($property, $value)
    {
        if (str_contains($property, 'product_id') && $value) {
            $parts = explode('.', $property);
            $index = $parts[1];
            $this->fillProductData($index, $value, 'items');
        }
    }

	/**
     * 
     */
	public function removeRow($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
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
}