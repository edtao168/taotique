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
    public bool $drawer = false; // 統一使用 $drawer
    public bool $isReadOnly = false; // 控制唯讀狀態
	public array $purchaseRecords = []; // 用於儲存詳情頁的採購紀錄

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

    public function create()
    {
        $this->isReadOnly = false;
        $this->reset(['name', 'contact_person', 'phone', 'editingSupplier', 'contact_json', 'notes']);
        $this->drawer = true;
    }

    public function edit(Supplier $supplier)
    {
        $this->isReadOnly = false;
        $this->loadSupplier($supplier);
        $this->drawer = true;   
    }
	
	public function showDetails($id)
    {
        $this->isReadOnly = true;
        $supplier = Supplier::with(['inventories' => function($q) {
			$q->latest()->limit(5); 
		}])->findOrFail($id);
		
		$this->loadSupplier($supplier);

		// 將入庫紀錄轉換為陣列供顯示
		$this->purchaseRecords = $supplier->inventories->map(function($inv) {
			return [
				'product_name' => $inv->product->name ?? '未知商品',
				'quantity'     => $inv->quantity,
				'cost'         => $inv->cost_twd, // 使用換算後的 TWD 成本
				'date'         => $inv->created_at->format('Y-m-d'),
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
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'contact_json' => $this->contact_json,
            'notes' => $this->notes,
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
        // 檢查是否有庫存關聯，避免刪除有資料的供應商
        if ($supplier->inventories()->exists()) {
            $this->error('該供應商已有庫存紀錄，無法刪除');
            return;
        }

        $supplier->delete();
        $this->warning('供應商已移至回收桶');
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'w-16'],
            ['key' => 'name', 'label' => '供應商名稱'],
            ['key' => 'contact_person', 'label' => '聯繫人'],
            ['key' => 'phone', 'label' => '電話'],
            ['key' => 'created_at', 'label' => '建立日期'],
        ];

        return view('livewire.suppliers.index', [
            'suppliers' => Supplier::where('name', 'like', "%{$this->search}%")->paginate(10),
            'headers' => $headers
        ]);
    }
}