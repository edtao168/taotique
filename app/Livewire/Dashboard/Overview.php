<?php

namespace App\Livewire\Dashboard;

use App\Models\Inventory;
use App\Models\Sale;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Overview extends Component
{
    public function render()
    {
        $now = Carbon::now();

        // 1. 統計數據
        $stats = [
            'todaySales' => Sale::whereDate('created_at', Carbon::today())->sum('subtotal'),
            'monthSales' => Sale::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->sum('subtotal'),
            'monthNetProfit' => Sale::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->sum('final_net_amount'),
            'inventoryValue' => Product::totalInventoryValue(),
            'lowStockCount' => Inventory::whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('products')
                      ->whereColumn('products.id', 'inventories.product_id')
                      ->whereRaw('inventories.quantity <= products.min_stock');
            })->count(),
        ];

        // 2. 生成最近 12 個月的月份列表（包含無數據的月份）
        $monthlyData = $this->getMonthlyDataWithGapsFilled();

        return view('livewire.dashboard.overview', [
            'stats' => $stats,
            'monthlyData' => $monthlyData,
            'recentSales' => Sale::with('customer')->latest()->take(10)->get()
        ]);
    }

    /**
     * 獲取最近 12 個月數據，缺少的月份補 0
     */
    private function getMonthlyDataWithGapsFilled()
    {
        $now = Carbon::now();
        $startDate = $now->copy()->subMonths(11)->startOfMonth();
        
        // 生成 12 個月的空白模板
        $months = collect();
        for ($i = 0; $i < 12; $i++) {
            $date = $startDate->copy()->addMonths($i);
            $months->put($date->format('Y-m'), [
                'month' => $date->format('Y-m'),
                'sales' => 0,
                'profit' => 0,
            ]);
        }

        // 查詢實際數據
        $actualData = Sale::select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('SUM(subtotal) as sales'),
                DB::raw('SUM(final_net_amount) as profit')
            )
            ->where('created_at', '>=', $startDate)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // 合併數據：用實際數據覆蓋模板中的 0
        $merged = $months->map(function ($defaultData, $monthKey) use ($actualData) {
            if ($actualData->has($monthKey)) {
                return [
                    'month' => $monthKey,
                    'sales' => (float) $actualData[$monthKey]->sales,
                    'profit' => (float) $actualData[$monthKey]->profit,
                ];
            }
            return $defaultData;
        });

        return $merged->values(); // 轉回數值索引陣列
    }
}