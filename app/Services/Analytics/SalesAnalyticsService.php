<?php
// app/Services/Analytics/SalesAnalyticsService.php

namespace App\Services\Analytics;

use App\Enums\WorkflowStatus;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Analytics\DTO\AbcItem;
use App\Services\Analytics\DTO\GmroiItem;
use App\Services\Analytics\DTO\HourlyHeatmapCell;
use App\Services\Analytics\DTO\SalesMetrics;
use App\Services\Analytics\DTO\TurnoverItem;
use App\Services\Analytics\Support\AnalyticsScope;
use App\Services\Analytics\Support\AnalyticsScopeResolver;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SalesAnalyticsService
{
    public function __construct(
        private readonly AnalyticsScopeResolver $scopeResolver,
    ) {}

    // =================================================================
    // 1. 總覽指標
    // =================================================================

    public function overview(?Carbon $date = null): SalesMetrics
    {
        $date  ??= now();
        $scope   = $this->scopeResolver->resolve();

        return Cache::remember(
            $this->cacheKey('overview', $scope, $date->toDateString()),
            now()->addMinutes(5),
            fn () => $this->buildOverview($date, $scope)
        );
    }

	private function buildOverview(Carbon $date, AnalyticsScope $scope): SalesMetrics
	{
		if (!$scope->hasAnyShop()) {
			return $this->emptyMetrics();
		}

		$monthStart = $date->copy()->startOfMonth();
		$monthEnd   = $date->copy()->endOfMonth();
		$prevStart  = $date->copy()->subMonthNoOverflow()->startOfMonth();
		$prevEnd    = $date->copy()->subMonthNoOverflow()->endOfMonth();
		$yearStart  = $date->copy()->startOfYear();

		// ============================================================
		// (1) 用 sales 算：營收、淨營業額、訂單數
		// ============================================================
		$row = Sale::query()
			->whereIn('shop_id', $scope->shopIds)
			->whereIn('status', $this->revenueStatuses())
			->selectRaw('
				SUM(CASE WHEN sold_at BETWEEN ? AND ? THEN customer_total ELSE 0 END) as month_sales,
				SUM(CASE WHEN sold_at BETWEEN ? AND ? THEN final_net_amount ELSE 0 END) as month_net_revenue,
				SUM(CASE WHEN sold_at BETWEEN ? AND ? THEN customer_total ELSE 0 END) as prev_sales,
				SUM(CASE WHEN sold_at BETWEEN ? AND ? THEN customer_total ELSE 0 END) as year_sales,
				SUM(CASE WHEN DATE(sold_at) = ? THEN customer_total ELSE 0 END) as today_sales,
				COUNT(CASE WHEN sold_at BETWEEN ? AND ? THEN 1 END) as month_orders
			', [
				$monthStart, $monthEnd,
				$monthStart, $monthEnd,
				$prevStart,  $prevEnd,
				$yearStart,  $monthEnd,
				$date->toDateString(),
				$monthStart, $monthEnd,
			])
			->first();

		$monthSales      = (float) ($row->month_sales ?? 0);
		$monthNetRevenue = (float) ($row->month_net_revenue ?? 0);
		$prevSales       = (float) ($row->prev_sales ?? 0);
		$monthOrders     = (int)   ($row->month_orders ?? 0);

		// ============================================================
		// (2) 用 sale_items 算：本月商品成本
		//     ⚠️ 成本只認「已出庫」的狀態（costStatuses）
		// ============================================================
		$costRow = DB::table('sale_items as si')
			->join('sales as s', 's.id', '=', 'si.sale_id')
			->join('products as p', 'p.id', '=', 'si.product_id')
			->whereIn('s.shop_id', $scope->shopIds)
			->where('p.tenant_id', $scope->tenantId)
			->whereIn('s.status', $this->costStatuses())
			->whereBetween('s.sold_at', [$monthStart, $monthEnd])
			->selectRaw('SUM(si.quantity * COALESCE(p.cost, 0)) as month_cost')
			->first();

		$monthCost = (float) ($costRow->month_cost ?? 0);

		// ============================================================
		// (3) 算毛利與毛利率
		// ============================================================
		$monthGrossProfit = $monthNetRevenue - $monthCost;
		$monthGrossMargin = $monthNetRevenue > 0
			? round(($monthGrossProfit / $monthNetRevenue) * 100, 2)
			: 0.0;

		// ============================================================
		// (4) 算月增率
		// ============================================================
		$growth = $prevSales > 0
			? (($monthSales - $prevSales) / $prevSales) * 100
			: ($monthSales > 0 ? 100.0 : 0.0);

		// ============================================================
		// (5) 回傳
		// ============================================================
		return new SalesMetrics(
			todaySales:           (float) ($row->today_sales ?? 0),
			monthSales:           $monthSales,
			monthNetRevenue:      $monthNetRevenue,
			yearSales:            (float) ($row->year_sales ?? 0),
			monthSalesPrev:       $prevSales,
			salesGrowth:          round($growth, 2),
			monthOrderCount:      $monthOrders,
			monthAvgOrderValue:   $monthOrders > 0 ? round($monthSales / $monthOrders, 2) : 0.0,
			monthGrossProfit:     round($monthGrossProfit, 2),
			monthGrossMarginRate: $monthGrossMargin,
		);
	}

    private function emptyMetrics(): SalesMetrics
    {
        return new SalesMetrics(0, 0, 0, 0, 0, 0.0, 0, 0.0);
    }

    // =================================================================
    // 2. 12 個月趨勢
    // =================================================================

    public function monthlyTrend(int $months = 12): Collection
    {
        $scope = $this->scopeResolver->resolve();
        $start = now()->subMonths($months - 1)->startOfMonth();

        return Cache::remember(
            $this->cacheKey('trend', $scope, "m{$months}"),
            now()->addMinutes(10),
            function () use ($scope, $start) {
                if (!$scope->hasAnyShop()) {
                    return $this->emptyTrend($start);
                }

                $rows = Sale::query()
                    ->whereIn('shop_id', $scope->shopIds)
                    ->whereIn('status', $this->revenueStatuses())
                    ->where('sold_at', '>=', $start)
                    ->selectRaw("
                        DATE_FORMAT(sold_at, '%Y-%m') as month,
                        SUM(customer_total) as sales,
                        SUM(final_net_amount) as profit
                    ")
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get()
                    ->keyBy('month');

                return collect(CarbonPeriod::create($start, '1 month', now()->startOfMonth()))
                    ->map(function (Carbon $m) use ($rows) {
                        $key = $m->format('Y-m');
                        return (object) [
                            'month'  => $key,
                            'sales'  => (float) ($rows[$key]->sales ?? 0),
                            'profit' => (float) ($rows[$key]->profit ?? 0),
                        ];
                    });
            }
        );
    }

    private function emptyTrend(Carbon $start): Collection
    {
        return collect(CarbonPeriod::create($start, '1 month', now()->startOfMonth()))
            ->map(fn (Carbon $m) => (object) [
                'month' => $m->format('Y-m'),
                'sales' => 0.0,
                'profit' => 0.0,
            ]);
    }

    // =================================================================
    // 3. ABC 分析
    // =================================================================

    public function abcAnalysis(Carbon $from, Carbon $to, string $metric = 'revenue'): Collection
    {
        $scope = $this->scopeResolver->resolve();

        return Cache::remember(
            $this->cacheKey('abc', $scope, "{$from->toDateString()}_{$to->toDateString()}_{$metric}"),
            now()->addMinutes(15),
            fn () => $this->buildAbc($from, $to, $metric, $scope)
        );
    }

	private function buildAbc(Carbon $from, Carbon $to, string $metric, AnalyticsScope $scope): Collection
	{
		if (!$scope->hasAnyShop()) {
			return collect();
		}

		$valueExpr = $metric === 'gross_profit'
			? 'SUM(si.subtotal - si.total_cost)'
			: 'SUM(si.subtotal)';

		$statuses = $metric === 'gross_profit'
			? $this->costStatuses()
			: $this->revenueStatuses();

		// ✅ 不用窗口函數，只算每商品聚合
		$rows = DB::table('sale_items as si')
			->join('sales as s', 's.id', '=', 'si.sale_id')
			->join('products as p', 'p.id', '=', 'si.product_id')
			->whereIn('s.shop_id', $scope->shopIds)
			->where('p.tenant_id', $scope->tenantId)
			->whereBetween('s.sold_at', [$from, $to])
			->whereIn('s.status', $statuses)
			->groupBy('si.product_id', 'p.name')
			->selectRaw("
				si.product_id,
				p.name as product_name,
				{$valueExpr} as metric_value
			")
			->orderByDesc('metric_value')
			->get();

		if ($rows->isEmpty()) {
			return collect();
		}

		// ✅ 在 PHP 端算累計與分級（O(n)，極快）
		$total = (float) $rows->sum('metric_value');

		$cumulative = 0.0;

		return $rows->map(function ($row) use ($total, &$cumulative) {
			$value = (float) $row->metric_value;
			$cumulative += $value;

			$revenueShare    = $total > 0 ? ($value / $total) * 100 : 0;
			$cumulativeShare = $total > 0 ? ($cumulative / $total) * 100 : 0;

			return new AbcItem(
				productId:       (int) $row->product_id,
				productName:     (string) $row->product_name,
				revenue:         $value,
				revenueShare:    round($revenueShare, 2),
				cumulativeShare: round($cumulativeShare, 2),
				grade:           $this->resolveAbcGrade($cumulativeShare),
			);
		});
	}

    private function resolveAbcGrade(float $cumulativeShare): string
    {
        return match (true) {
            $cumulativeShare <= 70 => 'A',
            $cumulativeShare <= 90 => 'B',
            default                => 'C',
        };
    }

    // =================================================================
    // 4. 週轉率 / 滯銷（採方案 A：庫存成本用 products.cost）
    // =================================================================

    public function turnoverAnalysis(Carbon $from, Carbon $to, int $slowMovingDays = 60): Collection
    {
        $scope = $this->scopeResolver->resolve();

        return Cache::remember(
            $this->cacheKey('turnover', $scope, "{$from->toDateString()}_{$to->toDateString()}_{$slowMovingDays}"),
            now()->addMinutes(15),
            fn () => $this->buildTurnover($from, $to, $slowMovingDays, $scope)
        );
    }

    private function buildTurnover(Carbon $from, Carbon $to, int $slowMovingDays, AnalyticsScope $scope): Collection
    {
        if (!$scope->hasAnyShop()) {
            return collect();
        }

        $days = max(1, $from->diffInDays($to));

        // (1) 期間銷量（per product）
        $salesAgg = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->whereIn('s.shop_id', $scope->shopIds)
            ->whereIn('s.status', $this->costStatuses())
            ->whereBetween('s.sold_at', [$from, $to])
            ->groupBy('si.product_id')
            ->selectRaw('
                si.product_id,
                SUM(si.quantity) as sold_qty,
                SUM(si.total_cost) as cogs
            ')
            ->get()
            ->keyBy('product_id');

        // (2) 當前庫存（per product，僅 scope 內店鋪）
        // inventories 有 shop_id，但為保險仍 join warehouses 驗證
        $inventoryAgg = DB::table('inventories as inv')
            ->join('warehouses as w', 'w.id', '=', 'inv.warehouse_id')
            ->whereIn('w.shop_id', $scope->shopIds)
            ->groupBy('inv.product_id')
            ->selectRaw('
                inv.product_id,
                SUM(inv.quantity) as stock_qty
            ')
            ->get()
            ->keyBy('product_id');

        $productIds = $salesAgg->keys()
            ->merge($inventoryAgg->keys())
            ->unique()
            ->values()
            ->all();

        if (empty($productIds)) {
            return collect();
        }

        // (3) 商品主檔（含 cost，用於方案 A 成本）
        // Product 有 TenantScoped，Eloquent 自動過濾 tenant_id
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->select(['id', 'name', 'cost'])
            ->get()
            ->keyBy('id');

        return collect($productIds)->map(function ($pid) use ($products, $salesAgg, $inventoryAgg, $days, $slowMovingDays) {
            $p = $products[$pid] ?? null;
            if (!$p) return null;

            $soldQty = (float) ($salesAgg[$pid]->sold_qty ?? 0);

            $avgStockQty = (float) ($inventoryAgg[$pid]->stock_qty ?? 0);
            $stockValue  = $avgStockQty * (float) $p->cost;  // 方案 A

            $turnoverRate = $avgStockQty > 0 ? $soldQty / $avgStockQty : 0;
            $turnoverDays = $turnoverRate > 0 ? $days / $turnoverRate : $days;

            $dailySalesAvg = $soldQty / $days;
            $daysToSellOut = $dailySalesAvg > 0
                ? (int) ceil($avgStockQty / $dailySalesAvg)
                : null;

            $isSlowMoving = $avgStockQty > 0
                && ($daysToSellOut === null || $daysToSellOut > $slowMovingDays);

            return new TurnoverItem(
                productId:     (int) $pid,
                productName:   (string) $p->name,
                avgStockQty:   round($avgStockQty, 2),
                soldQty:       $soldQty,
                turnoverRate:  round($turnoverRate, 2),
                turnoverDays:  round($turnoverDays, 1),
                stockValue:    round($stockValue, 2),
                dailySalesAvg: round($dailySalesAvg, 2),
                daysToSellOut: $daysToSellOut,
                isSlowMoving:  $isSlowMoving,
            );
        })->filter()->sortByDesc('stockValue')->values();
    }

    // =================================================================
    // 5. GMROI（採方案 A）
    // =================================================================

    public function gmroiAnalysis(Carbon $from, Carbon $to): Collection
    {
        $scope = $this->scopeResolver->resolve();

        return Cache::remember(
            $this->cacheKey('gmroi', $scope, "{$from->toDateString()}_{$to->toDateString()}"),
            now()->addMinutes(15),
            fn () => $this->buildGmroi($from, $to, $scope)
        );
    }

    private function buildGmroi(Carbon $from, Carbon $to, AnalyticsScope $scope): Collection
	{
		if (!$scope->hasAnyShop()) {
			return collect();
		}

		// (1) 期間營收與銷量（per product）
		// ✅ 只取 revenue 與 sold_qty，cogs 在 PHP 端用 products.cost 算
		$salesAgg = DB::table('sale_items as si')
			->join('sales as s', 's.id', '=', 'si.sale_id')
			->whereIn('s.shop_id', $scope->shopIds)
			->whereIn('s.status', $this->costStatuses())
			->whereBetween('s.sold_at', [$from, $to])
			->groupBy('si.product_id')
			->selectRaw('
				si.product_id,
				SUM(si.subtotal) as revenue,
				SUM(si.quantity) as sold_qty
			')
			->get()
			->keyBy('product_id');

		// (2) 當前庫存量（per product，scope 內店鋪）
		$inventoryAgg = DB::table('inventories as inv')
			->join('warehouses as w', 'w.id', '=', 'inv.warehouse_id')
			->whereIn('w.shop_id', $scope->shopIds)
			->groupBy('inv.product_id')
			->selectRaw('
				inv.product_id,
				SUM(inv.quantity) as stock_qty
			')
			->get()
			->keyBy('product_id');

		$productIds = $salesAgg->keys()->unique()->values()->all();
		if (empty($productIds)) {
			return collect();
		}

		// (3) 商品主檔（含 cost，用於「當下成本」計算）
		$products = Product::query()
			->whereIn('id', $productIds)
			->select(['id', 'name', 'cost'])
			->get()
			->keyBy('id');

		return collect($productIds)->map(function ($pid) use ($products, $salesAgg, $inventoryAgg) {
			$p = $products[$pid] ?? null;
			if (!$p) return null;

			$revenue  = (float) ($salesAgg[$pid]->revenue ?? 0);
			$soldQty  = (float) ($salesAgg[$pid]->sold_qty ?? 0);
			$unitCost = (float) $p->cost;

			// ✅ 商品成本 = 銷量 × 當下單件成本
			$cogs        = $soldQty * $unitCost;
			$grossProfit = $revenue - $cogs;

			// ✅ 庫存成本 = 庫存量 × 當下單件成本
			$stockQty   = (float) ($inventoryAgg[$pid]->stock_qty ?? 0);
			$avgInvCost = $stockQty * $unitCost;

			$gmroi = $avgInvCost > 0 ? $grossProfit / $avgInvCost : 0.0;
			$grossMarginRate = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0.0;

			return new GmroiItem(
				productId:        (int) $pid,
				productName:      (string) $p->name,
				revenue:          round($revenue, 2),
				grossProfit:      round($grossProfit, 2),
				grossMarginRate:  round($grossMarginRate, 2),
				avgInventoryCost: round($avgInvCost, 2),
				gmroi:            round($gmroi, 2),
			);
		})->filter()->sortByDesc('gmroi')->values();
	}

    // =================================================================
    // 6. 時段熱度
    // =================================================================

    public function hourlyHeatmap(Carbon $from, Carbon $to): Collection
    {
        $scope = $this->scopeResolver->resolve();

        return Cache::remember(
            $this->cacheKey('heatmap', $scope, "{$from->toDateString()}_{$to->toDateString()}"),
            now()->addMinutes(30),
            function () use ($from, $to, $scope) {
                if (!$scope->hasAnyShop()) {
                    return $this->emptyHeatmap();
                }

                $rows = Sale::query()
                    ->whereIn('shop_id', $scope->shopIds)
                    ->whereIn('status', $this->revenueStatuses())
                    ->whereBetween('sold_at', [$from, $to])
                    ->selectRaw('
                        DAYOFWEEK(sold_at) - 1 as day_of_week,
                        HOUR(sold_at) as hour,
                        SUM(customer_total) as revenue,
                        COUNT(*) as order_count
                    ')
                    ->groupBy('day_of_week', 'hour')
                    ->get();

                $map = $rows->keyBy(fn ($r) => "{$r->day_of_week}-{$r->hour}");

                $result = collect();
                for ($d = 0; $d < 7; $d++) {
                    for ($h = 0; $h < 24; $h++) {
                        $key = "{$d}-{$h}";
                        $result->push(new HourlyHeatmapCell(
                            dayOfWeek:  $d,
                            hour:       $h,
                            revenue:    (float) ($map[$key]->revenue ?? 0),
                            orderCount: (int)   ($map[$key]->order_count ?? 0),
                        ));
                    }
                }

                return $result;
            }
        );
    }

    private function emptyHeatmap(): Collection
    {
        $result = collect();
        for ($d = 0; $d < 7; $d++) {
            for ($h = 0; $h < 24; $h++) {
                $result->push(new HourlyHeatmapCell($d, $h, 0, 0));
            }
        }
        return $result;
    }

    // =================================================================
    // Helpers
    // =================================================================

    private function cacheKey(string $type, AnalyticsScope $scope, string $suffix): string
    {
        return "analytics:{$type}:{$scope->signature()}:{$suffix}";
    }

    /**
     * 有效狀態：只有「已核准 / 已結案 / 已結算」才算成交。
     * 對應 WorkflowStatus enum：
     *   APPROVED  = 'approved'  （已審核，尚未出庫）
     *   COMPLETED = 'completed' （已出庫結案）
     *   SETTLED   = 'settled'   （已結算）
     *
     * ⚠️ 這裡的選擇會直接影響所有數字。
     * 「已審核」是否算成交？見下方說明。
     */
    // 營收：已審核就算（客戶已下單）
	private function revenueStatuses(): array
	{
		return [
			WorkflowStatus::APPROVED->value,
			WorkflowStatus::COMPLETED->value,
			WorkflowStatus::SETTLED->value,
		];
	}

	// 成本/毛利：只有出庫才算
	private function costStatuses(): array
	{
		return [
			WorkflowStatus::COMPLETED->value,
			WorkflowStatus::SETTLED->value,
		];
	}
}