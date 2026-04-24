<?php //檔案路徑：app/Traits/HasShop.php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
//use Illuminate\Database\Eloquent\Model;

trait HasShop
{
    /**
     * 給 Eloquent Model 使用：自動過濾 shop_id
     * 只有當此 Trait 被用於繼承了 Model 的類別時才會生效
     */
    public static function bootHasShop()
    {
        // 檢查當前類別是否支持 addGlobalScope (即是否為 Model)
        if (method_exists(static::class, 'addGlobalScope')) {
            static::addGlobalScope('shop_filter', function (Builder $builder) {
                if (auth()->check()) {
                    $builder->where('shop_id', auth()->user()->shop_id);
                }
            });
        }
    }

    /**
     * 給 Livewire Component 使用：取得當前登入者的 shop_id
     * 透過計算屬性 (Computed Property) 確保即時性與嚴謹性
     */
    public function getShopIdProperty(): int
    {
        return auth()->user()->shop_id ?? 1; // 預設為 1 符合您的規範
    }
}

// 在 Model 中使用
/* class SalesReturn extends Model
{
    use \App\Traits\HasShop; // 一行注入多店隔離邏輯
} */