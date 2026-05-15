<?php // app/Models/SalesReturnFee.php

namespace App\Models;

use App\Traits\HasShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnFee extends Model
{
    use HasShop; // 規範 5: 多店預留

    protected $fillable = [
        'shop_id',
        'sales_return_id',
        'fee_type',
        'amount',
        'note'
    ];

    protected $casts = [
        'amount' => 'decimal:4', // 數值嚴謹性
    ];

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }
}