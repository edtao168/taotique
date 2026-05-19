<?php

namespace App\Models;

use App\Models\Account;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Traits\HasAccounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Purchase extends Model
{
    use HasAccounting;
	
	protected $fillable = [
        'shop_id',
		'purchase_number',
        'supplier_id',
		'payment_method',
        'user_id',
		'warehouse_id',
        'currency',
        'exchange_rate',
		'subtotal',
		'shipping_fee',
        'tax',
        'other_fees',
        'discount',
        'total_amount',
        'total_twd',
        'purchased_at',
		'stocked_in_at',
        'remark'
    ];

    protected function casts(): array
    {
        return [
            'shop_id'       => 'integer',
			'purchased_at' 	=> 'datetime',
			'stocked_in_at' => 'datetime',
            'exchange_rate' => 'decimal:4',
            'total_twd' 	=> 'decimal:4',
			'subtotal' 		=> 'decimal:4',
            'total_amount' 	=> 'decimal:4',
        ];
    }
	
	/**
     * 判定採購單是否已鎖定 (不允許任何修改)
     */
	public function isLocked(): bool
	{
		return $this->returns()->whereIn('status', ['pending', 'approved', 'completed'])->exists();
	}
	
	/**
     * 判斷是否有採購退貨紀錄
     */
    public function hasReturnRecords(): bool
    {
        // 排除已取消的退貨單（若有狀態定義）
        return $this->returns()
            ->whereIn('status', ['pending', 'completed']) 
            ->exists();
    }

    /**
     * 判斷單據是否允許異動
     */
    public function canBeModified(): bool
    {
        return !$this->hasReturnRecords();
    }

    // --- 新增的單號生成邏輯 (參考 Sale.php) ---
    protected static function booted()
    {
        static::creating(function ($purchase) {
            if (empty($purchase->shop_id)) {
                $purchase->shop_id = 1;
            }
			// 如果儲存時 purchase_number 是空的，則自動生成
            if (empty($purchase->purchase_number)) {
                $purchase->purchase_number = self::generatePurchaseNumber();
            }
        });
    }

	/**
	 * 嚴謹的金額運算邏輯
	 */
	public function calculateAndSetTotals()
	{
		// 1. 計算原始幣別總額：(小計 + 運費) - 折扣
		$total = bcadd($this->subtotal, $this->shipping_fee, 4);
		$total = bcadd($total, $this->tax, 4);
        $total = bcadd($total, $this->other_fees, 4);
		$this->total_amount = bcsub($total, $this->discount, 4);

		// 2. 換算本幣：total_amount * exchange_rate
		$this->total_twd = bcmul($this->total_amount, $this->exchange_rate, 4);
	}

    /**
     * 產生採購單號碼 (使用統一的 Setting 方法)
     */
    public static function generatePurchaseNumber(): string
    {
        // 從 settings 表抓取前綴，預設 PO-
		$prefix = Setting::get('po_prefix', 'PO-'); 
		$date = now()->format('Ymd');
		
		// 取得當日最後一筆序號
		$lastOrder = self::whereDate('created_at', now()->toDateString())
			->orderBy('id', 'desc')
			->first();
			
		$sequence = $lastOrder ? (int)substr($lastOrder->purchase_number, -4) + 1 : 1;
		// ✅ 強制轉為整數，預設 4 位數
		$digits = (int) Setting::get('number_digits', 4);
		
		// 防呆：確保 digits 在 1~10 之間
		$digits = max(1, min(10, $digits));    
		
		return $prefix . $date . str_pad($sequence, $digits, '0', STR_PAD_LEFT);
    }

    /**
	 * 執行採購單入庫：處理明細、換算匯率、更新庫存與加權成本
	 */
	public function processInbound(): void
	{
		if ($this->stocked_in_at) {
            throw new \Exception("此單據已入庫。");
        }
		
        // 外部事務併發控制，重試3次
        DB::transaction(function () {
            
            // 重新鎖定主表，防併發重複入庫
            $purchase = self::where('id', $this->id)->lockForUpdate()->firstOrFail();
            if ($purchase->stocked_in_at) {
                throw new \Exception("此單據已被其他併發進程入庫。");
            }

            // 遍歷此採購單下所有的明細
            foreach ($purchase->items as $item) {
                
                // 1. 併發控制：悲觀鎖鎖定現有庫存紀錄（嚴格檢查 shop_id, warehouse_id, product_id）
                $inventory = Inventory::where('shop_id', $purchase->shop_id ?? 1)
                    ->where('warehouse_id', $item->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory) {
                    // 若有數據，庫存數量追加（強制呼叫 bcadd，保留4位小數）
                    $newQty = bcadd($inventory->quantity, $item->quantity, 4);
                    $inventory->update([
                        'quantity' => $newQty,
                        'cost'     => $item->cost_twd // 隨入庫更新最新批次成本快照
                    ]);
                } else {
                    // 若無數據，新增庫存紀錄
                    Inventory::create([
                        'shop_id'      => $purchase->shop_id ?? 1,
                        'product_id'   => $item->product_id,
                        'warehouse_id' => $item->warehouse_id,
                        'supplier_id'  => $purchase->supplier_id,
                        'quantity'     => $item->quantity,
                        'cost'         => $item->cost_twd,
                        'status'       => 'in_stock'
                    ]);
                }

                // 2. 更新產品加權平均成本 (WAC)
                $product = Product::where('id', $item->product_id)->lockForUpdate()->firstOrFail();
                $product->updateWeightedAverageCost($item->quantity, $item->cost_twd);
            }
			
            // 3. 🎯 金流會計自動化：依據 payment_method 動態定錨會計科目
            $methodConfig = config("business.purchase_methods.{$purchase->payment_method}");
            if (!$methodConfig) {
                throw new \Exception("未知的採購付款方式：{$purchase->payment_method}");
            }

            $creditAccountCode = $methodConfig['default_account'];
            $debitAccountCode = config('business.accounting_rules.purchase_inbound.debit_code', '1405');

            $debitAccount = Account::where('code', $debitAccountCode)->first();
            $creditAccount = Account::where('code', $creditAccountCode)->first();

            if (!$debitAccount || !$creditAccount) {
                throw new \Exception("會計自動過帳失敗！找不到對應的科目代碼：借方[{$debitAccountCode}] 或 貸方[{$creditAccountCode}]。");
            }

            // 4. 呼叫 HasAccounting Trait 產生日記帳傳票
            $this->createPurchaseJournal(
                entryDate: now()->toDateString(),
                inventoryAccountId: $debitAccount->id, // 借：1405 庫存商品
                payableAccountId: $creditAccount->id    // 貸：動態對照科目
            );

            // 4. 標記主表為已入庫
			$this->update(['stocked_in_at' => now()]);
		}, 3);
	}
	
	/**
     * 取得庫存科目ID（可依供應商或商品類型動態決定）
     */
    protected function getInventoryAccountId(): int
    {
        // 預設：庫存商品 (1405)
        // [TECH-DEBT] 未來可擴充為依商品類型選擇科目
        return Account::where('code', '1405')->first()?->id ?? 1405;
    }

    /**
     * 取得應付帳款科目ID
     */
    protected function getPayableAccountId(): int
    {
        // 預設：應付帳款 (2202)
        //return Account::where('code', '2202')->first()?->id ?? 1;
		return Account::where('code', '2202')->first()?->id ?? 2202;
    }
	
	/**
     * 分店
     */
	public function shop(): BelongsTo
	{		
		return $this->belongsTo(Shop::class);
	}
	
	/**
     * 明細
     */	 
	public function items(): HasMany
	{
		return $this->hasMany(PurchaseItem::class); 
	}
	
	/**
     * 供應商
     */
	public function supplier(): BelongsTo
	{		
		return $this->belongsTo(Supplier::class);
	}
	
	/**
     * 倉庫
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
	
	/**
     * 建立者
     */
	 public function user(): BelongsTo
	{
		// 假設您的 sales 表中有 user_id 欄位
		return $this->belongsTo(User::class);
	}
	
	/**
     * 定義與採購退貨單的關聯
     */
    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class, 'purchase_id');
    }
}