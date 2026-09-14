<?php
// app/Services/Analytics/AnalyticsCache.php

namespace App\Services\Analytics;

use App\Services\Analytics\Support\AnalyticsScope;
use App\Services\Analytics\Support\AnalyticsScopeResolver;
use Illuminate\Support\Facades\Cache;

/**
 * 分析快取管理器
 *
 * 職責：
 * 1. 依「當前 scope」清除對應的 analytics 快取
 * 2. 避免 Owner 清快取時，Staff 的快取被誤清（反之亦然）
 *
 * 使用時機：
 * - Sale 建立 / 更新 / 刪除
 * - Sale 狀態轉換（approve / stock_out / settle / cancel）
 * - 退貨建立 / 完成
 * - 進貨過帳（影響庫存 → 影響週轉/GMROI）
 */
class AnalyticsCache
{
    /**
     * 快取類型清單（對應 SalesAnalyticsService 的 cacheKey 前綴）
     */
    private const TYPES = [
        'overview',
        'trend',
        'abc',
        'turnover',
        'gmroi',
        'heatmap',
    ];

    public function __construct(
        private readonly AnalyticsScopeResolver $scopeResolver,
    ) {}

    /**
     * 清除「當前登入者 scope」的所有分析快取
     *
     * ⚠️ 注意：這只清當前 scope。
     * 若 Owner 改了資料，Staff 的快取不會被清（反之亦然）。
     * 這是刻意的：避免跨 scope 誤清，也避免權限洩漏。
     */
    public function flushForCurrentScope(): void
    {
        try {
            $scope = $this->scopeResolver->resolve();
            $this->flushForScope($scope);
        } catch (\Throwable $e) {
            // 若無 tenant context（例如排程、CLI），靜默跳過
            // 避免因快取清除失敗而中斷主要業務
            logger()->warning('AnalyticsCache flush skipped: ' . $e->getMessage());
        }
    }

    /**
     * 清除「指定 scope」的所有分析快取
     */
    public function flushForScope(AnalyticsScope $scope): void
    {
        $signature = $scope->signature();

        foreach (self::TYPES as $type) {
            // 快取 key 格式：analytics:{type}:{signature}:{suffix}
            // suffix 不固定（日期、月數、metric），無法逐一列舉
            // 因此用 pattern 刪除
            $this->forgetByPattern("analytics:{$type}:{$signature}:*");
        }
    }

    /**
     * 依 tenant 清除所有 scope 的快取
     *
     * 使用時機：
     * - 需要「全租戶」清快取時（例如管理員批次操作）
     * - 或作為安全網（不確定當前 scope 時）
     */
    public function flushForTenant(int $tenantId): void
    {
        foreach (self::TYPES as $type) {
            $this->forgetByPattern("analytics:{$type}:t{$tenantId}:*");
        }
    }

    /**
     * 依 pattern 刪除快取
     *
     * ⚠️ 相容性說明：
     * - Redis driver：用 SCAN 迭代刪除（安全，不阻塞）
     * - Database driver：查 cache 表 LIKE 刪除
     * - File driver：不支援 pattern，只能略過（或改用 tag）
     *
     * 若你的 cache driver 支援 tags，建議改用 tag（見下方註解）
     */
    private function forgetByPattern(string $pattern): void
    {
        $store = Cache::getStore();

        // --- Redis ---
        if ($store instanceof \Illuminate\Cache\RedisStore) {
            $redis = $store->connection();
            $prefix = $store->getPrefix();
            $fullPattern = $prefix . $pattern;

            $cursor = '0';
            do {
                [$cursor, $keys] = $redis->scan($cursor, ['match' => $fullPattern, 'count' => 100]);
                if (!empty($keys)) {
                    // 去掉 prefix 再刪，避免 double prefix
                    $keys = array_map(fn ($k) => substr($k, strlen($prefix)), $keys);
                    $redis->del($keys);
                }
            } while ($cursor !== '0');

            return;
        }

        // --- Database ---
        if ($store instanceof \Illuminate\Cache\DatabaseStore) {
            $prefix = $store->getPrefix();
            \DB::table('cache')
                ->where('key', 'like', $prefix . $pattern)
                ->delete();

            return;
        }

        // --- 其他（File / Array / Memcached）---
        // 無法用 pattern 刪除。建議這些環境改用 tags。
        logger()->debug('AnalyticsCache: pattern forget not supported for ' . get_class($store));
    }
}