<?php
// app/Services/Analytics/Support/AnalyticsScopeResolver.php

namespace App\Services\Analytics\Support;

use App\Models\Shop;

class AnalyticsScopeResolver
{
    public function resolve(): AnalyticsScope
    {
        $user = auth()->user();

        if (!$user || !$user->tenant_id) {
            throw new \RuntimeException('No tenant context for analytics.');
        }

        $role = $user->role ?? 'staff';

        // Owner / Admin → 該租戶所有店鋪（與 ShopScoped 邏輯對齊）
        if (in_array($role, ['owner', 'admin'], true)) {
            $shopIds = Shop::where('tenant_id', $user->tenant_id)
                ->pluck('id')
                ->all();

            return new AnalyticsScope(
                tenantId:     $user->tenant_id,
                shopIds:      $shopIds,
                isTenantWide: true,
            );
        }

        // Staff → 只認 current_shop_id
        if ($user->current_shop_id) {
            return new AnalyticsScope(
                tenantId:     $user->tenant_id,
                shopIds:      [(int) $user->current_shop_id],
                isTenantWide: false,
            );
        }

        // Fallback：與 ShopScoped 一致
        $shopIds = Shop::where('tenant_id', $user->tenant_id)
            ->pluck('id')
            ->all();

        return new AnalyticsScope(
            tenantId:     $user->tenant_id,
            shopIds:      $shopIds,
            isTenantWide: true,
        );
    }
}