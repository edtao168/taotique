<?php

namespace App\Livewire\Purchases;

use App\Models\Purchase;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use WithPagination, Toast;

    public string $search = '';
    public array $sortBy = ['column' => 'purchased_at', 'direction' => 'desc'];
	public bool $deleteModal = false;
    public ?Purchase $selectedPurchase = null;
    public bool $shouldSyncInventory = true; // 預設勾選同步扣除

    // 第一步：點擊刪除鈕時觸發
    public function confirmDelete(Purchase $purchase)
    {
        $this->selectedPurchase = $purchase;
        $this->deleteModal = true;
    }

    // 第二步：使用者在 Modal 點擊確認後執行
    public function delete()
    {
        if (!$this->selectedPurchase) return;

        // 如果使用者勾選了同步扣除
        if ($this->shouldSyncInventory) {
            // 找到該採購單對應的所有庫存紀錄並刪除
            Inventory::where('purchase_id', $this->selectedPurchase->id)->delete();
        }

        // 刪除採購單本身
        $this->selectedPurchase->delete();

        $this->deleteModal = false;
        $this->success('採購單已刪除' . ($this->shouldSyncInventory ? '，庫存已同步扣除。' : '。'));
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'purchase_number', 'label' => '單號', 'class' => 'font-semibold'],
            ['key' => 'supplier_name', 'label' => '供應商', 'sortBy' => 'supplier_id'],
            ['key' => 'purchased_at', 'label' => '日期'],
            ['key' => 'total_foreign', 'label' => '外幣總額', 'textAlign' => 'text-right'],
            ['key' => 'total_twd', 'label' => '本幣(TWD)', 'textAlign' => 'text-right'],
            ['key' => 'actions', 'label' => '', 'sortable' => false],
        ];

        $purchases = Purchase::with('supplier')
            ->when($this->search, function ($query) {
                $query->where('purchase_number', 'like', "%{$this->search}%")
                      ->orWhereHas('supplier', fn($q) => $q->where('name', 'like', "%{$this->search}%"));
            })
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(10);

        return view('livewire.purchases.index', [
            'purchases' => $purchases,
            'headers' => $headers
        ]);
    }
}