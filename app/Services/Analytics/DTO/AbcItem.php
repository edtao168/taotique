<?php
// app/Services/Analytics/DTO/AbcItem.php

namespace App\Services\Analytics\DTO;

final readonly class AbcItem
{
    public function __construct(
        public int    $productId,
        public string $productName,
        public float  $revenue,
        public float  $revenueShare,
        public float  $cumulativeShare,
        public string $grade,
    ) {}
}