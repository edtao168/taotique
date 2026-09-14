<?php
// app/Services/Analytics/Support/AnalyticsScope.php

namespace App\Services\Analytics\Support;

final readonly class AnalyticsScope
{
    public function __construct(
        public int   $tenantId,
        public array $shopIds,       // 當前可見的店鋪 ID（可能為空）
        public bool  $isTenantWide,  // Owner/Admin = true
    ) {}

    public function signature(): string
    {
        $shops = implode(',', $this->shopIds);
        return "t{$this->tenantId}:s[{$shops}]:w" . ($this->isTenantWide ? '1' : '0');
    }

    public function hasAnyShop(): bool
    {
        return !empty($this->shopIds);
    }
}