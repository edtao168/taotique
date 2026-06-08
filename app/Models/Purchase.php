<?php

namespace App\Models;

use App\Models\Account;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Traits\HasAccounting;
use Exception;
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
			'shipping_fee'  => 'decimal:4',
			'tax'           => 'decimal:4',
            'other_fees'    => 'decimal:4',
            'discount'      => 'decimal:4',
            'total_amount'  => 'decimal:4',
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
     * 實作會計動態規則接口
     */
    public function getAccountingRules(string $eventType): array
	{
		if ($eventType !== 'purchase_stock_in') {
			return [];
		}

		// 🎯 回傳格式須符合 AccountingService 預期：包含 lines 索引
		return [
			'lines' => [
				// 借方：庫存商品（動態依商品類別）
				[
					'account_code'   => 'DYNAMIC:auto:inventory',
					'entry_type'     => 'debit',
					'amount_source'  => AmountSource::PURCHASE_BASE_ITEMS->value,
					'ratio'          => '1.0000',
					'is_active'      => true,
					'sort_order'     => 1,
				],
				// 借方：進項稅額（固定科目）
				[
					'account_code'   => '222101',  // 應交稅費-應交增值稅(進項)
					'entry_type'     => 'debit',
					'amount_source'  => AmountSource::PURCHASE_BASE_TAX->value,
					'ratio'          => '1.0000',
					'is_active'      => true,
					'sort_order'     => 2,
				],
				// 借方：運費附加費（動態依費用類型）
				[
					'account_code'   => 'DYNAMIC:purchase:expense',
					'entry_type'     => 'debit',
					'amount_source'  => AmountSource::PURCHASE_BASE_SHIPPING->value,
					'ratio'          => '1.0000',
					'is_active'      => true,
					'sort_order'     => 3,
				],
				// 借方：其他附加費
				[
					'account_code'   => 'DYNAMIC:purchase:expense',
					'entry_type'     => 'debit',
					'amount_source'  => AmountSource::PURCHASE_BASE_OTHER_FEES->value,
					'ratio'          => '1.0000',
					'is_active'      => true,
					'sort_order'     => 4,
				],
				// 貸方：應付帳款/付款管道（動態）
				[
					'account_code'   => 'DYNAMIC:purchase:payment',
					'entry_type'     => 'credit',
					'amount_source'  => AmountSource::PURCHASE_BASE_TOTAL->value,
					'ratio'          => '1.0000',
					'is_active'      => true,
					'sort_order'     => 5,
				],
			],
		];
	}

    /**
     * 執行採購入庫厚邏輯（高併發庫存鎖定、加權平均成本計算、會計自動過帳）
     */
    public function processInbound(): void
    {
        if ($this->stocked_in_at) {
            throw new Exception("該採購單已執行過入庫，不可重複操作。");
        }

        DB::transaction(function () {
            $shopId = $this->shop_id ?? 1;
            $warehouseId = $this->warehouse_id;
            $rate = $this->exchange_rate ?? '1.0000';

			// 1. 預先載入 items 與 product，避免 N+1 且確保成本計算正確
			$this->load(['items.product']);
		
            // 2. 逐筆遍歷採購明細，對相關資料實施 lockForUpdate() 防併發穿透
            foreach ($this->items as $item) {
                $product = Product::where('id', $item->product_id)->lockForUpdate()->first();
				
				if (!$product) {
					throw new Exception("商品 ID {$item->product_id} 不存在，入庫中斷。");
				}
                
                // 獲取或創建當前分店與歸屬倉庫的庫存記錄
                $inventory = Inventory::where([
                    'shop_id'      => $shopId,
                    'warehouse_id' => $warehouseId,
                    'product_id'   => $item->product_id
                ])->lockForUpdate()->first();

                if (!$inventory) {
                    $inventory = new Inventory([
                        'shop_id'      => $shopId,
                        'warehouse_id' => $warehouseId,
                        'product_id'   => $item->product_id,
                        'quantity'     => 0,
                        'cost'         => '0.0000'
                    ]);
                }

                // 3. 🎯 成本計算：外幣單價 × 匯率 = 本幣採購成本
				$foreignPrice = (string)($item->foreign_price ?? $item->price ?? '0.0000');
				$currentCostTwd = bcmul($foreignPrice, $rate, 4);
				
				// 🛡️ 快照成本到 purchase_item 欄位（供未來追溯）
				//$item->unit_cost = $currentCostTwd;
				//$item->save();

				$oldQty = (string)($inventory->quantity ?? '0');
				$oldCost = (string)($inventory->cost ?? '0.0000');
				$newQty = (string)($item->quantity ?? '0');

				$totalQty = bcadd($oldQty, $newQty, 4);

                if (bccomp($totalQty, '0', 4) > 0) {
                    $oldTotalAmount = bcmul($oldQty, $oldCost, 4);
                    $newTotalAmount = bcmul($newQty, $currentCostTwd, 4);
                    $combinedAmount = bcadd($oldTotalAmount, $newTotalAmount, 4);
                    $newWeightedCost = bcdiv($combinedAmount, $totalQty, 4);
                } else {
                    $newWeightedCost = $currentCostTwd;
                }

                $inventory->quantity = $totalQty;
                $inventory->cost = $newWeightedCost;
                $inventory->save();
            }

            // 4. 變更採購單完工狀態快照
			$this->stocked_in_at = now();
			$this->save();

			// 5. 🎯 呼叫 Trait 核心，連動 AccountingService 自動過帳
			$journal = $this->postJournal('purchase_stock_in');
			
			if (!$journal) {
				throw new Exception("會計過帳失敗：postJournal 返回 null，請檢查 purchase_stock_in 規則配置。");
			}
			
			Log::info("採購單入庫完成", [
				'purchase_id' => $this->id,
				'purchase_number' => $this->purchase_number,
				'journal_id' => $journal->id
			]);
        });
    }
	
	/**
     * 取得庫存科目ID（可依供應商或商品類型動態決定）
     */
    /* protected function getInventoryAccountId(): int
    {
        // 預設：庫存商品 (1405)
        // [TECH-DEBT] 未來可擴充為依商品類型選擇科目
        return Account::where('code', '1405')->first()?->id ?? 1405;
    } */
	protected function getInventoryAccountCode(Product $product): string
{
    $category = $product->category_code; // 假設產品有分類
    
    return match($category) {
        'bracelet' => '140502',  // 手鍊手鐲
        'earring'  => '140505',  // 耳環
        'general'  => '140503',  // 百貨
        'package'  => '140901',  // 禮盒包材
        'part'     => '140509',  // 配件半成品
        'pendant'  => '140501',  // 吊墜項鍊
        'ring'     => '140506',  // 戒指
        default    => '140599',  // 其他庫存
    };
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
	
	    // =========================================================================
    // SECTION: 會計金額解析（專屬於 Purchase）
    // =========================================================================
    
    /**
     * 解析金額來源（供 AccountingService 呼叫）
     */
    public function getAmountFromSource(string $amountSource, ?string $eventType = null): string
    {
        // 採購本幣換算金額（優先處理）
        if (str_starts_with($amountSource, 'purchase_base_')) {
            return $this->resolveBaseAmount($amountSource);
        }
        
        // 採購外幣原始金額
        return match($amountSource) {
            'subtotal'     => $this->subtotal ?? '0.0000',
            'shipping_fee' => $this->shipping_fee ?? '0.0000',
            'tax'          => $this->tax ?? '0.0000',
            'other_fees'   => $this->other_fees ?? '0.0000',
            'discount'     => $this->discount ?? '0.0000',
            'total_amount' => $this->total_amount ?? '0.0000',
            'total_twd'    => $this->total_twd ?? '0.0000',
            default        => $this->getAttribute($amountSource) ?? '0.0000',
        };
    }
    
    /**
     * 解析採購本幣換算後金額
     */
    private function resolveBaseAmount(string $amountSource): string
    {
        $rate = (string)($this->exchange_rate ?? '1.0000');
        
        $foreignAmount = match($amountSource) {
            'purchase_base_items'    => $this->subtotal ?? '0.0000',
            'purchase_base_tax'      => $this->tax ?? '0.0000',
            'purchase_base_shipping' => $this->shipping_fee ?? '0.0000',
            'purchase_base_other_fees' => $this->other_fees ?? '0.0000',
            'purchase_base_total'    => $this->total_amount ?? '0.0000',
            default => '0.0000',
        };
        
        return bcmul($foreignAmount, $rate, 4);
    }
    
    // =========================================================================
    // SECTION: 動態科目解析（專屬於 Purchase）
    // =========================================================================
    public static function getDocumentNumberField(): string
    {
        return 'purchase_number';
    }
    
    public function getDocumentNumber(): string
    {
        return $this->purchase_number ?? 'PO-' . $this->id;
    }
    
    public static function getReferenceType(): string
    {
        return 'purchase';
    }
	
/**
 * 解析動態科目代碼（供 AccountingService 呼叫）
 */
public function resolveDynamicAccount(string $dynamicSpec, ?string $context = null): string
{
    $parts = explode(':', $dynamicSpec);
    $domain = $parts[0] ?? '';
    $type = $parts[1] ?? '';
    
    return match($domain) {
        'auto'     => $this->resolveAutoDomainAccount($type),
        'purchase' => $this->resolvePurchaseDomainAccount($type, $context),
        default => throw new \RuntimeException("未知的動態科目網域: {$domain}"),
    };
}

/**
 * 處理 auto 域動態科目（庫存相關）
 */
private function resolveAutoDomainAccount(string $type): string
{
    if ($type !== 'inventory') {
        throw new \RuntimeException("未知的 auto 域動態科目類型: {$type}");
    }
    
    // 從第一個採購明細取得商品類別
    $firstItem = $this->items->first();
    $category = $firstItem?->product?->category_code ?? 'default';
    
    return match($category) {
        '1', 'pendant'  => '140501',  // 吊墜項鍊
        '2', 'bracelet' => '140502',  // 手鍊手鐲
        '3', 'earring'  => '140505',  // 耳環
        '4', 'ring'     => '140506',  // 戒指
        '5', 'general'  => '140503',  // 百貨
        '6', 'package'  => '140901',  // 禮盒包材
        '7', 'part'     => '140509',  // 配件半成品
        default         => '140599',  // 其他庫存
    };
}

/**
 * 處理 purchase 域動態科目
 */
private function resolvePurchaseDomainAccount(string $type, ?string $context): string
{
    return match($type) {
        'payment' => $this->resolvePaymentAccount(),
        'expense' => $this->resolveExpenseAccount($context),
        default => throw new \RuntimeException("未知的採購動態科目類型: {$type}"),
    };
}

/**
 * 依付款方式動態決定應付帳款科目
 */
private function resolvePaymentAccount(): string
{
    $paymentMethod = $this->payment_method ?? 'credit';
    
    return match($paymentMethod) {
        'cash_twd', 'cash'    => '1001',     // 庫存現金
        'bank_cathay'         => '100201',   // 銀行存款-新台幣帳戶
        'wechat_pay'          => '100207',   // 銀行存款-微信
        'alipay'              => '100208',   // 銀行存款-支付寶
        'credit'              => '2202',     // 應付帳款（賒購）
        'china_ap'            => '220201',   // 應付帳款-大陸廠商
        default               => '2202',     // 預設應付帳款
    };
}

/**
 * 依費用類型動態決定科目
 */
private function resolveExpenseAccount(?string $context): string
{
    return match($context) {
        'tariff', 'duty' => '140502',   // 關稅計入庫存成本
        'freight'        => '140503',   // 運費計入庫存成本
        'handling'       => '140504',   // 手續費計入庫存成本
        default          => '140599',   // 其他費用計入庫存成本
    };
}

    // 分店    
	 public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
	// 明細
    public function items(): HasMany { return $this->hasMany(PurchaseItem::class, 'purchase_id'); }
	// 供應商
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
	// 倉庫
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
	// 使用者
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
	// 採退
    public function returns(): HasMany { return $this->hasMany(PurchaseReturn::class, 'purchase_id'); }
		
	/**
     * 🛡️ 防禦性虛擬關聯：防止 HasAccounting Trait 強制預載入 fees 時崩潰
     */
    public function fees(): HasMany
    {
        // 採購單費用直接記錄於主表欄位（shipping_fee, tax, other_fees），故此處回傳一個必為空的 HasMany 關聯
        return $this->hasMany(PurchaseItem::class, 'purchase_id')->whereRaw('1 = 0');
    }
}