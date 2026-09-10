<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    // 允許批量寫入的欄位
    protected $fillable = [
        'product_id', 
        'warehouse_id', 
        'quantity', 
        'type', 
        'reference', 
        'remark', 
        'user_id'
    ];

    // 關聯商品
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }	

    // 關聯倉庫
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // 關聯操作人員
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
	
	public function getTypeNameAttribute(): string
	{
		return match ($this->type) {
			'sale' => '銷售',
			'purchase' => '採購',
			'transfer' => '調撥',
			'stocktake' => '盤點',
			default => $this->type,
		};
	}
	
	public function getTypeColorAttribute(): string
	{
		return match($this->type) {
			'transfer' => 'badge-info',
			'stocktake' => 'badge-warning',
			'sale' => 'badge-error',
			'purchase' => 'badge-success',
			default => 'badge-neutral',
		};
	}
	
	/**
     * 手動庫存調整 (AdjustStock) 專用的下拉選單選項
     * 排除由系統自動單據 (Sale, Purchase, Transfer, Stocktake) 觸發的類型
     */
    public static function getManualAdjustTypes(): array
    {
        return [
            // 入庫類 (數量為正數)
            'initial'          => '[入庫] 期初庫存導入 (舊店/搬遷轉入)',
            'miscellaneous_in' => '[入庫] 雜項/其他入庫 (如：廠商無償贈樣、贈品轉商品)',
            
            // 出庫類 (數量為負數)
            'gift'             => '[出庫] 公關贈送',
            'scrap'            => '[出庫] 商品報廢 / 損耗',
            'sample'           => '[出庫] 樣品領用 / 內部借用',
            'miscellaneous_out'=> '[出庫] 雜項/其它出庫',
        ];
    }
}