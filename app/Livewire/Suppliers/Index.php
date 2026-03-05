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
    public bool $supplierModal = false;

    // 表單欄位
    public ?Supplier $editingSupplier = null;
    public string $name = '';
    public string $contact_person = '';
    public string $phone = '';
	public array $contact_json = [];
	public string $notes = '';

    public function create()
    {
        $this->reset(['name', 'contact_person', 'phone', 'editingSupplier']);
        $this->supplierModal = true;
    }

    public function edit(Supplier $supplier)
    {
        $this->editingSupplier = $supplier;
        $this->name = $supplier->name;
        $this->contact_person = $supplier->contact_person??'';
        $this->phone = $supplier->phone??'';
		$this->contact_json = array_merge(
			['wechat' => '', 'line' => '', 'other' => ''], 
			$supplier->contact_json ?? []
		);
		$this->notes = $supplier->notes ?? '';		
		$this->supplierModal = true;        
    }

    public function save()
    {
        $data = $this->validate([
			'name' => 'required|unique:suppliers,name,' . ($this->editingSupplier->id ?? 'NULL'),
			'contact_person' => 'nullable',
			'phone' => 'nullable',
			'contact_json' => 'nullable|array',
			'notes' => 'nullable',
		]);

        if ($this->editingSupplier) {
            $this->editingSupplier->update($data);
            $this->success('供應商已更新');
        } else {
            Supplier::create($data);
            $this->success('供應商已創建');
        }

        $this->supplierModal = false;
    }

    public function delete(Supplier $supplier)
    {
        // 檢查是否有庫存關聯，避免刪除有資料的供應商
        if ($supplier->inventories()->exists()) {
            $this->error('該供應商已有庫存紀錄，無法刪除');
            return;
        }

        $supplier->delete();
        $this->warning('供應商已刪除');
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