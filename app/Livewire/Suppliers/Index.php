<?php

namespace App\Livewire\Suppliers;

use App\Models\Supplier;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use WithPagination, Toast;

    public string $search = '';
    public bool $drawer = false;
    public bool $isReadOnly = false;
    public array $purchaseRecords = [];

    // 表單欄位
    public ?Supplier $editingSupplier = null;
    public string $name = '';
    public string $contact_person = '';
    public string $phone = '';
    public array $contact_json = [
        'wechat' => '',
        'line' => '',
        'other' => ''
    ];
    public string $notes = '';

    /**
     * 新增供應商 (與客戶端 showCreate 邏輯對齊)
     */
    public function showCreate()
    {
        $this->isReadOnly = false;
        $this->reset(['name', 'contact_person', 'phone', 'editingSupplier', 'contact_json', 'notes', 'purchaseRecords']);
        $this->drawer = true;
    }

    /**
     * 編輯供應商
     */
    public function edit(Supplier $supplier)
    {
        $this->isReadOnly = false;
        $this->loadSupplier($supplier);
        $this->drawer = true;   
    }
	
    /**
     * 顯示詳情並載入最近採購紀錄
     */
    public function showDetails($id)
    {
        $this->isReadOnly = true;
        // 預載入最近 5 筆入庫紀錄及其關聯商品
        $supplier = Supplier::with(['inventories' => function($q) {
            $q->with('product')->latest()->limit(5); 
        }])->findOrFail($id);
        
        $this->loadSupplier($supplier);

        // 轉換採購紀錄供前端顯示
        $this->purchaseRecords = $supplier->inventories->map(function($inv) {
            return [
                'product_name' => $inv->product->name ?? '未知商品',
                'quantity'     => $inv->quantity,
                'cost'         => $inv->cost_twd, 
                'date'         => $inv->created_at->format('Y-m-d'),
                'id'           => $inv->id
            ];
        })->toArray();
        
        $this->drawer = true;
    }

    protected function loadSupplier(Supplier $supplier)
    {
        $this->editingSupplier = $supplier;
        $this->name = $supplier->name;
        $this->contact_person = $supplier->contact_person ?? '';
        $this->phone = $supplier->phone ?? '';
        $this->contact_json = array_merge(
            ['wechat' => '', 'line' => '', 'other' => ''], 
            $supplier->contact_json ?? []
        );
        $this->notes = $supplier->notes ?? '';
    }

    public function save()
    {
        if ($this->isReadOnly) return;

        $this->validate([
            'name' => 'required|unique:suppliers,name,' . ($this->editingSupplier->id ?? 'NULL'),
        ]);

        $dbData = [
            'name'           => $this->name,
            'contact_person' => $this->contact_person,
            'phone'          => $this->phone,
            'contact_json'   => $this->contact_json,
            'notes'          => $this->notes,
        ];

        if ($this->editingSupplier) {
            $this->editingSupplier->update($dbData);
            $this->success('供應商已更新');
        } else {
            Supplier::create($dbData);
            $this->success('供應商已創建');
        }

        $this->drawer = false;
    }

    public function delete(Supplier $supplier)
    {
        if ($supplier->inventories()->exists()) {
            $this->error('該供應商已有庫存紀錄，無法刪除');
            return;
        }

        $supplier->delete();
        $this->warning('供應商資料已移除');
    }

    public function render()
	{
		$headers = [
			['key' => 'id', 'label' => '#', 'class' => 'w-16'],
			['key' => 'name', 'label' => '供應商名稱'],
			['key' => 'contact_person', 'label' => '聯繫人'],
			['key' => 'phone', 'label' => '電話'],
			['key' => 'inventories_count', 'label' => '入庫次數', 'class' => 'text-right'],
			// 這裡的 key 要對應下方 withSum 產生的名稱
			['key' => 'inventories_sum_cost', 'label' => '累計採購 (TWD)', 'class' => 'text-right text-red-500'],
		];

		$suppliers = Supplier::query()
			->when($this->search, function($q) {
				$q->where('name', 'like', "%{$this->search}%")
				  ->orWhere('phone', 'like', "%{$this->search}%");
			})
			->withCount('inventories')
			// 修正處：請確保 'cost' 是 inventories 表中真實的欄位名
			->withSum('inventories', 'cost') 
			->orderBy('id')
			->paginate(10);

		return view('livewire.suppliers.index', [
			'suppliers' => $suppliers,
			'headers'   => $headers
		]);
	}
}