<?php

namespace App\Livewire\Settings\Warehouses;

use App\Models\Warehouse;
use App\Models\Shop;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public $search = '';
    public bool $warehouseModal = false;

    // 表單欄位
    public ?Warehouse $editingWarehouse = null;
    public $shop_id;
    public $name;
    public $is_active = true;

    public function toggleActive(Warehouse $warehouse)
    {
        $warehouse->is_active = !$warehouse->is_active;
        $warehouse->save();

        $status = $warehouse->is_active ? '已啟用' : '已停用';
        $this->success("倉庫 {$warehouse->name} {$status}");
    }
	
	public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'shop.name', 'label' => '所屬店鋪'],
            ['key' => 'name', 'label' => '倉庫名稱'],
            ['key' => 'is_active', 'label' => '狀態', 'class' => 'w-24'],
            ['key' => 'actions', 'label' => '操作', 'sortable' => false, 'class' => 'w-20'],
        ];

        $warehouses = Warehouse::with('shop')
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->get();

        $shops = Shop::all();

        return view('livewire.settings.warehouses.index', [
            'warehouses' => $warehouses,
            'headers' => $headers,
            'shops' => $shops
        ]);
    }

    public function create()
    {
        $this->reset(['editingWarehouse', 'name', 'shop_id', 'is_active']);
        $this->warehouseModal = true;
    }

    public function edit(Warehouse $warehouse)
    {
        $this->editingWarehouse = $warehouse;
        $this->shop_id = $warehouse->shop_id;
        $this->name = $warehouse->name;
        $this->is_active = $warehouse->is_active;
        $this->warehouseModal = true;
    }

    public function save()
    {
        $data = $this->validate([
            'shop_id' => 'required|exists:shops,id',
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        if ($this->editingWarehouse) {
            $this->editingWarehouse->update($data);
            $this->success('倉庫已更新');
        } else {
            Warehouse::create($data);
            $this->success('倉庫已建立');
        }

        $this->warehouseModal = false;
    }
}