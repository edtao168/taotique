<?php
// app/Services/Analytics/DTO/GmroiItem.php

namespace App\Services\Analytics\DTO;

final readonly class GmroiItem
{
    public function __construct(
        public int    $productId,
        public string $productName,
        public float  $revenue,
        public float  $grossProfit,
        public float  $grossMarginRate,
        public float  $avgInventoryCost,
        public float  $gmroi,
    ) {}
}