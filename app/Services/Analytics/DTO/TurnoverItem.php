<?php
// app/Services/Analytics/DTO/TurnoverItem.php

namespace App\Services\Analytics\DTO;

final readonly class TurnoverItem
{
    public function __construct(
        public int    $productId,
        public string $productName,
        public float  $avgStockQty,
        public float  $soldQty,
        public float  $turnoverRate,
        public float  $turnoverDays,
        public float  $stockValue,
        public float  $dailySalesAvg,
        public ?int   $daysToSellOut,
        public bool   $isSlowMoving,
    ) {}
}