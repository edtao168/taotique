<?php
// 檔案路徑：app/Livewire/Sales/Returns/ReturnIndex.php

namespace App\Livewire\Sales\Returns;

use App\Models\SalesReturn;
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
    public ?SalesReturn $selectedReturn = null;

    /**
     * 觸發詳情抽屜
     */
    public function showDetail($id)
    {
        $this->selectedReturn = SalesReturn::with([
            'sale.customer', 
            'items.product', 
            'fees',
            'user'
        ])->find($id);

        $this->drawer = true;
    }

	/**
	 * 執行退貨入庫 + 自動財務過帳（合併為單一操作）
	 */
	public function submitReturnPost($id)
	{
		try {
			DB::transaction(function () use ($id) {
				$return = SalesReturn::where('id', $id)->lockForUpdate()->firstOrFail();

				if ($return->status !== 'pending') {
					throw new \Exception('只有「待處理」狀態的退貨單可以執行入庫與過帳。');
				}

				// 狀態推進至 approved（記錄審核資訊）
				$return->transitionTo('approved');
				
				// 立即執行過帳（內含庫存入庫 + 會計分錄）
				$return->post();
			});

			$this->drawer = false;
			$this->selectedReturn = null;
			$this->resetPage(); // 重置分頁回到第一頁
			$this->success('退貨入庫與財務結轉已完成！');

		} catch (\Throwable $e) {
			$this->error('處理失敗：' . $e->getMessage());
		}
	}

    /**
     * 刪除退貨紀錄 (需注意庫存回滾邏輯)
     */
    public function delete(SalesReturn $return)
    {
        if ($return->status === 'completed') {
            $this->error('已完成財務結轉的單據不可刪除。');
            return;
        }

        // 應在 Model 或 Service 層處理：刪除退貨單時，需扣除之前因退貨而回補的庫存
        $return->delete(); 
        $this->drawer = false;
        $this->success('退貨紀錄已刪除，庫存已重新計算');
    }
	
    public function render()
    {
        $returns = SalesReturn::with([
            'sale.customer',
            'items.product',
            'fees',
            'user'
        ])
        ->when($this->search, function ($query) {
            $query->where('return_no', 'like', "%{$this->search}%")
                  ->orWhereHas('sale', function($q) {
                      $q->where('invoice_number', 'like', "%{$this->search}%");
                  })
                  ->orWhereHas('sale.customer', function($q) {
                      $q->where('name', 'like', "%{$this->search}%");
                  });
        })
        ->orderBy('created_at', 'desc')
        ->paginate(15);

        $headers = [
            ['key' => 'return_no', 'label' => '退貨單號', 'class' => 'font-mono'],
            ['key' => 'sale.invoice_number', 'label' => '原銷售單號', 'class' => 'font-mono text-xs'],
            ['key' => 'sale.customer.name', 'label' => '客戶名稱'],
            ['key' => 'total_refund_amount', 'label' => '應退金額', 'textAlign' => 'text-right'],
            ['key' => 'status', 'label' => '狀態', 'class' => 'text-center'],
            ['key' => 'created_at', 'label' => '申請時間'],
        ];

        return view('livewire.sales.returns.return-index', [
            'returns' => $returns,
            'headers' => $headers
        ]);
    }
}