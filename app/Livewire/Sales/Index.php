<?php
// 檔案路徑：app/Livewire/Sales/Index.php

namespace App\Livewire\Sales;

use Livewire\Component;
use App\Models\Sale;
use Carbon\Carbon;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use WithPagination, Toast;

    public string $search = '';
    public bool $drawer = false;
    public ?Sale $selectedSale = null;

    // 篩選條件
    public $dateRange = 'month'; 

    /**
     * 觸發詳情抽屜
     */
    public function showDetail($id)
    {
        $this->selectedSale = Sale::with(['customer', 'items.product', 'user'])->find($id);
        $this->drawer = true;
    }

    /**
     * 刪除訂單
     */
    public function delete(Sale $sale)
    {
        // 呼叫 Model 層的作廢/刪除邏輯
        $sale->delete(); 
        $this->drawer = false;
        $this->success('訂單已刪除，庫存已回滾');
    }

    public function render()
    {
        // --- 1. 統計數據邏輯 ---
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth   = Carbon::now()->endOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth   = Carbon::now()->subMonth()->endOfMonth();

        $monthSales = Sale::whereBetween('sold_at', [$startOfMonth, $endOfMonth])->sum('subtotal');
        $lastMonthSales = Sale::whereBetween('sold_at', [$startOfLastMonth, $endOfLastMonth])->sum('subtotal');
        $salesGrowth = $lastMonthSales > 0 ? (($monthSales - $lastMonthSales) / $lastMonthSales) * 100 : ($monthSales > 0 ? 100 : 0);
        $yearSales = Sale::whereYear('sold_at', date('Y'))->sum('subtotal');
        $monthProfit = Sale::whereBetween('sold_at', [$startOfMonth, $endOfMonth])->get()->sum('final_net_amount');

        // --- 2. 銷售清單查詢 (合併原 SalesIndex 邏輯) ---
        $sales = Sale::with(['customer', 'user'])
            ->when($this->search, function ($query) {
                $query->where('invoice_number', 'like', "%{$this->search}%")
                      ->orWhereHas('customer', fn($q) => $q->where('name', 'like', "%{$this->search}%"));
            })
            ->orderBy('sold_at', 'desc')
            ->paginate(10);

        $headers = [
            ['key' => 'invoice_number', 'label' => '訂單單號', 'class' => 'font-mono'],
            ['key' => 'channel', 'label' => '通路', 'class' => 'w-24'],
            ['key' => 'customer.name', 'label' => '客戶'],
            ['key' => 'customer_total', 'label' => '應收總額', 'textAlign' => 'text-right'],
            ['key' => 'sold_at', 'label' => '日期', 'class' => 'w-32'],
        ];

        return view('livewire.sales.index', [
            'monthSales' => $monthSales,
            'salesGrowth' => $salesGrowth,
            'yearSales' => $yearSales,
            'monthProfit' => $monthProfit,
            'sales' => $sales,
            'headers' => $headers
        ]);
    }
}