<?php //檔案路徑：app/Traits/HasShop.php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasShop
{
    public static function bootHasShop()
    {
        static::addGlobalScope('shop_filter', function (Builder $builder) {
            // 假設登入後 Session 或 Auth 存有 shop_id
            if (auth()->check()) {
                $builder->where('shop_id', auth()->user()->shop_id);
            }
        });
    }
}

// 在 Model 中使用
class SalesReturn extends Model
{
    use \App\Traits\HasShop; // 一行注入多店隔離邏輯
}