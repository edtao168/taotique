<?php

namespace App\Livewire\Settings\Shops;

use App\Models\Shop;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public $search = '';
    public bool $shopModal = false;

    // 簡化後的表單屬性
    public ?Shop $editingShop = null;
    public $name = '';

    public function render()
    {
        // 只保留存在的欄位
        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'name', 'label' => '營業點名稱'],
            ['key' => 'actions', 'label' => '操作', 'sortable' => false, 'class' => 'w-20'],
        ];

        $shops = Shop::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest()
            ->get();

        return view('livewire.settings.shops.index', [
            'shops' => $shops,
            'headers' => $headers,
        ]);
    }

    public function create()
    {
        $this->reset(['editingShop', 'name']);
        $this->shopModal = true;
    }

    public function edit(Shop $shop)
    {
        $this->editingShop = $shop;
        $this->name = $shop->name;
        $this->shopModal = true;
    }

    public function save()
    {
        $data = $this->validate([
            // 這裡加入唯一性檢查，避免資料庫噴 Error
            'name' => 'required|string|max:255|unique:shops,name,' . ($this->editingShop->id ?? 'NULL'),
        ]);

        if ($this->editingShop) {
            $this->editingShop->update($data);
            $this->success('營業點已更新');
        } else {
            Shop::create($data);
            $this->success('營業點已建立');
        }

        $this->shopModal = false;
    }
    
    public function delete(Shop $shop)
    {
        // 因為沒狀態欄位，若要移除只能刪除，但要注意 Warehouse 關聯
        try {
            $shop->delete();
            $this->success('營業點已刪除');
        } catch (\Exception $e) {
            $this->error('無法刪除，可能有倉庫正關聯此營業點。');
        }
    }
}