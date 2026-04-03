<?php

namespace App\Livewire\Purchases;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use WithPagination, Toast;

    public string $search = '';
	public bool $drawer = false;	
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

        DB::transaction(function () {
            if ($this->shouldSyncInventory) {
                // 根據 product_id + warehouse_id 並鎖定採購單關聯進行刪除
                foreach ($this->selectedPurchase->items as $item) {
                    Inventory::where('product_id', $item->product_id)
                        ->where('warehouse_id', $item->warehouse_id)
                        ->where('purchase_item_id', $item->id) // 建議保留此關聯以精確刪除該批次
                        ->delete();
                }
            }

            // 刪除明細與主表 (受資料庫級聯或手動刪除)
            $this->selectedPurchase->items()->delete();
            $this->selectedPurchase->delete();
        });

        $this->deleteModal = false;
        $this->drawer = false;
        $this->selectedPurchase = null;
        $this->success('採購單已刪除，相關庫存已同步更新。');
    }

    public function render()
    {
        $headers = [            
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
	
	/**
     * 顯示採購單詳情
     * 由 Blade 中的 @row-click 或手機端 @click 觸發
     */
    public function showDetail(int $id): void
    {
        // 載入採購單並預載入關聯資料，確保 Drawer 顯示時不會產生 N+1 查詢
        $this->selectedPurchase = Purchase::with(['supplier', 'items.product', 'user'])
            ->findOrFail($id);

        $this->drawer = true;
    }

    /**
     * 重置選中的資料（當關閉 Drawer 時可以選擇性呼叫）
     */
    public function updatedDrawer($value): void
    {
        if (!$value) {
            // 當 Drawer 關閉時，不一定要清除資料，保留可增加流暢感，
            // 但若有安全性考量可在此清除：$this->selectedPurchase = null;
        }
    }
}