<?php
// app/Services/Analytics/DTO/HourlyHeatmapCell.php

namespace App\Services\Analytics\DTO;

final readonly class HourlyHeatmapCell
{
    public function __construct(
        public int   $dayOfWeek,
        public int   $hour,
        public float $revenue,
        public int   $orderCount,
    ) {}
}