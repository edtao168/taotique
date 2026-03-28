<?php

namespace App\Livewire\Settings\Categories;

use App\Models\CategoryDefinition;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public $search = '';
    public bool $myModal = false;
    public ?CategoryDefinition $editingItem = null;

    // 表單欄位
    public $code, $name, $remark;

    public function render()
    {
        $headers = [
            ['key' => 'code', 'label' => '大類', 'class' => 'w-32 font-mono text-primary'],
            ['key' => 'name', 'label' => '類別名稱'],
			['key' => 'remark', 'label' => '備註'],
            ['key' => 'actions', 'label' => '', 'sortable' => false],		
        ];

        $rows = CategoryDefinition::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"))
            ->get();

        return view('livewire.settings.categories.index', [
            'rows' => $rows,
            'headers' => $headers
        ]);
    }

    public function create()
    {
        $this->reset(['code', 'name', 'remark', 'editingItem']); // 強制清空所有欄位與編輯目標
        $this->myModal = true;
    }

    public function edit(CategoryDefinition $item)
    {
        $this->editingItem = $item;
        $this->code = $item->code;
        $this->name = $item->name;
        $this->remark = $item->remark;
        $this->myModal = true;
    }
		
    public function save()
    {
        $data = $this->validate([
            'code' => 'required|size:1|unique:category_definitions,code,' . ($this->editingItem ? $this->editingItem->code : 'NULL') . ',code',
            'name' => 'required',
			'remark' => 'nullable',
        ]);

        if ($this->editingItem) {
            $this->editingItem->update($data);
            $this->success('類別已更新');
        } else {
            CategoryDefinition::create($data);
            $this->success('新類別已建立');
        }

        $this->myModal = false;
        $this->reset(['code', 'name', 'remark', 'editingItem', 'myModal']);
    }

    public function delete(CategoryDefinition $item)
    {
        $item->delete();
        $this->success('類別已刪除');
    }
}