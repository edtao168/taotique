<?php
// 檔案路徑：app/Livewire/Purchases/Returns/ReturnIndex.php

namespace App\Livewire\Purchases\Returns;

use App\Models\PurchaseReturn;
use App\Traits\HasProductSearch;
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
        // 載入關聯：原銷售單、客戶、退貨明細與對應商品、操作員
        $this->selectedReturn = PurchaseReturn::with([
            'purchase.supplier', 
            'items.product', 
            'fees',
			'user'
        ])->find($id);
        
        $this->drawer = true;
    }

    /**
     * 刪除退貨紀錄 (需注意庫存回滾邏輯)
     */
    public function delete(PurchaseReturn $return)
    {
        // 應在 Model 或 Service 層處理：刪除退貨單時，需扣除之前因退貨而回補的庫存
        $return->delete(); 
        $this->drawer = false;
        $this->success('退貨紀錄已刪除，庫存已重新計算');
    }
		
    public function render()
    {
        $returns = PurchaseReturn::with([
			'purchase.supplier',
			'items.product',
			'fees',
			'user'
			])
			->when($this->search, function ($query) {
				$query->where('return_no', 'like', "%{$this->search}%")
                      ->orWhereHas('purchase', function($q) {
                          $q->where('purchase_number', 'like', "%{$this->search}%");  // 修正：purchase_no -> purchase_number
                      })
                      ->orWhereHas('purchase.supplier', function($q) {  // 修正：透過 purchase 關聯客戶
                          $q->where('name', 'like', "%{$this->search}%");
                      });
			})
			->orderBy('created_at', 'desc') // 修正為 created_at
			->paginate(15);

		$headers = [
			['key' => 'return_no', 'label' => '退貨單號', 'class' => 'font-mono'], // 修正 key
			['key' => 'purchase.purchase_number', 'label' => '原銷售單號', 'class' => 'font-mono text-xs'], // 修正 key
			['key' => 'purchase.supplier.name', 'label' => '客戶'],
			['key' => 'total_refund_amount', 'label' => '退款總額', 'textAlign' => 'text-right'], // 修正 key
			['key' => 'created_at', 'label' => '退貨日期', 'class' => 'w-32'], // 修正 key
		];

        return view('livewire.purchases.returns.return-index', [
            'returns' => $returns,
            'headers' => $headers
        ]);
    }
}