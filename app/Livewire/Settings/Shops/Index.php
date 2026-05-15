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

    public ?Shop $editingShop = null;
    public $name = '';

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'name', 'label' => '營業點名稱'],
            ['key' => 'actions', 'label' => '', 'sortable' => false, 'class' => 'w-20'],
        ];

        $shops = Shop::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))            
            ->get();

        return view('livewire.settings.shops.index', [
            'shops' => $shops,
            'headers' => $headers,
        ]);
    }

    // 確保新增時清空所有狀態與編輯目標
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
            'name' => 'required|string|max:255|unique:shops,name,' . ($this->editingShop->id ?? 'NULL'),
        ]);

        if ($this->editingShop) {
            $this->editingShop->update($data);
            $this->success('營業點已更新');
        } else {
            Shop::create($data);
            $this->success('營業點已建立');
        }

        // 儲存成功後重置並關閉
        $this->reset(['editingShop', 'name', 'shopModal']);
    }
    
    public function delete(Shop $shop)
    {
        try {
            $shop->delete();
            $this->success('營業點已刪除');
        } catch (\Exception $e) {
            $this->error('無法刪除，可能有倉庫正關聯此營業點。');
        }
    }
}