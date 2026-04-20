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

	/**
	 * 參考銷售單模式：直接執行刪除（由前端 wire:confirm 保護）
	 */
	public function delete($id)
	{
		$purchase = Purchase::with('items')->find($id);
		
		if (!$purchase) {
			$this->error('找不到該單據，可能已被刪除。');
			return;
		}
		
		if ($purchase->hasReturnRecords()) {
			$this->error('此採購單已有退貨紀錄，禁止刪除。');
			return;
		}

		try {
			DB::transaction(function () use ($purchase) {
				if ($this->shouldSyncInventory) {
					foreach ($purchase->items as $item) {
						$inventory = Inventory::where('product_id', $item->product_id)
							->where('warehouse_id', $item->warehouse_id)
							->lockForUpdate()
							->first();

						if ($inventory) {
							$newQty = bcsub($inventory->quantity, $item->quantity, 4);
							
							if (bccomp($newQty, '0', 4) <= 0) {
								$inventory->delete();
							} else {
								$inventory->update(['quantity' => $newQty]);
							}
						}
					}
				}
				
				$purchase->delete();
			});

			$this->selectedPurchase = null;
			$this->drawer = false;
			$this->success('採購單已刪除');
			
		} catch (\Exception $e) {
			$this->error('刪除失敗：' . $e->getMessage());
		}
	}
	
	/**
     * 顯示採購單詳情
     * 由 Blade 中的 @row-click 或手機端 @click 觸發
     */
    public function showDetail(int $id): void
    {
        $purchase = Purchase::with(['supplier', 'items.product', 'items.warehouse', 'user'])
			->find($id);

		if (!$purchase) {
			$this->error('找不到該單據，可能已被刪除。');
			return;
		}

		$this->selectedPurchase = $purchase;
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
}