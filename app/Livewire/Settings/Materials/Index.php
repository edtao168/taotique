<?php

namespace App\Livewire\Settings\Materials;

use App\Models\MaterialDefinition;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public $search = '';
    public bool $myModal = false;
    public ?MaterialDefinition $editingItem = null;

    public $bb_code, $c_code, $name, $market_names;

    // 新增此方法：確保新增時狀態完全清空，避免手機端顯示舊資料
    public function create()
    {
        $this->reset(['bb_code', 'c_code', 'name', 'market_names', 'editingItem']);
        $this->myModal = true;
    }

    public function render()
    {
        $headers = [
            ['key' => 'bb_code', 'label' => '代碼', 'class' => 'w-20 font-mono text-primary'],
            ['key' => 'c_code', 'label' => '細分', 'class' => 'w-20 font-mono text-primary'],
            ['key' => 'name', 'label' => '材質名稱'],
            ['key' => 'market_names', 'label' => '商業名稱'],
            ['key' => 'actions', 'label' => '', 'sortable' => false],
        ];

        $rows = MaterialDefinition::query()
            ->when($this->search, function ($q) {
                return $q->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                          ->orWhere('bb_code', 'like', "%{$this->search}%")
                          ->orWhere('market_names', 'like', "%{$this->search}%");
                });
            })
            ->get();

        return view('livewire.settings.materials.index', [
            'rows' => $rows, 
            'headers' => $headers
        ]);
    }

    public function edit(MaterialDefinition $item)
    {
        $this->editingItem = $item;
        $this->bb_code = $item->bb_code;
        $this->c_code = $item->c_code;
        $this->name = $item->name;
        $this->market_names = $item->market_names;
        $this->myModal = true;
    }

    public function save()
    {
        $data = $this->validate([
            // 修正：代碼唯一性檢查應合併 bb_code 與 c_code 邏輯（視您的業務邏輯而定，此處維持原本針對 bb_code 的檢查並修正語法）
            'bb_code' => 'required|size:2|unique:material_definitions,bb_code,' . ($this->editingItem->id ?? 'NULL'),
            'c_code' => 'required|size:1',
            'name' => 'required',
            'market_names' => 'nullable',
        ]);

        if ($this->editingItem) {
            $this->editingItem->update($data);
            $this->success('材質已更新');
        } else {
            MaterialDefinition::create($data);
            $this->success('材質已新增');
        }

        $this->reset(['bb_code', 'c_code', 'name', 'market_names', 'editingItem', 'myModal']);
    }

    public function delete(MaterialDefinition $item)
    {
        $item->delete();
        $this->success('材質已刪除');
    }
}