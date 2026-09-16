<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'shop_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'cost_snapshot',   // 🆕 與 Conversion 寫入一致
        'type',
        'reference',
        'remark',
        'user_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTypeNameAttribute(): string
    {
        return match ($this->type) {
            'initial'           => '期初導入',
            'gift'              => '公關贈送',
            'scrap'             => '商品報廢',
            'sample'            => '樣品領用',
            'stocktake_adj'     => '盤點調整',
            'miscellaneous_adj' => '雜項調整',
            'sale'              => '銷售',
            'purchase'          => '採購',
            'transfer'          => '調撥',
            'conversion_input'  => '拆裝投入（領料）',
            'conversion_output' => '拆裝產出（入庫）',
            default             => $this->type,
        };
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'initial'           => 'badge-success',
            'gift'              => 'badge-warning',
            'scrap'             => 'badge-error',
            'sample'            => 'badge-info',
            'stocktake_adj'     => 'badge-warning',
            'miscellaneous_adj' => 'badge-neutral',
            'sale'              => 'badge-error',
            'purchase'          => 'badge-success',
            'transfer'          => 'badge-info',
            'conversion_input'  => 'badge-warning',
            'conversion_output' => 'badge-success',
            default             => 'badge-neutral',
        };
    }

    /**
     * 手動庫存調整 (AdjustStock) 專用下拉選單
     */
    public static function getManualAdjustTypes(): array
    {
        return [
            'initial'           => '[入庫] 期初庫存導入 (舊店/搬遷轉入)',
            'gift'              => '[出庫] 公關贈送',
            'scrap'             => '[出庫] 商品報廢 / 損耗',
            'sample'            => '[出庫] 樣品領用 / 內部借用',
            'miscellaneous_adj' => '雜項調整（可選方向）',
        ];
    }

    /**
     * 系統自動單據使用的類型（不供手動選擇）
     */
    public static function getSystemAdjustTypes(): array
    {
        return [
            'stocktake_adj'     => '盤點調整',
            'sale'              => '銷售出庫',
            'purchase'          => '採購入庫',
            'transfer'          => '調撥',
            'conversion_input'  => '拆裝投入（領料）',
            'conversion_output' => '拆裝產出（入庫）',
        ];
    }

    /**
     * 全部類型（供查詢、驗證、報表使用）
     */
    public static function getAllAdjustTypes(): array
    {
        return array_merge(self::getManualAdjustTypes(), self::getSystemAdjustTypes());
    }
}