<?php
// app/Services/Analytics/DTO/SalesMetrics.php

namespace App\Services\Analytics\DTO;

final readonly class SalesMetrics
{
    public function __construct(
		public float $todaySales,
		public float $monthSales,
		public float $monthNetRevenue,
		public float $yearSales,
		public float $monthSalesPrev,
		public float $salesGrowth,
		public int $monthOrderCount,
		public float $monthAvgOrderValue,
		public float $monthGrossProfit,
		public float $monthGrossMarginRate,
	) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}