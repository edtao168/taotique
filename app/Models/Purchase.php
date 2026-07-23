<?php

namespace App\Models;

use App\Enums\WorkflowStatus;
use App\Models\Account;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Traits\ShopScoped;
use App\Traits\HasAccounting;
use App\Traits\HasWorkflow;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Purchase extends Model
{
    use HasAccounting, HasWorkflow, ShopScoped;
	
	protected $fillable = [
        'shop_id',
		'purchase_number',
		'status',
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
        'total_base',
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
            'total_base' 	=> 'decimal:4',
			'subtotal' 		=> 'decimal:4',
            'total_amount' 	=> 'decimal:4',
			'shipping_fee'  => 'decimal:4',
			'tax'           => 'decimal:4',
            'other_fees'    => 'decimal:4',
            'discount'      => 'decimal:4',
            'total_amount'  => 'decimal:4',
			'status'        => WorkflowStatus::class,
        ];
    }

    // ============================================
    // 實作 HasWorkflow 的抽象方法
    // ============================================
    
    protected static function getStatusEnumClass(): string
    {
        return WorkflowStatus::class;
    }

    /**
     * 定義狀態轉換規則（一人店，跳過 pending）
     */
    protected function getTransitionRules(): array
    {
        return [
            // ===== 草稿 → 審核通過（一人店捷徑） =====
            [
                'from' => WorkflowStatus::DRAFT->value,
                'to' => WorkflowStatus::APPROVED->value,
                'event' => 'approve',
                'label' => '審核通過',
            ],
            
            // ===== 審核通過 → 入庫完成 =====
            [
                'from' => WorkflowStatus::APPROVED->value,
                'to' => WorkflowStatus::COMPLETED->value,
                'event' => 'stock_in',
                'label' => '完成入庫',
            ],
            
            // ===== 草稿 → 完成（自動入庫捷徑） =====
            [
                'from' => WorkflowStatus::DRAFT->value,
                'to' => WorkflowStatus::COMPLETED->value,
                'event' => 'auto_stock_in',
                'label' => '自動入庫',
            ],
            
            // ===== 取消 =====
            [
                'from' => WorkflowStatus::DRAFT->value,
                'to' => WorkflowStatus::CANCELLED->value,
                'event' => 'cancel',
                'label' => '取消單據',
            ],
            [
                'from' => WorkflowStatus::APPROVED->value,
                'to' => WorkflowStatus::CANCELLED->value,
                'event' => 'cancel',
                'label' => '取消單據',
            ],
        ];
    }
	
	// ============================================
    // 業務方法
    // ============================================

    /**
     * 審核採購單（一人店使用）
     */
    public function approve(User $actor): void
    {
        if ($this->status !== WorkflowStatus::DRAFT) {
            throw new \RuntimeException("目前狀態為「{$this->status->label()}」，無法審核。僅「草稿」可審核。");
        }
        
        $this->transitionTo(
            WorkflowStatus::APPROVED->value,
            'approve',
            $actor
        );
    }

    /**
     * 取消採購單
     */
    public function cancel(User $actor, ?string $reason = null): void
    {
        $allowed = [WorkflowStatus::DRAFT, WorkflowStatus::APPROVED];
        if (!in_array($this->status, $allowed)) {
            throw new \RuntimeException("目前狀態為「{$this->status->label()}」，無法取消。");
        }
        
        // 已入庫不能取消
        if ($this->stocked_in_at) {
            throw new \RuntimeException('已入庫的採購單無法取消，請走退貨流程');
        }
        
        // 已有退貨不能取消
        if ($this->hasReturnRecords()) {
            throw new \RuntimeException('已有退貨紀錄的採購單無法取消');
        }
        
        $this->transitionTo(
            WorkflowStatus::CANCELLED->value,
            'cancel',
            $actor,
            ['reason' => $reason]
        );
    }
	
	// =============================================
	
	/**
     * 判定採購單是否已鎖定 (不允許任何修改)
	 * 
	 * 鎖定條件：
	 * 1. 有進行中的退貨記錄
	 * 2. 已是最終狀態（已完成/已取消等）
     */
	public function isLocked(): bool
	{
		// 業務鎖定：有退貨記錄
		if ($this->hasReturnRecords()) {
			return true;
		}
		
		// 工作流鎖定：最終狀態
		return $this->isFinalized();
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
		return $this->isEditable() && !$this->hasReturnRecords();
	}
	
	// =========================================================================
	// SECTION: 會計自動規則對齊介面
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

	public static function generatePurchaseNumber(): string
    {
        return DB::transaction(function () {
            $prefix = Setting::get('po_prefix', 'PO-');
            $digits = (int) Setting::get('number_digits', 4);
            $datePart = now()->format('Ymd');
            $fullPrefix = $prefix . $datePart;

            $lastOrder = self::where('purchase_number', 'like', "{$fullPrefix}%")
                ->lockForUpdate()
                ->orderBy('purchase_number', 'desc')
                ->first();

            $nextNumber = $lastOrder ? (int) substr($lastOrder->purchase_number, -$digits) + 1 : 1;
            return $fullPrefix . str_pad($nextNumber, $digits, '0', STR_PAD_LEFT);
        });
    }

	/**
	 * 嚴謹的金額運算邏輯
	 */
	public function calculateAndSetTotals()
	{
		$netItems = bcsub($this->subtotal, $this->discount, 4);
		
		// 總額 = 商品淨額 + 運費 + 稅 + 其他費用
		$total = bcadd($netItems, $this->shipping_fee, 4);
		$total = bcadd($total, $this->tax, 4);
		$total = bcadd($total, $this->other_fees, 4);
		
		$this->total_amount = $total;
		$this->total_base = bcmul($this->total_amount, $this->exchange_rate, 4);
	}

    /**
     * 執行採購入庫厚邏輯（高併發庫存鎖定、加權平均成本計算、會計自動過帳）
     */
    public function processInbound(): void
    {
        if ($this->stocked_in_at) {
            throw new Exception("該採購單已執行過入庫，不可重複操作。");
        }
		
		$paymentMode = $paymentMode ?? 'prepaid';

        DB::transaction(function () use ($paymentMode) {
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
				$currentCostBase = bcmul($foreignPrice, $rate, 4);
				
				// 🛡️ 快照成本到 purchase_item 欄位（供未來追溯）
				//$item->unit_cost = $currentCostBase;
				//$item->save();

				$oldQty = (string)($inventory->quantity ?? '0');
				$oldCost = (string)($inventory->cost ?? '0.0000');
				$newQty = (string)($item->quantity ?? '0');

				$totalQty = bcadd($oldQty, $newQty, 4);

                if (bccomp($totalQty, '0', 4) > 0) {
                    $oldTotalAmount = bcmul($oldQty, $oldCost, 4);
                    $newTotalAmount = bcmul($newQty, $currentCostBase, 4);
                    $combinedAmount = bcadd($oldTotalAmount, $newTotalAmount, 4);
                    $newWeightedCost = bcdiv($combinedAmount, $totalQty, 4);
                } else {
                    $newWeightedCost = $currentCostBase;
                }

                $inventory->quantity = $totalQty;
                $inventory->cost = $newWeightedCost;
                $inventory->save();
				// 將新的加權平均成本同步到 Product 主表
				$product->updateWeightedAverageCost($newQty, $currentCostBase);
            }

            // 4. 變更採購單完工狀態快照
			$this->stocked_in_at = now();
			$this->save();
			
			// 根據付款模式決定 eventType
			$eventType = match($paymentMode) {
				'prepaid' => 'purchase_stock_in_prepaid',
				'cash'    => 'purchase_stock_in_cash',
				'credit'  => 'purchase_stock_in_credit',
				default   => 'purchase_stock_in_prepaid',
			};

			// 5. 呼叫 Trait 核心，連動 AccountingService 自動過帳
			 $journal = $this->postJournal($eventType, $paymentMode);
			
			if (!$journal) {
				throw new Exception("會計過帳失敗：請檢查 {$eventType} 規則配置。");
			}
			
			// 使用 workflow 轉為 completed
			if ($this->status === WorkflowStatus::DRAFT) {
				$this->transitionTo(
					WorkflowStatus::APPROVED->value,
					'approve',
					auth()->user()
				);
			}
			
			$this->transitionTo(
				WorkflowStatus::COMPLETED->value,
				'stock_in',
				auth()->user(),
				['payment_mode' => $paymentMode, 'journal_id' => $journal->id]
			);
			
			$this->stocked_in_at = now();
			$this->save();
			
			logger("採購單入庫完成", [
				'purchase_id'		=> $this->id,
				'purchase_number'	=> $this->purchase_number,
				'journal_id'		=> $journal->id,
				'payment_mode'		=> $paymentMode ?? 'default'
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
            'total_base'    => $this->total_base ?? '0.0000',
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
            'purchase_base_items'    => $this->total_amount ?? '0.0000',
            'purchase_base_tax'      => $this->tax ?? '0.0000',
            'purchase_base_shipping' => $this->shipping_fee ?? '0.0000',
            'purchase_base_other_fees' => $this->other_fees ?? '0.0000',
            'purchase_base_total'    => $this->total_amount ?? '0.0000',
            default => '0.0000',
        };
        
        return bcmul($foreignAmount, $rate, 4);
    }
  
	// =========================================================================
	// SECTION: 動態科目解析（專屬於 Purchase）- 參照 Sale 架構重構
	// =========================================================================

	public function resolveDynamicAccount(string $dynamicSpec, ?string $context = null): string
	{
		$parts = explode(':', $dynamicSpec);
		$domain = $parts[0] ?? '';
		$type = $parts[1] ?? '';
		$subType = $parts[2] ?? null;
		
		return match($domain) {
			'auto'     => $this->resolveAutoDynamicAccount($type, $subType),
			'purchase' => $this->resolvePurchaseDynamicAccount($type, $subType, $context),
			default => throw new \RuntimeException("未知的動態科目網域: {$domain}"),
		};
	}

	/**
	 * 處理 auto 域動態科目（與 Sale 保持一致）
	 */
	private function resolveAutoDynamicAccount(string $type, ?string $subType = null): string
	{
		return match($type) {
			'inventory' => config('business.accounting_accounts.cost.inventory', '1405'),
			'cost'      => config('business.accounting_accounts.cost.cost_of_goods_sold', '5401'),
			default => throw new \RuntimeException("未知的 auto 域動態科目類型: {$type}"),
		};
	}

	/**
	 * 處理 purchase 域動態科目
	 */
	private function resolvePurchaseDynamicAccount(string $type, ?string $subType = null, ?string $context = null): string
	{
		return match($type) {
			'payment' => $this->resolvePaymentAccount($context),
			'expense' => $this->resolveExpenseAccount($subType, $context),
			default => throw new \RuntimeException("未知的採購動態科目類型: {$type}"),
		};
	}

	/**
	 * 依付款方式動態決定貸方科目
	 * 
	 * 🎯 核心：根據付款方式和交易模式（context）決定科目
	 * context 可能的值：
	 * - 'prepaid': 先付款後發貨 → 貸：預付賬款
	 * - 'cash': 一手交錢一手交貨 → 貸：銀行存款/現金
	 * - 'credit': 先貨後款/月結 → 貸：應付賬款
	 * - null: 根據 payment_method 判斷
	 */
	private function resolvePaymentAccount(?string $context = null): string
	{
		// 如果有 context，優先根據交易模式決定
		if ($context) {
			return match($context) {
				'prepaid' => '1123',      // 預付賬款（資產類）
				'cash'    => $this->getCashAccountByPaymentMethod(),
				'credit'  => $this->getCreditAccountByPaymentMethod(),
				default   => $this->getDefaultPaymentAccount(),
			};
		}
		
		// 無 context 時，根據 payment_method 欄位判斷
		return $this->getDefaultPaymentAccount();
	}

	/**
	 * 根據付款方式取得現金/銀行科目（一手交錢）
	 */
	private function getCashAccountByPaymentMethod(): string
	{
		$paymentMethod = $this->payment_method ?? 'credit';
		
		return match($paymentMethod) {
			'cash_twd', 'cash'    => '100101',   // 門市現金
			'bank_cathay'         => '100201',   // 銀行存款-國泰世華
			'wechat_pay'          => '100207',   // 銀行存款-微信
			'alipay'              => '100208',   // 銀行存款-支付寶
			default               => '100201',   // 預設銀行存款
		};
	}

	/**
	 * 根據付款方式取得應付帳款科目（賒購）
	 */
	private function getCreditAccountByPaymentMethod(): string
	{
		$paymentMethod = $this->payment_method ?? 'credit';
		
		return match($paymentMethod) {
			'china_ap'            => '220201',   // 應付帳款-大陸廠商
			'credit'              => '2202',     // 應付帳款（一般）
			default               => '2202',     // 預設應付帳款
		};
	}

	/**
	 * 取得預設的付款科目（向後兼容）
	 */
	private function getDefaultPaymentAccount(): string
	{
		$paymentMethod = $this->payment_method ?? 'credit';
		
		return match($paymentMethod) {
			'cash_twd', 'cash'    => '100101',   // 門市現金
			'bank_cathay'         => '100201',   // 銀行存款
			'wechat_pay'          => '100207',   // 銀行存款-微信
			'alipay'              => '100208',   // 銀行存款-支付寶
			'china_ap'            => '220201',   // 應付帳款-大陸廠商（月結）
			'credit'              => '2202',     // 應付帳款（一般）
			default               => '2202',
		};
	}

	/**
	 * 依費用類型動態決定借方科目
	 * 
	 * 🎯 根據《小企業會計準則》，採購附加費應計入存貨成本
	 * 所以預設都進庫存商品（1405），可依費用類型細分
	 */
	private function resolveExpenseAccount(?string $subType = null, ?string $context = null): string
	{
		// 可依費用類型細分到不同的庫存明細科目
		return match($subType) {
			'shipping', 'freight' => '1405',   // 運費 → 庫存商品-運費分攤
			'tariff', 'duty'      => '1405',   // 關稅 → 庫存商品-關稅分攤
			'handling'            => '1405',   // 手續費 → 庫存商品-手續費分攤
			default               => '1405',   // 其他 → 庫存商品-其他
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