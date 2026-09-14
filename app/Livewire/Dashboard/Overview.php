<?php
// app/Livewire/Dashboard/Overview.php

namespace App\Livewire\Dashboard;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Analytics\SalesAnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Overview extends Component
{
    /**
     * ABC 分析：指標（revenue / gross_profit）
     */
    public string $abcMetric = 'revenue';

    /**
     * ABC 分析：期間月數（1 / 3 / 6 / 12）
     */
    public int $abcMonths = 3;

    /**
     * 切換指標時，清除 computed 快取
     */
    public function updatedAbcMetric(): void
    {
        unset($this->abcItems);
    }

    /**
     * 切換期間時，清除 computed 快取
     */
    public function updatedAbcMonths(): void
    {
        unset($this->abcItems);
    }

    /**
     * ABC 分析資料（依當前 metric / months）
     */
    #[Computed]
    public function abcItems(): Collection
    {
        $to   = now()->endOfMonth();
		$from = now()->subMonths($this->abcMonths - 1)->startOfMonth();

        return app(SalesAnalyticsService::class)
            ->abcAnalysis($from, $to, $this->abcMetric);
    }

    /**
     * ABC 分級後的統計摘要（給 Blade 顯示各級數量與金額）
     */
    #[Computed]
    public function abcSummary(): array
    {
        $items = $this->abcItems;

        $summary = [
            'A' => ['count' => 0, 'total' => 0.0],
            'B' => ['count' => 0, 'total' => 0.0],
            'C' => ['count' => 0, 'total' => 0.0],
        ];

        foreach ($items as $item) {
            $summary[$item->grade]['count']++;
            $summary[$item->grade]['total'] += $item->revenue;
        }

        $grandTotal = collect($summary)->sum('total');

        foreach ($summary as $grade => &$row) {
            $row['share'] = $grandTotal > 0
                ? round(($row['total'] / $grandTotal) * 100, 1)
                : 0.0;
        }

        return $summary;
    }
	
	    /**
     * 時段熱度：期間月數（1 / 3 / 6 / 12）
     */
    public int $heatmapMonths = 3;

    /**
     * 時段熱度：顯示模式（revenue / order_count）
     */
    public string $heatmapMode = 'revenue';

    /**
     * 切換期間時，清除 computed 快取
     */
    public function updatedHeatmapMonths(): void
    {
        unset($this->heatmapCells);
    }

    /**
     * 切換模式時，清除 computed 快取
     */
    public function updatedHeatmapMode(): void
    {
        unset($this->heatmapCells);
    }

    /**
     * 時段熱度資料（7×24 = 168 格）
     */
    #[Computed]
    public function heatmapCells(): Collection
    {
        $to   = now()->endOfMonth();
        $from = now()->subMonths($this->heatmapMonths - 1)->startOfMonth();

        return app(SalesAnalyticsService::class)
            ->hourlyHeatmap($from, $to);
    }

    /**
     * 時段熱度：整理成 7×24 二維陣列 + 最大值（給 Blade 畫圖用）
     */
    #[Computed]
    public function heatmapMatrix(): array
    {
        $cells = $this->heatmapCells;
        $mode  = $this->heatmapMode;

        // 初始化 7×24 矩陣
        $matrix = [];
        for ($d = 0; $d < 7; $d++) {
            for ($h = 0; $h < 24; $h++) {
                $matrix[$d][$h] = 0;
            }
        }

        // 填入資料
        $maxValue = 0;
        foreach ($cells as $cell) {
            $value = $mode === 'order_count' ? $cell->orderCount : $cell->revenue;
            $matrix[$cell->dayOfWeek][$cell->hour] = $value;
            if ($value > $maxValue) {
                $maxValue = $value;
            }
        }

        return [
            'matrix'   => $matrix,
            'maxValue' => $maxValue,
        ];
    }

    /**
     * 時段熱度：找出最佳時段（營收最高的前 3 格）
     */
    #[Computed]
    public function heatmapTopSlots(): array
    {
        $cells = $this->heatmapCells;
        $mode  = $this->heatmapMode;

        return $cells
            ->sortByDesc(fn ($c) => $mode === 'order_count' ? $c->orderCount : $c->revenue)
            ->take(3)
            ->map(fn ($c) => [
                'day'       => $c->dayOfWeek,
                'hour'      => $c->hour,
                'revenue'   => $c->revenue,
                'orderCount'=> $c->orderCount,
            ])
            ->values()
            ->all();
    }

    public function render(SalesAnalyticsService $analytics)
    {
        $tenantId = auth()->user()->tenant_id;

        // 1. 統計指標
        $metrics = $analytics->overview();

        // 2. 12 個月趨勢
        $monthlyData = $analytics->monthlyTrend(12);

        // 3. 庫存總額與低庫存預警
        $inventoryValue = Product::totalInventoryValue();

        $lowStockCount = Inventory::whereHas('product', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->whereColumn('inventories.quantity', '<=', 'products.min_stock');
            })
            ->whereHas('warehouse.shop', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
            ->count();

        // 4. 最近 5 筆銷售
        $recentSales = Sale::with(['shop', 'channel', 'customer', 'user'])
            ->latest('sold_at')
            ->take(5)
            ->get();

        return view('livewire.dashboard.overview', [
            'metrics'        => $metrics,
            'monthlyData'    => $monthlyData,
            'inventoryValue' => $inventoryValue,
            'lowStockCount'  => $lowStockCount,
            'recentSales'    => $recentSales,
        ]);
    }
}