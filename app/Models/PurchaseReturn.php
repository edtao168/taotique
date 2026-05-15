<?php

// 檔案路徑：app/Models/PurchaseReturn.php

namespace App\Models;

use App\Traits\HasShop; // 規範 5: 多店預留
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
	
		/**
     * 產生採購退回單號碼 (使用統一的 Setting 方法)
     */
    public static function generatePurchaseReturnNumber(): string
    {
        // 從 settings 表抓取前綴，預設 PO-
		$prefix = Setting::get('pr_prefix', 'PR-'); 
		$date = now()->format('Ymd');
		
		// 取得當日最後一筆序號
		$lastOrder = self::where('return_no', 'like', $prefix . $date . '%')
			->orderBy('id', 'desc')
			->first();
			
		$sequence = $lastOrder ? (int)substr($lastOrder->return_no, -Setting::get('number_digits', 4)) + 1 : 1;
    $digits = Setting::get('number_digits', 4);
		
		return $prefix . $date . str_pad($sequence, $digits, '0', STR_PAD_LEFT);
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
	
	/**
     * 定義關聯：退貨單 屬於 採購單
     */
    public function purchase(): BelongsTo
    {
        // 確保欄位名稱 purchase_id 與資料庫一致
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    /**
     * 定義關聯：退貨單 有多個 退貨明細
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class, 'purchase_return_id');
    }

    /**
     * 定義關聯：退貨單 屬於 供應商 (透過採購單)
     * 這在顯示列表時非常有用，可以直接用 $return->supplier->name
     */
    public function supplier(): BelongsTo
    {
        // 如果您的 purchase_returns 表裡沒有 supplier_id，
        // 您也可以在 Blade 中直接透過 $return->purchase->supplier 顯示
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
	
		/**
		 * 建立者
		 */
		 public function user(): BelongsTo
		{
			return $this->belongsTo(User::class);
		}
}