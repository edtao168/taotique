<?php

namespace App\Livewire\Purchases;

use App\Enums\WorkflowStatus;
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
    public bool $shouldSyncInventory = true;

    // ============================================
    // 審核方法（一人店使用）
    // ============================================
    
    public function approvePurchase(int $purchaseId)
    {
        try {
            $purchase = Purchase::findOrFail($purchaseId);
            
            if ($purchase->status !== WorkflowStatus::DRAFT) {
                throw new \Exception("目前狀態為「{$purchase->status_label}」，無法審核。僅「草稿」可審核。");
            }
            
            $purchase->approve(auth()->user());
            
            $this->selectedPurchase = $purchase->fresh(['shop', 'supplier', 'items.product', 'user']);
            $this->success("✅ 採購單 {$purchase->purchase_number} 已審核通過！");
        } catch (\Exception $e) {
            $this->error('審核失敗：' . $e->getMessage());
        }
    }

    // ============================================
    // 入庫方法
    // ============================================

    public function processStockIn(Purchase $purchase)
    {        
        try {
            // processInbound 內部會處理 workflow 轉換
            $purchase->processInbound();
            
            $this->drawer = false;
            $this->selectedPurchase = null;
            
            $this->success("採購單 {$purchase->purchase_number} 已成功入庫並完成會計結轉");
        } catch (\Exception $e) {
            $this->error('入庫失敗：' . $e->getMessage());
        }
    }

    // ============================================
    // 取消方法
    // ============================================
    
    public function cancelPurchase(int $purchaseId)
    {
        try {
            $purchase = Purchase::findOrFail($purchaseId);
            $purchase->cancel(auth()->user());
            
            $this->selectedPurchase = $purchase->fresh(['shop', 'supplier', 'items.product', 'user']);
            $this->success("✅ 採購單 {$purchase->purchase_number} 已取消！");
        } catch (\Exception $e) {
            $this->error('取消失敗：' . $e->getMessage());
        }
    }

    // ============================================
    // 刪除方法
    // ============================================

    public function delete($id)
    {
        $purchase = Purchase::with('items')->find($id);
        
        if (!$purchase) {
            $this->error('找不到該單據，可能已被刪除。');
            return;
        }
        
        // ✅ 使用 workflow 判斷是否可刪除
        if (!$purchase->isDeletable()) {
            $this->error('此採購單狀態為「' . $purchase->status_label . '」，無法刪除。');
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
                        $inventory = Inventory::where('shop_id', $purchase->shop_id ?? 1)
                            ->where('product_id', $item->product_id)
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
            $this->success('採購單已刪除，庫存已同步回滾。');
            
        } catch (\Exception $e) {
            $this->error('刪除失敗：' . $e->getMessage());
        }
    }

    // ============================================
    // 顯示詳情
    // ============================================

    public function showDetail(int $id): void
    {
        $purchase = Purchase::with(['shop', 'supplier', 'items.product', 'items.warehouse', 'user'])
            ->find($id);

        if (!$purchase) {
            $this->error('找不到該單據，可能已被刪除。');
            return;
        }

        $this->selectedPurchase = $purchase;
        $this->drawer = true;
    }

    public function updatedDrawer($value): void
    {
        if (!$value) {
            // 保留資料增加流暢感
        }
    }

    // ============================================
    // 計算屬性（供 Blade 判斷按鈕顯示）
    // ============================================

    public function getCanApproveProperty(): bool
    {
        if (!$this->selectedPurchase) {
            return false;
        }
        return $this->selectedPurchase->status === WorkflowStatus::DRAFT;
    }

    public function getCanStockInProperty(): bool
    {
        if (!$this->selectedPurchase) {
            return false;
        }
        return $this->selectedPurchase->status === WorkflowStatus::APPROVED 
            && !$this->selectedPurchase->stocked_in_at;
    }

    public function getCanCancelProperty(): bool
    {
        if (!$this->selectedPurchase) {
            return false;
        }
        return in_array($this->selectedPurchase->status, [WorkflowStatus::DRAFT, WorkflowStatus::APPROVED])
            && !$this->selectedPurchase->stocked_in_at
            && !$this->selectedPurchase->hasReturnRecords();
    }

    // ============================================
    // render
    // ============================================

    public function render()
    {
        $headers = [            
			['key' => 'purchase_number', 'label' => '單號'],
			['key' => 'status', 'label' => '狀態', 'class' => 'w-32'],
			['key' => 'shop.name', 'label' => '分店'],
			['key' => 'warehouse.name', 'label' => '歸屬倉庫'],
			['key' => 'supplier.name', 'label' => '供應商'],
			['key' => 'purchased_at', 'label' => '日期'],
			['key' => 'total_amount', 'label' => '原幣總額', 'textAlign' => 'text-right'],
			['key' => 'total_base', 'label' => '功能幣別', 'textAlign' => 'text-right'],
			['key' => 'actions', 'label' => '', 'sortable' => false],
		];

        $purchases = Purchase::with('shop', 'supplier', 'warehouse')
            ->when($this->search, function ($query) {
                $query->where('purchase_number', 'like', "%{$this->search}%")
                      ->orWhereHas('supplier', fn($q) => $q->where('name', 'like', "%{$this->search}%"));
            })
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(10);

        return view('livewire.purchases.index', [
            'purchases' => $purchases,
            'headers' => $headers,
            'canApprove' => $this->canApprove,
            'canStockIn' => $this->canStockIn,
            'canCancel' => $this->canCancel,
        ]);
    }
}