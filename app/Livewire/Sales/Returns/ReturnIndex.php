<?php
// 檔案路徑：app/Livewire/Sales/Returns/ReturnIndex.php

namespace App\Livewire\Sales\Returns;

use App\Models\SalesReturn;
use App\Traits\HasBarcodeScanner;
use App\Traits\HasProductSearch;
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
        // 載入關聯：原銷售單、客戶、退貨明細與對應商品、操作員
        $this->selectedReturn = SalesReturn::with([
            'sale.customer', 
            'items.product', 
            'fees',
			'user'
        ])->find($id);
        
        $this->drawer = true;
    }

    /**
     * 刪除退貨紀錄 (需注意庫存回滾邏輯)
     */
    public function delete(SalesReturn $return)
    {
        // 應在 Model 或 Service 層處理：刪除退貨單時，需扣除之前因退貨而回補的庫存
        $return->delete(); 
        $this->drawer = false;
        $this->success('退貨紀錄已刪除，庫存已重新計算');
    }

	/**
	 * 修正：列表頁掃碼應改為「搜尋退貨單」或「彈出詳情」
	 */
	public function onBarcodeScanned(string $barcode, ?int $index = null): void
	{
		// 嘗試尋找符合該條碼的退貨單號 (或原銷售單號)
		$return = SalesReturn::where('return_no', $barcode)
			->orWhereHas('sale', fn($q) => $q->where('invoice_number', $barcode))
			->first();

		if ($return) {
			$this->showDetail($return->id);
			$this->success("已找到單據：{$barcode}");
		} else {
			$this->error("找不到單據：{$barcode}");
		}
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
                          $q->where('invoice_number', 'like', "%{$this->search}%");  // 修正：sale_no -> invoice_number
                      })
                      ->orWhereHas('sale.customer', function($q) {  // 修正：透過 sale 關聯客戶
                          $q->where('name', 'like', "%{$this->search}%");
                      });
			})
			->orderBy('created_at', 'desc') // 修正為 created_at
			->paginate(15);

		$headers = [
			['key' => 'return_no', 'label' => '退貨單號', 'class' => 'font-mono'], // 修正 key
			['key' => 'sale.invoice_number', 'label' => '原銷售單號', 'class' => 'font-mono text-xs'], // 修正 key
			['key' => 'sale.customer.name', 'label' => '客戶'],
			['key' => 'total_refund_amount', 'label' => '退款總額', 'textAlign' => 'text-right'], // 修正 key
			['key' => 'created_at', 'label' => '退貨日期', 'class' => 'w-32'], // 修正 key
		];

        return view('livewire.sales.returns.return-index', [
            'returns' => $returns,
            'headers' => $headers
        ]);
    }
}