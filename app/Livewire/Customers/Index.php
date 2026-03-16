<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Illuminate\Database\Eloquent\Builder;

class Index extends Component
{
    use WithPagination, Toast;

    public string $search = '';
    public bool $drawer = false;
	public bool $isReadOnly = false;
    
    // 表單屬性
    public ?Customer $customer = null; // 用於編輯
    public array $formData = [
        'name' => '',
        'phone' => '',
        'wechat' => '',
        'notes' => '',
    ];

    public function showCreate()
    {
        $this->isReadOnly = false;
		$this->reset(['formData', 'customer']);
        $this->drawer = true;
    }

    public function edit(Customer $customer)
    {
        $this->isReadOnly = false;
		$this->customer = $customer;
        $this->formData = $customer->toArray();
        $this->drawer = true;
    }

    public function save()
    {
        $data = $this->validate([
            'formData.name' => 'required|min:2',
            'formData.phone' => 'nullable',
            'formData.wechat' => 'nullable',
            'formData.notes' => 'nullable',
        ]);

        Customer::updateOrCreate(
            ['id' => $this->customer?->id],
            $this->formData
        );

        $this->success('客戶資料已儲存');
        $this->drawer = false;
    }

    public function render()
	{
		$headers = [
			['key' => 'id', 'label' => '#', 'class' => 'w-1'],
			['key' => 'name', 'label' => '客戶姓名'],
			['key' => 'phone', 'label' => '聯絡電話'],
			['key' => 'customer_paid_sum', 'label' => '顧客實付(累計)'],
			['key' => 'actual_received_sum', 'label' => '我方實收(累計)'],
		];

		$customers = Customer::query()
			->withSum('sales as customer_paid_sum', 'customer_total')
			->withSum('sales as actual_received_sum', 'final_net_amount')
			->when($this->search, fn(Builder $q) => $q->where('name', 'like', "%{$this->search}%")
				->orWhere('phone', 'like', "%{$this->search}%"))
			->latest()
			->paginate(10);

		return view('livewire.customers.index', [
			'customers' => $customers,
			'headers' => $headers
		]);
	}
	
	/**
	 * 顯示客戶詳細資料 (查詢模式)
	 */
	public function showDetails($id)
	{
		// 1. 找到該名客戶
		$this->isReadOnly = true;
		$customer = \App\Models\Customer::findOrFail($id);
		
		// 2. 將資料填入 formData 供 Drawer 顯示
		$this->formData = [
			'name'   => $customer->name,
			'phone'  => $customer->phone,
			'wechat' => $customer->wechat,
			'notes' => $customer->notes,
		];

		// 3. 開啟 Drawer (確保變數名稱與 Blade 中的 wire:model="drawer" 一致)
		$this->drawer = true;
	}
}