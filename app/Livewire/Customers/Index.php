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
    public bool $customerDrawer = false;
    
    // 表單屬性
    public ?Customer $customer = null; // 用於編輯
    public array $formData = [
        'name' => '',
        'phone' => '',
        'wechat' => '',
        'remark' => '',
    ];

    public function showCreate()
    {
        $this->reset(['formData', 'customer']);
        $this->customerDrawer = true;
    }

    public function edit(Customer $customer)
    {
        $this->customer = $customer;
        $this->formData = $customer->toArray();
        $this->customerDrawer = true;
    }

    public function save()
    {
        $data = $this->validate([
            'formData.name' => 'required|min:2',
            'formData.phone' => 'nullable',
            'formData.wechat' => 'nullable',
            'formData.remark' => 'nullable',
        ]);

        Customer::updateOrCreate(
            ['id' => $this->customer?->id],
            $this->formData
        );

        $this->success('客戶資料已儲存');
        $this->customerDrawer = false;
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
}