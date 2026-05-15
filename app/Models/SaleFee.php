<?php // 檔案路徑：app/Models/SaleFee.php

namespace App\Models;

use \App\Traits\HasShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleFee extends Model
{    
    protected $fillable = [
        'shop_id',
        'sale_id',
        'fee_type',
        'amount',
        'note',        
    ];

    /**
     * 數值嚴謹性規範：自動轉換格式
     */
    protected $casts = [
        'amount' => 'decimal:4',
    ];

    /**
     * 關聯原銷售單
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
	
	public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}