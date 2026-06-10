<?php
// 檔案路徑：app/Livewire/Purchases/Returns/ReturnIndex.php

namespace App\Livewire\Purchases\Returns;

use App\Models\PurchaseReturn;
use App\Traits\HasProductSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class ReturnIndex extends Component
{
    use WithPagination, Toast;

    public string $search = '';
    public bool $drawer = false;
    public ?PurchaseReturn $selectedReturn = null;

    /**
     * 觸發詳情抽屜
     */
    public function showDetail($id)
    {
        $this->selectedReturn = PurchaseReturn::with([
            'purchase.supplier', 
            'items.product', 
            'user'
        ])->find($id);
        
        $this->drawer = true;
    }

    /**
     * 執行退貨出庫 + 自動財務過帳（合併為單一操作）
     */
    public function submitReturnPost($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $return = PurchaseReturn::where('id', $id)->lockForUpdate()->firstOrFail();

                if ($return->status !== 'pending') {
                    throw new \Exception('只有「待處理」狀態的退貨單可以執行出庫與過帳。');
                }

                // 狀態推進至 approved（記錄審核資訊）
                $return->approve();
                
                // 立即執行過帳（內含庫存出庫 + 會計分錄）
                $return->post();
            });

            $this->drawer = false;
            $this->selectedReturn = null;
            $this->resetPage();
            $this->success('退貨出庫與財務結轉已完成！');

        } catch (\Throwable $e) {
            $this->error('處理失敗：' . $e->getMessage());
        }
    }

    /**
     * 刪除退貨紀錄 (需注意庫存回滾邏輯)
     */
    public function delete(PurchaseReturn $return)
    {
        if ($return->status === 'completed') {
            $this->error('已完成財務結轉的單據不可刪除。');
            return;
        }

        $return->delete(); 
        $this->drawer = false;
        $this->success('退貨紀錄已刪除，庫存已重新計算');
    }
        
    public function render()
    {
        $returns = PurchaseReturn::with([
            'purchase.supplier',
            'items.product',            
            'user'
        ])
        ->when($this->search, function ($query) {
            $query->where('return_no', 'like', "%{$this->search}%")
                  ->orWhereHas('purchase', function($q) {
                      $q->where('purchase_number', 'like', "%{$this->search}%");
                  })
                  ->orWhereHas('purchase.supplier', function($q) {
                      $q->where('name', 'like', "%{$this->search}%");
                  });
        })
        ->orderBy('created_at', 'desc')
        ->paginate(15);

        $headers = [
            ['key' => 'return_no', 'label' => '退貨單號', 'class' => 'font-mono'],
            ['key' => 'purchase.purchase_number', 'label' => '原採購單號', 'class' => 'font-mono text-xs'],
            ['key' => 'purchase.supplier.name', 'label' => '供應商'],
            ['key' => 'total_return_amount', 'label' => '退款總額', 'textAlign' => 'text-right'],
            ['key' => 'status', 'label' => '狀態', 'class' => 'text-center'],
            ['key' => 'created_at', 'label' => '退貨日期', 'class' => 'w-32'],
        ];

        return view('livewire.purchases.returns.return-index', [
            'returns' => $returns,
            'headers' => $headers
        ]);
    }
}