<?php // app/Livewire/Conversions/Index.php

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
    
    // 控制 Drawer 顯示與儲存選中資料
    public bool $showDrawer = false;
    public ?Conversion $selectedConversion = null;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    /**
     * 開啟詳情 Drawer
     */
    public function showDetails(int $id)
    {
        $this->selectedConversion = Conversion::with(['items.product', 'user', 'warehouse'])->find($id);
        $this->showDrawer = true;
    }

    public function delete(int $id)
    {
        $conversion = Conversion::find($id);
        if ($conversion) {
            $conversion->delete();
            $this->showDrawer = false;
            $this->success('紀錄已刪除');
        }
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'w-16'],
            ['key' => 'conversion_no', 'label' => '轉換單號', 'class' => 'font-semibold'],
            ['key' => 'process_date', 'label' => '作業日期'],
            ['key' => 'user.name', 'label' => '操作員'],
            ['key' => 'remark', 'label' => '備註', 'sortable' => false],
        ];

        $conversions = Conversion::query()
            ->with(['user'])
            ->where('shop_id', 1) 
            ->when($this->search, function ($query) {
                $query->where('conversion_no', 'like', "%{$this->search}%")
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