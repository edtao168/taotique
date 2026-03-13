<?php

namespace App\Livewire\Conversions;

use App\Models\Conversion;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use WithPagination, Toast;

    public string $search = '';
    public array $sortBy = ['column' => 'process_date', 'direction' => 'desc'];

    // 搜尋與過濾
    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function delete(Conversion $conversion)
    {
        // 僅刪除單據紀錄，庫存回滾邏輯通常建議另外處理或限制刪除已過帳單據
        $conversion->delete();
        $this->success('紀錄已刪除');
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'w-16'],
            ['key' => 'order_no', 'label' => '轉換單號', 'class' => 'font-semibold'],
            ['key' => 'process_date', 'label' => '作業日期'],
            ['key' => 'user.name', 'label' => '操作員'],
            ['key' => 'remark', 'label' => '備註', 'sortable' => false],
            ['key' => 'created_at', 'label' => '建立時間'],
        ];

        $conversions = Conversion::query()
            ->with(['user', 'items'])
            ->where('store_id', 1) // 初期預設為 1
            ->when($this->search, function ($query) {
                $query->where('order_no', 'like', "%{$this->search}%")
                      ->orWhere('remark', 'like', "%{$this->search}%");
            })
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(10);

        return view('livewire.conversions.index', [
            'conversions' => $conversions,
            'headers' => $headers
        ]);
    }
}