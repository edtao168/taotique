<?php

// 檔案路徑：app/Models/PurchaseReturn.php

namespace App\Models;

use App\Traits\HasShop; // 規範 5: 多店預留
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PurchaseReturn extends Model
{
    use HasShop;

    protected $fillable = [
        'shop_id', 'warehouse_id', 'purchase_id', 'return_no',
        'items_total_amount', 'fees_total_amount', 'total_return_amount',
        'exchange_rate', 'status', 'return_reason', 'created_by'
    ];

    protected $casts = [
        'items_total_amount' => 'decimal:4',
        'fees_total_amount' => 'decimal:4',
        'total_return_amount' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
    ];

    // 1. 關聯費用 (對稱 SalesReturnFee)
    public function fees() { 
        return $this->hasMany(PurchaseReturnFee::class); 
    }

    // 2. 關聯明細 (對稱 SalesReturnItem)
    public function items() { 
        return $this->hasMany(PurchaseReturnItem::class); 
    }

    /**
     * 規範 1: 使用 BCMath 計算總額
     */
    public function refreshTotal()
    {
        $itemsSum = $this->items()->sum('subtotal');
        $feesSum = $this->fees()->sum('amount');
        
        // 採購退回邏輯：退回金額 = 商品總價 - 供應商收取的費用(如手續費)
        $this->total_return_amount = bcsub($itemsSum, $feesSum, 4);
        $this->save();
    }
}