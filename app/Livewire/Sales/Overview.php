<?php // app/Livewire/Sales/Overview.php

namespace App\Livewire\Sales;

use Livewire\Component;
use App\Models\Sale;
use App\Models\Inventory;
use Carbon\Carbon;
use Livewire\WithPagination;

class Overview extends Component
{
    use WithPagination;

    public bool $drawer = false;
    public ?Sale $selectedSale = null;

    /**
     * 觸發詳情抽屜
     */
    public function showDetail($id)
    {
        // 預加載關聯以確保抽屜內的資料（如客戶名、商品名）能顯示
        $this->selectedSale = Sale::with(['customer', 'items.product'])->find($id);
        $this->drawer = true;
    }

    public function render()
	{
		// 取得時間區間
		$startOfMonth = Carbon::now()->startOfMonth();
		$endOfMonth   = Carbon::now()->endOfMonth();
		$startOfYear  = Carbon::now()->startOfYear();
		
		// 取得上個月區間 (用於對比)
		$startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
		$endOfLastMonth   = Carbon::now()->subMonth()->endOfMonth();

		// 1. 本月銷售與上月對比
		$monthSales = Sale::whereBetween('sold_at', [$startOfMonth, $endOfMonth])->sum('subtotal');
		$lastMonthSales = Sale::whereBetween('sold_at', [$startOfLastMonth, $endOfLastMonth])->sum('subtotal');
		
		// 計算成長率 (避免除以零)
		$salesGrowth = $lastMonthSales > 0 
			? (($monthSales - $lastMonthSales) / $lastMonthSales) * 100 
			: ($monthSales > 0 ? 100 : 0);

		// 2. 本年銷售
		$yearSales = Sale::whereBetween('sold_at', [$startOfYear, Carbon::now()])->sum('subtotal');

		// 3. 本月淨利 (假設您已有 final_net_amount 欄位)
		$monthProfit = Sale::whereBetween('sold_at', [$startOfMonth, $endOfMonth])->sum('final_net_amount');

		return view('livewire.sales.overview', [
			'monthSales'     => $monthSales,
			'salesGrowth'    => round($salesGrowth, 1),
			'yearSales'      => $yearSales,
			'monthProfit'    => $monthProfit,
			// 清單一併改為只顯示本月銷售單
			'recentSales'    => Sale::with('customer')
								->whereBetween('sold_at', [$startOfMonth, $endOfMonth])
								->latest()
								->paginate(10)
		]);
	}
}