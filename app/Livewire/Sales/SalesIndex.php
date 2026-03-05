<?php //C:\laragon\www\taotique\app\Livewire\Sales\SalesIndex.php

namespace App\Livewire\Sales;

use App\Models\Sale;
use Livewire\Component;
use Livewire\WithPagination;

class SalesIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $drawer = false;
    public ?Sale $selectedSale = null;

    // 定義表格欄位
    public function headers(): array
    {
        return [
            ['key' => 'invoice_number', 'label' => '訂單編號'],
            ['key' => 'channel', 'label' => '通路'],
            ['key' => 'customer.name', 'label' => '客戶姓名'],
            ['key' => 'customer_total', 'label' => '應收總額'],
            ['key' => 'sold_at', 'label' => '銷售日期'],
        ];
    }

    /**
     * 觸發詳情抽屜
     */
    public function showDetail($id)
    {
        $this->selectedSale = Sale::with(['customer', 'items.product'])->find($id);
        $this->drawer = true;
    }

    /**
     * 刪除訂單 (含還原庫存邏輯)
     */
    public function delete(Sale $sale)
    {
        // 這裡建議調用我們在 Sale Model 寫好的邏輯
        // $sale->void(); 
        $sale->delete();
        $this->drawer = false;
    }

    public function render()
    {
        $sales = Sale::with('customer')
            ->where('invoice_number', 'like', "%{$this->search}%")
            ->orWhereHas('customer', function($q) {
                $q->where('name', 'like', "%{$this->search}%");
            })
            ->latest('sold_at')
            ->paginate(15);

        return view('livewire.sales.sales-index', [
            'sales' => $sales,
            'headers' => $this->headers()
        ]);
    }
}