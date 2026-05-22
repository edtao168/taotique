<?php
// app/Livewire/Settings/Accounts/Index.php

namespace App\Livewire\Settings\Accounts;

use App\Models\Account;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public $search = '';
    public $type = '';
    public bool $myModal = false;
    public ?Account $editingItem = null;

    // 表單欄位
    public $code, $name, $type_value, $parent_id, $is_monetary = false, $currency = 'TWD', $is_active = true;

    protected $rules = [
        'code' => 'required|string|max:20|unique:accounts,code',
        'name' => 'required|string|max:100',
        'type_value' => 'required|in:asset,liability,equity,cost,profit',
        'parent_id' => 'nullable|exists:accounts,id',
        'is_monetary' => 'boolean',
        'currency' => 'required_if:is_monetary,true|string|max:3',
        'is_active' => 'boolean',
    ];
	
	/**
     * 新增科目初始化
     */
    public function create()
    {
        $this->reset(['code', 'name', 'type_value', 'parent_id', 'editingItem']);
        $this->is_monetary = false;
        $this->currency = 'TWD';
        $this->is_active = true;
        $this->myModal = true;
    }	

    public function edit(Account $item)
    {
        $this->editingItem = $item;
        $this->code = $item->code;
        $this->name = $item->name;
        $this->type_value = $item->type;
        $this->parent_id = $item->parent_id;
        $this->is_monetary = $item->is_monetary;
        $this->currency = $item->currency ?: 'TWD';
        $this->is_active = $item->is_active;
        $this->myModal = true;
    }

    public function save()
    {
        $rules = $this->rules;
        if ($this->editingItem) {
            $rules['code'] = 'required|string|max:20|unique:accounts,code,' . $this->editingItem->id;
        }
        $data = $this->validate($rules);

        Account::updateOrCreate(
            ['id' => $this->editingItem?->id],
            [
                'code' => $this->code,
                'name' => $this->name,
                'type' => $this->type_value,
                'parent_id' => $this->parent_id ?: null,
                'level' => $this->parent_id ? 2 : 1,
                'is_monetary' => $this->is_monetary,
                'currency' => $this->is_monetary ? $this->currency : '',
                'is_active' => $this->is_active,
                'shop_id' => 1,
            ]
        );

        $this->success($this->editingItem ? '科目已更新' : '新科目已建立');
        $this->myModal = false;
        $this->reset(['code', 'name', 'type_value', 'parent_id', 'is_monetary', 'currency', 'is_active', 'editingItem']);
    }

    public function delete(Account $item)
    {
        if ($item->accountingRuleLines()->exists()) {
            $this->error('此科目正在被過帳規則使用，無法刪除');
            return;
        }
        
        if (Account::where('parent_id', $item->id)->exists()) {
            $this->error('請先刪除子科目');
            return;
        }
        
        $item->delete();
        $this->success('科目已刪除');
    }
    
    public function toggleActive(Account $item)
    {
        $item->update(['is_active' => !$item->is_active]);
        $this->success($item->is_active ? '已啟用' : '已停用');
    }	
	
	public function render()
	{
		// 1. 取得並格式化幣別選項 (修正重點)
		$currencies = config('business.currencies', []);
		$currencyOptions = collect($currencies)->map(function ($config, $code) {
			return [
				'id'   => $code,
				'name' => ($config['name'] ?? $code) . ' (' . ($config['symbol'] ?? '') . ')'
			];
		})->values()->toArray();

		// 2. 格式化科目類型選項
		$typeOptions = collect(Account::typeOptions())->map(fn($label, $id) => [
			'id' => $id,
			'name' => $label
		])->values()->toArray();

		$headers = [
			['key' => 'code', 'label' => '代碼', 'class' => 'w-32 font-mono text-primary'],
			['key' => 'name', 'label' => '科目名稱'],
			['key' => 'type_label', 'label' => '類型', 'class' => 'w-24'],
			['key' => 'currency', 'label' => '幣別', 'class' => 'w-16'],
			['key' => 'status', 'label' => '狀態', 'class' => 'w-20'],
			['key' => 'actions', 'label' => '', 'class' => 'w-20', 'sortable' => false],
		];

		$rows = Account::query()
			->with('parent')
			->when($this->search, fn($q) => $q->where('code', 'like', "%{$this->search}%")
				->orWhere('name', 'like', "%{$this->search}%"))
			->when($this->type, fn($q) => $q->where('type', $this->type))
			->orderBy('code')
			->get();
			
		$parentAccounts = Account::where('level', 1)
			->where('is_active', true)
			->get()
			->map(fn($account) => [
				'id' => $account->id,			
				'name' => "{$account->code} - {$account->name}",
			]);

		return view('livewire.settings.accounts.index', [
			'rows' => $rows,
			'headers' => $headers,
			'typeOptions' => $typeOptions,
			'currencyOptions' => $currencyOptions,
			'parentAccounts' => $parentAccounts,
		]);
	}
}