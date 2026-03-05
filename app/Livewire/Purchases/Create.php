<?php

namespace App\Livewire\Purchases;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\Purchase;
use App\Traits\HasProductSearch;
use Livewire\Component;
use Mary\Traits\Toast;

class Create extends Component
{
    use HasProductSearch, Toast;

    // 主表欄位
    public $supplier_id;
    public $purchased_at;
    public $currency = 'CNY';
    public $exchange_rate = '4.5'; // 預設匯率，實務上可由 API 或設定抓取
    public $remark;

    // 明細行數據
    public array $items = [];
	
	public bool $showSupplierModal = false; // 控制彈窗
	public string $newSupplierName = '';

    // 初始載入
	public function mount()
    {
        $this->purchase = new Purchase();
		$this->purchased_at = now()->format('Y-m-d');
        $this->addRow(); // 初始化第一行
    }

    
    // 當 items.0.product_id 改變時自動觸發
    public function updated($property, $value)
    {
        if (str_contains($property, 'product_id')) {
            $index = explode('.', $property)[1];
            $this->fillProductData($index, $value, 'items');
        }
    }

    public function save()
    {
        $this->validate([
            'supplier_id' => 'required',
            'purchased_at' => 'required|date',
            'items.*.product_id' => 'required',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.foreign_price' => 'required|numeric',
        ]);

        // 1. 建立採購主紀錄
        $purchase = Purchase::create([
            'purchase_number' => 'PO' . now()->format('YmdHis'),
            'supplier_id' => $this->supplier_id,
            'user_id' => auth()->id(),
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate,
            'purchased_at' => $this->purchased_at,
            'remark' => $this->remark,
        ]);

        // 2. 調用厚 Model 邏輯進行過帳 (明細、庫存、加權成本更新)
        $purchase->processInbound($this->items);

        $this->success('採購單已入庫，商品成本已自動更新', redirectTo: route('purchases.index'));
    }

    
	// 快速新增供應商方法
	public function saveSupplier()
	{
		$this->validate(['newSupplierName' => 'required|unique:suppliers,name']);
		
		$supplier = \App\Models\Supplier::create([
			'name' => $this->newSupplierName,
			// 其他欄位留空，事後再去補齊即可
		]);

		$this->supplier_id = $supplier->id; // 自動選中新供應商
		$this->showSupplierModal = false;
		$this->newSupplierName = '';
		$this->success('供應商已建立並自動選取');
	}
	
	// 新增明細行
    public function addRow()
    {
        $this->items[] = [
            'product_id' => null,
            'warehouse_id' => 1, // 預設主倉
            'quantity' => 1,
            'foreign_price' => '0',
            'cost_twd' => '0', // 預覽用
        ];
    }

    // 移除明細行
    public function removeRow($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

	public function render()
    {
        return view('livewire.purchases.create', [
            'suppliers' => Supplier::all(),
            'products' => Product::where('is_active', true)->get(),
            'warehouses' => Warehouse::all(),
        ]);
    }
	
}