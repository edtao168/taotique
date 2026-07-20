<?php
// app/Models/Traits/TenantScoped.php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait TenantScoped
{
    /**
     * 自動過濾當前租戶的資料
     */
    protected static function bootTenantScoped()
    {
        // 查詢時自動加上 tenant_id 條件
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check() && auth()->user()->tenant_id) {
                $builder->where('tenant_id', auth()->user()->tenant_id);
            }
        });

        // 新增時自動填入 tenant_id
        static::creating(function ($model) {
            if (auth()->check() && auth()->user()->tenant_id) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });
    }
}