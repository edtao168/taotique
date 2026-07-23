<?php
// app/Models/Traits/ShopScoped.php

namespace App\Models\Traits;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;

trait ShopScoped
{
    /**
     * 啟動 ShopScoped Global Scope
     * 
     * 這個 Trait 會自動：
     * 1. 查詢時自動過濾 shop_id（依據使用者角色）
     * 2. 新增時自動填入 current_shop_id
     */
    protected static function bootShopScoped()
    {
        // ============================================================
        // 1. 查詢過濾（Global Scope）
        // ============================================================
        static::addGlobalScope('shop', function (Builder $builder) {
            // 未登入 → 不處理
            if (!auth()->check()) {
                return;
            }

            $user = auth()->user();
            $role = $user->role ?? 'staff';

            // --- 1.1 Owner / Admin：可看該租戶所有店鋪 ---
            if (in_array($role, ['owner', 'admin'])) {
                $shopIds = Shop::where('tenant_id', $user->tenant_id)->pluck('id');
                
                if ($shopIds->isNotEmpty()) {
                    $builder->whereIn('shop_id', $shopIds);
                } else {
                    // 沒有任何店鋪 → 回傳空集合
                    $builder->whereRaw('1 = 0');
                }
                return;
            }

            // --- 1.2 Staff（預設）：只能看自己的店 ---
            if ($user->current_shop_id) {
                $builder->where('shop_id', $user->current_shop_id);
                return;
            }

            // --- 1.3 沒有 current_shop_id 的 Staff（fallback） ---
            // 如果 staff 沒有設定 current_shop_id，改用 tenant 的所有店鋪
            if ($user->tenant_id) {
                $shopIds = Shop::where('tenant_id', $user->tenant_id)->pluck('id');
                if ($shopIds->isNotEmpty()) {
                    $builder->whereIn('shop_id', $shopIds);
                }
            }
        });

        // ============================================================
        // 2. 新增時自動填入 shop_id
        // ============================================================
        static::creating(function ($model) {
            if (auth()->check() && auth()->user()->current_shop_id) {
                $model->shop_id = auth()->user()->current_shop_id;
            }
        });
    }

    /**
     * 清除 ShopScoped 的 Global Scope（用於特殊查詢）
     * 
     * 使用情境：
     * - Owner 想看特定單店的數據
     * - 系統後台需要跨店查詢
     * 
     * @example Sale::withoutShopScope()->where('shop_id', $shopId)->get()
     */
    public function scopeWithoutShopScope($query)
    {
        return $query->withoutGlobalScope('shop');
    }

    /**
     * 過濾指定店鋪（輔助方法）
     * 
     * @example Sale::forShop($shopId)->get()
     */
    public function scopeForShop($query, $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * 過濾多個店鋪（輔助方法）
     * 
     * @example Sale::forShops([1, 2, 3])->get()
     */
    public function scopeForShops($query, array $shopIds)
    {
        return $query->whereIn('shop_id', $shopIds);
    }
}