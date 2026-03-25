<?php

namespace App\Livewire\Inventories;

use App\Models\InventoryMovement;
use Livewire\Component;
use Livewire\WithPagination;

class Movements extends Component
{
    use WithPagination;

    public string $search = '';
    public ?string $type = null;
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';
    
    // 加入這個屬性來控制載入數量
    public int $perPage = 10;

    // 排序方法
    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }
    
    // 新增 loadMore 方法
    public function loadMore()
    {
        $this->perPage += 10;
    }
    
    // 更新 render 方法中的 paginate 為使用 perPage
    public function render()
    {
        $query = InventoryMovement::query()
            ->with(['product', 'warehouse.shop', 'user'])
            ->when($this->search, function ($q) {
                $q->whereHas('product', fn($p) => $p->where('sku', 'like', "%{$this->search}%")
                    ->orWhere('name', 'like', "%{$this->search}%"));
            })
            ->when($this->type, fn($q) => $q->where('type', $this->type))
            ->orderBy($this->sortField, $this->sortDirection);
        
        $movements = $query->paginate($this->perPage);

        $headers = [
            ['key' => 'created_at', 'label' => '時間', 'class' => 'w-40'],
            ['key' => 'product.sku', 'label' => 'SKU'],
            ['key' => 'warehouse.name', 'label' => '倉庫/店別'],
            ['key' => 'type_label', 'label' => '異動類型'],
            ['key' => 'quantity', 'label' => '異動量', 'class' => 'text-right font-bold'],
            ['key' => 'remark', 'label' => '備註'],
            ['key' => 'user.name', 'label' => '操作人'],
        ];

        return view('livewire.inventories.movements', [
            'movements' => $movements,
            'headers' => $headers,
            'types' => [
                ['id' => 'transfer', 'name' => '調撥'],
                ['id' => 'stocktake', 'name' => '盤點'],
                ['id' => 'sale', 'name' => '銷售'],
                ['id' => 'purchase', 'name' => '採購'],
            ]
        ]);
    }
}