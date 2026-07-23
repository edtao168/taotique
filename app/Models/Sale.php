<?php
// 檔案路徑：app/Models/Sale.php

namespace App\Models;

use App\Enums\WorkflowStatus;
use App\Models\Setting;
use App\Models\AccountingRule;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\SaleFee;
use App\Models\SalesReturn;
use App\Models\Journal;
use App\Models\Traits\ShopScoped;
use App\Services\AccountingService;
use App\Traits\HasAccountAndDynamicSearch;
use App\Traits\HasAccounting;
use App\Traits\HasWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Sale extends Model
{
    use HasAccounting, 
        HasAccountAndDynamicSearch, 
        HasWorkflow,
		ShopScoped;

    protected $guarded = [];

    protected $casts = [
        'shop_id'          => 'integer',
        'warehouse_id'     => 'integer',
        'sold_at'          => 'datetime:Y-m-d H:i:s',
        'stocked_out_at'   => 'datetime:Y-m-d H:i:s',
        'exchange_rate'    => 'decimal:4',
        'subtotal'         => 'decimal:4',
        'status'           => WorkflowStatus::class,
    ];

    private static ?array $feeTypesCache = null;
    private const DECIMAL_PRECISION = 4;
	
    /**
     * 實作動態科目映射（銷售專用）
     */
    protected function getDynamicAccountMapping(): array
    {
        return [
            'DYNAMIC:sale:payment' => $this->getPaymentAccount(),
			'DYNAMIC:sale:settle' => $this->getSettleAccount(), 
            'DYNAMIC:sale:revenue' => $this->getRevenueAccount(),
            'DYNAMIC:auto:inventory' => '1405',
            'DYNAMIC:auto:cost' => '5401',
            'DYNAMIC:sale:channel_fee' => '5601',
            'DYNAMIC:sale:discount' => '5602',
        ];
    }
    
	/**
	 * 取得結算最終收款帳戶（借方）
	 * 根據通路決定錢最後匯到哪個銀行
	 */
	protected function getSettleAccount(): string
	{
		$settlementAccounts = config('business.settlement_accounts', []);
    
		if (!isset($settlementAccounts[$this->channel_id])) {
			throw new \RuntimeException(
				"通路 ID [{$this->channel_id}] 未定義結算帳戶，不應執行結算。"
			);
		}
		
		return $settlementAccounts[$this->channel_id];
	}

	/**
	 * 取得付款帳戶科目
	 */
    protected function getPaymentAccount(): string
    {
        // 從 config 讀取映射
		$mapping = config('business.payment_accounts', []);
		
		// 正規化 payment_method（支援新舊命名）
		$payment = match($this->payment_method) {
			'bank_transfer' => 'transfer',
			default => $this->payment_method,
		};
		
		return $mapping[$payment] ?? '112202';
    }
    
    /**
	 * 取得收入帳戶科目
	 */
	protected function getRevenueAccount(): string
    {
        $channel = $this->getChannelCode();
		$revenueConfig = config('business.accounting_accounts.revenue', []);
		
		return $revenueConfig[$channel]['code'] 
			?? $revenueConfig['default']['code'] 
			?? '500101';
    }

    // =========================================================================
    // SECTION: 前端 Mary UI 表格與卡片渲染 Accessors
    // =========================================================================

    public function getSoldDateAttribute(): string
    {
        return $this->sold_at ? $this->sold_at->format('Y-m-d') : '';
    }

    public function getSoldTimeAttribute(): string
    {
        return $this->sold_at ? $this->sold_at->format('H:i') : '';
    }

    public function getPaymentMethodNameAttribute(): string
    {
        return collect(config('business.payment_methods'))
            ->firstWhere('id', $this->payment_method)['name'] ?? $this->payment_method;
    }

    // =========================================================================
    // SECTION: 全通路通用動態過帳金額清洗 Accessors (BCMath 嚴謹運算)
    // =========================================================================
	public function getCustomerTotalAttribute(): string
	{
		$subtotal = (string) ($this->subtotal ?? '0.0000');
		$sad = $subtotal;  // 從 subtotal 開始
		
		$feeTypes = config('business.fee_types', []);
		
		foreach ($feeTypes as $feeType => $config) {
			$target = $config['target'] ?? '';
			
			// ✅ 只處理影響 customer 的費用
			if (!in_array($target, ['customer', 'both', 'revenue_adjustment'])) {
				continue;
			}
			
			$amount = $this->getFeeTotal($feeType);
			if (bccomp($amount, '0', 4) === 0) {
				continue;
			}
			
			$operator = $config['operator'] ?? 'add';
			if ($operator === 'add') {
				$sad = bcadd($sad, $amount, 4);
			} elseif ($operator === 'sub') {
				$sad = bcsub($sad, $amount, 4);
			}
		}
		
		// ✅ 稅金另外處理（因為 tax 的 target 通常不是 customer）
		$tax = $this->getFeeTotal('tax_amount');
		$sad = bcadd($sad, $tax, 4);
		
		return $sad;
	}

    // =========================================================================
    // SECTION: 會計自動規則對齊介面
    // =========================================================================

    public static function getDocumentNumberField(): string
    {
        return 'invoice_number';
    }

    public function getDocumentNumber(): string
    {
        return $this->invoice_number ?? 'SO-' . $this->id;
    }

    public static function getReferenceType(): string
    {
        return 'sale';
    }

    // =========================================================================
    // SECTION: 🆕 工作流相關（加入 HasWorkflow 後需要實作的方法）
    // =========================================================================

    /**
     * 取得對應的 Enum class
     */
    protected static function getStatusEnumClass(): string
    {
        return WorkflowStatus::class;
    }

    /**
     * 定義狀態轉換規則
     */
	protected function getTransitionRules(): array
	{
		return [
			// ===== 審核流程 =====
			['from' => 'pending', 'to' => 'approved', 'event' => 'approve', 'label' => '審核通過'],
			
			// ===== 捷徑 =====
			['from' => 'draft', 'to' => 'approved', 'event' => 'approve', 'label' => '審核通過'],
			['from' => 'draft', 'to' => 'completed', 'event' => 'stock_out', 'label' => '過帳完成'],
			
			// ===== 出貨 =====
			['from' => 'approved', 'to' => 'completed', 'event' => 'stock_out', 'label' => '出貨完成'],
			
			// ===== 結算 =====
			['from' => 'approved', 'to' => 'settled', 'event' => 'settle', 'label' => '銷售結算'],
			
			// ===== 結案（不涉及金流）=====
			['from' => 'approved', 'to' => 'completed', 'event' => 'complete', 'label' => '完成結案'],
			
			// ===== 取消 =====
			['from' => 'draft', 'to' => 'cancelled', 'event' => 'cancel', 'label' => '取消'],
			['from' => 'pending', 'to' => 'cancelled', 'event' => 'cancel', 'label' => '取消'],
			['from' => 'approved', 'to' => 'cancelled', 'event' => 'cancel', 'label' => '取消'],
		];
	}

    // =========================================================================
    // SECTION: 🆕 業務方法（狀態轉換）
    // =========================================================================

    /**
     * 取消訂單
     */
    public function cancel(User $actor, ?string $reason = null): void
    {
        $allowed = [WorkflowStatus::DRAFT, WorkflowStatus::PENDING, WorkflowStatus::APPROVED];
        if (!in_array($this->status, $allowed)) {
            throw new \RuntimeException('此狀態無法取消');
        }
        
        // 驗證：已出貨不能取消
		if ($this->stocked_out_at) {
			throw new \RuntimeException('已出貨的訂單無法取消，請走退貨流程');
		}
		
		// 驗證：已有退貨不能取消
		if ($this->hasReturnRecords()) {
			throw new \RuntimeException('已有退貨紀錄的訂單無法取消');
		}
        
        $this->transitionTo('cancelled', 'cancel', $actor, ['reason' => $reason]);
    }


	// =========================================================================
	// SECTION: 🆕 清算相關業務方法
	// =========================================================================

	/**
	 * 銷售結算（approved → settled）
	 * 
	 * 意義：將銷售款項結算至最終收款帳戶
	 * 會計：借：DYNAMIC:sale:payment / 貸：101202 蝦皮錢包
	 * 
	 * @param array $data 包含 amount, payment_method, settled_at, fee
	 */
	public function settle(User $actor, array $data = []): void
	{
		if ($this->status !== WorkflowStatus::APPROVED) {
			throw new \RuntimeException('只有已審核的訂單可以結算');
		}
		
		// ✅ 防呆：現金銷售不需要結算
		if ($this->payment_method === 'cash') {
			throw new \RuntimeException('現金銷售不需要結算，請使用「完成結案」');
		}
		
		// ✅ 防呆：必須已出庫
		if (!$this->stocked_out_at) {
			throw new \RuntimeException('訂單尚未出貨，無法結算');
		}
		
		// ✅ 防呆：如果有退貨
		if ($this->hasReturnRecords()) {
			throw new \RuntimeException('此訂單已有退貨紀錄，無法結算');
		}
	
		$amount = $data['amount'] ?? $this->customer_total;
		if (bccomp((string)$amount, '0', 4) <= 0) {
			throw new \RuntimeException('結算金額必須大於 0');
		}
		
		// 如果有指定 payment_method，暫時更新以便動態科目解析
		if (isset($data['payment_method'])) {
			$this->payment_method = $data['payment_method'];
		}
		
		DB::transaction(function () use ($actor, $data, $amount) {
			// 1. 狀態轉換
			$this->transitionTo(
				WorkflowStatus::SETTLED->value,
				'settle',
				$actor,
				[
					'amount' => $amount,
					'payment_method' => $data['payment_method'] ?? $this->payment_method,
					'settled_at' => $data['settled_at'] ?? now(),
					'fee' => $data['fee'] ?? 0,
				]
			);
			
			// 2. 產生傳票：借：DYNAMIC:sale:payment / 貸：101202 蝦皮錢包
			$this->postJournal('sale_settlement');
		}, 3);
	}

	/**
	 * 判斷此單據是否需要走平台撥款結算流程
	 */
	public function needsSettlement(): bool
	{
		$method = strtolower(trim($this->payment_method));
				
		// 現金不需要結算
		if ($method === 'cash') {
			return false;
		}
		
		// 只有「有定義結算帳戶」的通路才需要結算
		$settlementAccounts = config('business.settlement_accounts', []);
		return isset($settlementAccounts[$this->channel_id]);
	}

    // =========================================================================
    // SECTION: 庫存管理核心控制
    // =========================================================================

    /**
     * 執行銷售單實體出庫（對外公開方法，保持向後相容）
     */
	public function processStockOut(bool $allowNegative = false): void
	{
		// ✅ 前置檢查（不變更狀態）
		if ($this->stocked_out_at) {
			throw new \Exception("銷售單 {$this->invoice_number} 已完成出庫，請勿重複執行。");
		}

		if ($this->hasReturnRecords()) {
			throw new \Exception("銷售單 {$this->invoice_number} 已有退貨紀錄，無法出庫。");
		}
		
		// ✅ 只允許草稿或待審核狀態執行過帳
		if (!in_array($this->status, [WorkflowStatus::DRAFT, WorkflowStatus::PENDING])) {
			throw new \Exception("訂單狀態為「{$this->status->label()}」，無法過帳。僅「草稿」或「待審核」狀態可過帳。");
		}

		// ✅ 驗證過帳規則是否存在
		$this->validateAccountingRules();

		// ✅ 所有檢查通過後，在 Transaction 內執行
		DB::transaction(function () use ($allowNegative) {
			// ============================================================
			// 1. 執行庫存扣減
			// ============================================================
			$this->deductInventory($this->getCurrentItemsQuantity(), $allowNegative);

			// 重新載入最新關聯數據
			$this->fresh(['items.product', 'fees', 'channel']);

			// ============================================================
			// 2. 產生傳票（任何失敗都會 rollback）
			// ============================================================
			$journalRevenue = $this->postJournal('sale_revenue');
			$journalCost = $this->postJournal('sale_cost');

			$totalFees = $this->calculateTotalFees();
			$journalFee = null;
			if (bccomp($totalFees, '0.0000', 4) > 0) {
				$journalFee = $this->postJournal('sale_fee');
			}

			// ============================================================
			// 3. ✅ 確認傳票都已成功產生（防呆檢查）
			// ============================================================
			$this->ensureJournalsCreated([
				'sale_revenue' => $journalRevenue,
				'sale_cost' => $journalCost,
				'sale_fee' => $journalFee,
			]);

			// ============================================================
			// 4. 全部成功 → 更新出庫時間
			// ============================================================
			$this->update(['stocked_out_at' => now()]);
			
			// ============================================================
			// 5. 狀態變更為 completed
			// ============================================================
			$this->transitionTo('completed', 'stock_out', auth()->user());
			
		}, 3); // 重試 3 次
	}

	/**
	 * ✅ 防呆：確認傳票都已成功產生
	 */
	private function ensureJournalsCreated(array $journals): void
	{
		$errors = [];
		
		foreach ($journals as $eventType => $journal) {
			// sale_fee 可能為 null（沒有賣家費用）
			if ($eventType === 'sale_fee' && $journal === null) {
				continue;
			}
			
			if (!$journal || !$journal->exists) {
				$errors[] = "傳票 [{$eventType}] 建立失敗";
				continue;
			}
			
			// ✅ 檢查傳票是否有明細
			$itemCount = $journal->items()->count();
			if ($itemCount === 0) {
				$errors[] = "傳票 [{$eventType}] 沒有明細項目";
			}
			
			// ✅ 檢查傳票是否平衡
			$debitTotal = $journal->items()->sum('debit');
			$creditTotal = $journal->items()->sum('credit');
			if (bccomp((string)$debitTotal, (string)$creditTotal, 4) !== 0) {
				$errors[] = "傳票 [{$eventType}] 借貸不平衡 (借: {$debitTotal}, 貸: {$creditTotal})";
			}
		}
		
		if (!empty($errors)) {
			// ✅ 拋出異常觸發 rollback
			throw new \RuntimeException(
				"傳票驗證失敗，操作已取消：\n" . implode("\n", $errors)
			);
		}
	}

	/**
	 * 驗證過帳規則是否存在
	 */
	private function validateAccountingRules(): void
	{
		$events = ['sale_revenue', 'sale_cost'];
		
		// 檢查是否有賣家費用需要過帳
		$totalFees = $this->calculateTotalFees();
		if (bccomp($totalFees, '0.0000', 4) > 0) {
			$events[] = 'sale_fee';
		}
		
		foreach ($events as $event) {
			$rule = \App\Models\AccountingRule::where('event_type', $event)
				->where('is_active', true)
				->first();
				
			if (!$rule) {
				throw new \RuntimeException("找不到已啟用的過帳規則 [{$event}]，請先設定規則再執行。");
			}
			
			$lineCount = $rule->lines()->count();
			if ($lineCount === 0) {
				throw new \RuntimeException("過帳規則 [{$event}] 沒有設定明細行，請先設定再執行。");
			}
		}
	}

    /**
     * 撤銷出庫（反向操作）
     */
	public function reverseStockOut(): void
	{
		if (!$this->stocked_out_at) {
			throw new \Exception("銷售單 {$this->invoice_number} 尚未出庫，無法撤銷。");
		}

		if ($this->hasReturnRecords()) {
			throw new \Exception("銷售單 {$this->invoice_number} 已有退貨紀錄，無法撤銷出庫。請走銷退流程。");
		}

		DB::transaction(function () {
			$accountingService = app(AccountingService::class);

			// 1. 撤銷傳票
			$accountingService->reverseJournal('sale_revenue', $this);
			$accountingService->reverseJournal('sale_cost', $this);

			$totalFees = $this->calculateTotalFees();
			if (bccomp($totalFees, '0.0000', 4) > 0) {
				$accountingService->reverseJournal('sale_fee', $this);
			}

			// 2. 回滾庫存
			foreach ($this->items as $item) {
				$this->restoreSingleProduct(
					$item->product_id,
					$item->warehouse_id ?? $this->warehouse_id,
					(float)$item->quantity
				);
			}

			// 3. 清除出庫狀態，回到草稿
			$this->update([
				'stocked_out_at' => null,
				'status' => WorkflowStatus::DRAFT->value,
			]);
		}, 3);
	}

    /**
     * 編輯銷售單明細時的庫存多退少補調整校驗器
     */
    public function adjustStockForEdit(array $oldItems, array $newItems, bool $allowNegative = false): void
    {
        if ($this->stocked_out_at && !$this->canBeModified()) {
            throw new \Exception('已出庫結案的銷售單無法直接修改庫存，請走銷退流程。');
        }

        $changes = $this->calculateInventoryChanges($oldItems, $newItems);

        foreach ($changes as $key => $change) {
            [$productId, $warehouseId] = explode('-', $key);

            if ($change > 0) {
                $this->deductSingleProduct((int)$productId, (int)$warehouseId, (float)$change, $allowNegative);
            } elseif ($change < 0) {
                $this->restoreSingleProduct((int)$productId, (int)$warehouseId, abs((float)$change));
            }
        }
    }

    private function calculateInventoryChanges(array $oldItems, array $newItems): array
    {
        $oldQtyMap = [];
        foreach ($oldItems as $item) {
            $key = $item['product_id'] . '-' . ($item['warehouse_id'] ?? $this->warehouse_id);
            $oldQtyMap[$key] = $item['quantity'];
        }

        $changes = [];
        foreach ($newItems as $item) {
            $key = $item['product_id'] . '-' . ($item['warehouse_id'] ?? $this->warehouse_id);
            $oldQty = $oldQtyMap[$key] ?? 0;
            $change = bcsub((string)$item['quantity'], (string)$oldQty, self::DECIMAL_PRECISION);
            if (bccomp($change, '0', self::DECIMAL_PRECISION) !== 0) {
                $changes[$key] = (float)$change;
            }
            unset($oldQtyMap[$key]);
        }

        foreach ($oldQtyMap as $key => $oldQty) {
            $changes[$key] = -(float)$oldQty;
        }

        return $changes;
    }

    private function deductInventory(array $itemsQuantity, bool $allowNegative): void
    {
        foreach ($itemsQuantity as $key => $quantity) {
            [$productId, $warehouseId] = explode('-', $key);
            $this->deductSingleProduct((int)$productId, (int)$warehouseId, (float)$quantity, $allowNegative);
        }
    }

    private function deductSingleProduct(int $productId, int $warehouseId, float $quantity, bool $allowNegative): void
    {
        if ($quantity <= 0) return;

        $inventory = Inventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        $currentQty = $inventory ? (float)$inventory->quantity : 0;

        if (!$allowNegative && $currentQty < $quantity) {
            $product = Product::find($productId);
            throw new \Exception("出庫阻斷：商品 [{$product?->name}] 庫存不足！現有: {$currentQty}，需求: {$quantity}");
        }

        $newQty = bcsub((string)$currentQty, (string)$quantity, self::DECIMAL_PRECISION);

        if ($inventory) {
            $inventory->update(['quantity' => $newQty]);
        } else {
            Inventory::create([
                'shop_id'      => $this->shop_id ?? 1,
                'product_id'   => $productId,
                'warehouse_id' => $warehouseId,
                'quantity'     => $newQty,
            ]);
        }
    }

    private function restoreSingleProduct(int $productId, int $warehouseId, float $quantity): void
    {
        if ($quantity <= 0) return;

        $inventory = Inventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($inventory) {
            $newQty = bcadd((string)$inventory->quantity, (string)$quantity, self::DECIMAL_PRECISION);
            $inventory->update(['quantity' => $newQty]);
        } else {
            Inventory::create([
                'shop_id'      => $this->shop_id ?? 1,
                'product_id'   => $productId,
                'warehouse_id' => $warehouseId,
                'quantity'     => $quantity,
            ]);
        }
    }

    private function getCurrentItemsQuantity(): array
    {
        $quantities = [];
        foreach ($this->items as $item) {
            $key = $item->product_id . '-' . ($item->warehouse_id ?? $this->warehouse_id);
            $quantities[$key] = $item->quantity;
        }
        return $quantities;
    }

    // =========================================================================
    // SECTION: 單據狀態校驗與 Eloquent 生命週期事件攔截
    // =========================================================================

    public function isLocked(): bool 
    { 
        return $this->hasReturnRecords(); 
    }

    public function hasReturnRecords(): bool
    {
        return $this->returns()
            ->whereIn('status', ['pending', 'approved', 'completed'])
            ->exists();
    }

    public function canBeModified(): bool
    {
        // ✅ 改用 WorkflowStatus 判斷
        return !$this->hasReturnRecords() && $this->status !== WorkflowStatus::COMPLETED;
    }

    public function getAttribute($key)
    {
        if (self::$feeTypesCache === null) {
            self::$feeTypesCache = config('business.fee_types', []);
        }

        if (isset(self::$feeTypesCache[$key])) {
            if ($this->relationLoaded('fees')) {
                return (string) $this->fees->where('fee_type', $key)->sum('amount');
            }
            return (string) $this->fees()->where('fee_type', $key)->sum('amount');
        }

        return parent::getAttribute($key);
    }

    protected static function booted()
    {
        static::deleting(function ($sale) {
            if ($sale->hasReturnRecords()) throw new \Exception('此銷售單已有退貨紀錄，禁止刪除。');
            if ($sale->stocked_out_at) throw new \Exception('已出庫的銷售單禁止刪除。');
        });

        static::updating(function ($sale) {
            if ($sale->hasReturnRecords()) throw new \Exception('此銷售單已有退貨紀錄，禁止修改。');
        });

        static::creating(function ($sale) {
            if (empty($sale->invoice_number)) $sale->invoice_number = self::generateInvoiceNumber();
            if (empty($sale->shop_id)) $sale->shop_id = auth()->user()->shop_id ?? 1;
            // ✅ 新建立時預設為 draft
            if (empty($sale->status)) $sale->status = WorkflowStatus::DRAFT->value;
        });
    }

    public static function generateInvoiceNumber(): string
    {
        return DB::transaction(function () {
            $prefix = Setting::get('so_prefix', 'SO-');
            $digits = (int) Setting::get('number_digits', 4);
            $datePart = now()->format('Ymd');
            $fullPrefix = $prefix . $datePart;

            $lastOrder = self::where('invoice_number', 'like', "{$fullPrefix}%")
                ->lockForUpdate()
                ->orderBy('invoice_number', 'desc')
                ->first();

            $nextNumber = $lastOrder ? (int) substr($lastOrder->invoice_number, -$digits) + 1 : 1;
            return $fullPrefix . str_pad($nextNumber, $digits, '0', STR_PAD_LEFT);
        });
    }

    // =========================================================================
    // SECTION: 工廠批次建立與變更
    // =========================================================================

    public static function createWithCalculations(array $data, array $items): self
    {
        return DB::transaction(function () use ($data, $items) {
            $feeConfigs = config('business.fee_types', []);
            $saleFields = array_diff_key($data, $feeConfigs);
            
            // ✅ 確保 status 有預設值
            if (empty($saleFields['status'])) {
                $saleFields['status'] = WorkflowStatus::DRAFT->value;
            }
            
            $sale = self::create($saleFields);

            foreach ($items as $item) {
                $warehouseId = $item['warehouse_id'] ?? $data['warehouse_id'] ?? null;
                $product = Product::find($item['product_id']);
                $unitCost = $product?->cost ?? '0.0000'; 

                if (bccomp((string)$unitCost, '0', self::DECIMAL_PRECISION) === 0) {
                    logger()->warning("建立銷售項目時商品 [{$product?->name}] 成本快照為 0");
                }

                $sale->items()->create([
                    'product_id'   => $item['product_id'],
                    'warehouse_id' => $warehouseId,
                    'price'        => $item['price'],
                    'quantity'     => $item['quantity'],
                    'subtotal'     => bcmul((string)$item['quantity'], (string)$item['price'], self::DECIMAL_PRECISION),                    
                    'unit_cost'    => $unitCost,
                ]);
            }

            foreach ($data as $key => $value) {
                if (isset($feeConfigs[$key]) && bccomp((string)$value, '0', self::DECIMAL_PRECISION) !== 0) {
                    $sale->fees()->create([
                        'shop_id'  => $sale->shop_id ?? 1,
                        'fee_type' => $key,
                        'amount'   => $value,
                        'note'     => $feeConfigs[$key]['name'] ?? $key,
                    ]);
                }
            }
            return $sale;
        });
    }

    public function updateWithCalculations(array $data, array $items): self
    {
        return DB::transaction(function () use ($data, $items) {
            if (!$this->canBeModified()) throw new \Exception('此銷售單無法修改（已有退貨或已完成）。');

            $oldItems = [];
            if ($this->stocked_out_at) {
                $oldItems = $this->items->map(fn($item) => [
                    'product_id'   => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'quantity'     => $item->quantity,
                ])->toArray();
            }

            $feeConfigs = config('business.fee_types', []);
            $saleData = array_diff_key($data, $feeConfigs);
            $this->update($saleData);

            $this->fees()->delete();
            foreach ($data as $key => $value) {
                if (isset($feeConfigs[$key]) && bccomp((string)$value, '0', self::DECIMAL_PRECISION) !== 0) {
                    $this->fees()->create([
                        'shop_id'  => $this->shop_id ?? 1,
                        'fee_type' => $key,
                        'amount'   => $value,
                        'note'     => $feeConfigs[$key]['name'] ?? $key,
                    ]);
                }
            }

            $this->items()->delete();
            foreach ($items as $item) {
                if (empty($item['product_id'])) continue;
                $warehouseId = $item['warehouse_id'] ?? $saleData['warehouse_id'] ?? null;
                $product = Product::find($item['product_id']);

                $this->items()->create([
                    'product_id'   => $item['product_id'],
                    'warehouse_id' => $warehouseId,
                    'price'        => $item['price'],
                    'quantity'     => $item['quantity'],
                    'subtotal'     => bcmul((string)$item['quantity'], (string)$item['price'], self::DECIMAL_PRECISION),
                    'unit_cost'    => $product?->cost ?? '0.0000',
                ]);
            }

            if ($this->stocked_out_at) {
                $newItems = collect($items)->map(fn($item) => [
                    'product_id'   => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'] ?? $saleData['warehouse_id'] ?? null,
                    'quantity'     => $item['quantity'],
                ])->toArray();

                $allowNegative = Setting::get('allow_negative_stock', false);
                $this->adjustStockForEdit($oldItems, $newItems, $allowNegative);
            }
            return $this;
        });
    }

    public static function withTrashed() { return static::query(); }

    // =========================================================================
    // SECTION: 會計金額解析（專屬於 Sale）
    // =========================================================================

    public function getAmountFromSource(string $source, mixed $context = null): string
    {
        if (!$this->relationLoaded('items')) {
            $this->load('items');
        }
        
        return match ($source) {
			'customer_total' => (string) ($this->customer_total ?? '0.0000'),
			'customer_total_inc_tax' => $this->customer_total_inc_tax,
			'subtotal_after_discount' => (string) ($this->subtotal_after_discount ?? $this->subtotal ?? '0.0000'),
			'net_revenue' => $this->net_revenue,
			
			// ===== 費用類（從 sale_fees 讀取） =====
			'tax_amount' => $this->getFeeTotal('tax_amount'),
			'freight_amount' => $this->getFeeTotal('freight_amount'),
			'platform_fee' => $this->getFeeTotal('platform_fee'),
			'commission' => $this->getFeeTotal('commission'),
			'seller_discount' => $this->getFeeTotal('seller_discount'),
			'shipping_fee_platform' => $this->getFeeTotal('shipping_fee_platform'),
			'shipping_fee_customer' => $this->getFeeTotal('shipping_fee_customer'),
			'platform_coupon' => $this->getFeeTotal('platform_coupon'),
			'order_adjustment' => $this->getFeeTotal('order_adjustment'),			
			
			// ===== 總計 =====
			'total_fees' => $this->calculateTotalFees(),
			'cost_amount' => $this->calculateRealtimeCost(),
			'final_net_amount' => (string) ($this->final_net_amount ?? '0.0000'),
			
			// ===== 退貨 =====
			'return_total' => (string) ($this->return_total ?? '0.0000'),
			'return_cost' => (string) ($this->return_cost ?? '0.0000'),
			'return_cost_base' => (string) ($this->return_cost_base ?? '0.0000'),
			
			// ===== 採購 =====
			'purchase_base_items' => (string) ($this->purchase_base_items ?? '0.0000'),
			'purchase_base_tax' => (string) ($this->purchase_base_tax ?? '0.0000'),
			'purchase_base_shipping' => (string) ($this->purchase_base_shipping ?? '0.0000'),
			'purchase_base_other_fees' => (string) ($this->purchase_base_other_fees ?? '0.0000'),
			'purchase_base_total' => (string) ($this->purchase_base_total ?? '0.0000'),
			
			default => '0.0000',
		};
	}

    private function getFeeTotal(string $feeType): string
    {
        if ($this->relationLoaded('fees')) {
            $total = $this->fees->where('fee_type', $feeType)->sum('amount');
        } else {
            $total = $this->fees()->where('fee_type', $feeType)->sum('amount');
        }
        return (string) ($total ?: '0.0000');
    }
	
	/**
	 * 買家含稅總額 = customer_total + 銷項稅額
	 */
	public function getCustomerTotalIncTaxAttribute(): string
	{
		$customerTotal = $this->customer_total;
		$tax = $this->getFeeTotal('tax_amount');
		return bcadd($customerTotal, $tax, 4);
	}
	
	/**
	 * 淨收入（扣除所有折扣後）
	 * 用於 sale_revenue 的貸方金額
	 */
	public function getNetRevenueAttribute(): string
	{
		$subtotalAfterDiscount = $this->subtotal_after_discount;
		$platformCoupon = $this->getFeeTotal('platform_coupon');
		
		$result = bcsub($subtotalAfterDiscount, $platformCoupon, 4);
		info('net_revenue 計算', [
        'sale_id' => $this->id,
        'subtotal' => $this->subtotal,
        'subtotal_after_discount' => $this->subtotal_after_discount ?? 'null',
        'platform_coupon' => $platformCoupon,
        'result' => $result,
    ]);
		return bcsub($subtotalAfterDiscount, $platformCoupon, 4);
	}
	
	/**
	 * 取得折讓後小計（subtotal - seller_discount）
	 */
	public function getSubtotalAfterDiscountAttribute(): string
	{
		$subtotal = (string) ($this->subtotal ?? '0.0000');
		$sellerDiscount = $this->getFeeTotal('seller_discount');
		return bcsub($subtotal, $sellerDiscount, 4);
	}

    // =========================================================================
    // SECTION: 核心業務方法
    // =========================================================================

    public function calculateRealtimeCost(): string
    {
        $mainCost = $this->cost_total_amount ?? $this->cost_amount ?? '0.0000';
        if (bccomp((string)$mainCost, '0.0000', 4) > 0) {
            return (string)$mainCost;
        }

        $calculatedTotalCost = '0.0000';
        $items = $this->items()->get(); 
        
        foreach ($items as $item) {
            if (isset($item->total_cost) && bccomp((string)$item->total_cost, '0.0000', 4) > 0) {
                $calculatedTotalCost = bcadd($calculatedTotalCost, (string)$item->total_cost, 4);
                continue;
            }

            $unitCost = $item->unit_cost ?? '0.0000';
            $qty      = $item->quantity ?? '0';

            if (bccomp((string)$unitCost, '0.0000', 4) === 0 && isset($item->product_id)) {
                $warehouseId = $item->warehouse_id ?? $this->warehouse_id ?? 1;
                $shopId      = $this->shop_id ?? 1;

                $inventory = DB::table('inventories')
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $warehouseId)
                    ->where('shop_id', $shopId)
                    ->first();

                if ($inventory && isset($inventory->weighted_average_cost) && bccomp((string)$inventory->weighted_average_cost, '0.0000', 4) > 0) {
                    $unitCost = $inventory->weighted_average_cost;
                } else {
                    $unitCost = DB::table('products')->where('id', $item->product_id)->value('cost') ?? '0.0000';
                }
            }

            $itemTotalCost = bcmul((string)$unitCost, (string)$qty, 4);
            $calculatedTotalCost = bcadd($calculatedTotalCost, $itemTotalCost, 4);
        }

        return $calculatedTotalCost;
    }
	
	/**
	 * 計算所有費用的總和
	 */
	public function calculateTotalFees(): string
	{
		$total = '0.0000';
		$feeTypes = config('business.fee_types', []);
    
		foreach ($feeTypes as $feeType => $config) {
			// ✅ 只計算賣家費用
			if (($config['target'] ?? '') !== 'seller') {
				continue;
			}
			
			$amount = $this->getFeeTotal($feeType);
			if (bccomp($amount, '0', 4) !== 0) {
				$total = bcadd($total, $amount, 4);
			}
		}
		
		return $total;
	}	

    // =========================================================================
    // SECTION: 動態科目解析（專屬於 Sale）
    // =========================================================================

    public function resolveDynamicAccount(string $dynamicSpec, ?array $context = null): string
    {
        $parts = explode(':', $dynamicSpec);
        $prefix = $parts[0] ?? '';
        $subType = $parts[1] ?? null;
        $thirdLevel = $parts[2] ?? null;

        return match($prefix) {
            'sale'    => $this->resolveSaleDynamicAccount($subType, $thirdLevel),
            'auto'    => $this->resolveAutoDynamicAccount($subType),
            default   => throw new \RuntimeException("未知的動態科目類型前綴: {$prefix}"),
        };
    }

    private function resolveSaleDynamicAccount(?string $subType, ?string $thirdLevel = null): string
    {
        return match($subType) {
            'payment'     => $this->resolvePaymentAccount(),
			'settle'      => $this->resolveSettleAccount(),
            'revenue'     => $this->resolveRevenueAccount(),
            'cost'        => $this->resolveCostAccount(),
            'channel_fee' => $this->resolveChannelFeeAccount(),
            'discount'    => $this->resolveDiscountAccount(),
            'return_fee'  => $this->resolveReturnFeeAccount($thirdLevel),
            default       => throw new \RuntimeException("未知的銷售子科目類型: {$subType}"),
        };
    }
	
	/**
	 * 解析結算帳戶（供動態科目使用）
	 */
	private function resolveSettleAccount(): string
	{
		return $this->getSettleAccount();
	}

    private function resolveAutoDynamicAccount(?string $subType): string
    {
        return match($subType) {
            'inventory' => config('business.accounting_accounts.cost.inventory', '1405'),
            'cost'      => config('business.accounting_accounts.cost.cost_of_goods_sold', '5401'),
            default     => throw new \RuntimeException("未知的自動科目類型: {$subType}"),
        };
    }

    public function getChannelCode(): string
    {
        $channelId = $this->channel_id;
		$channelMapping = config('business.channel_ids', []);
		
		// 根據 channel_id 找出對應的代碼
		foreach ($channelMapping as $code => $id) {
			if ($id == $channelId) {
				return $code;
			}
		}
		
		// 預設為零售
		return 'retail';
    }

	/**
	 * 解析付款帳戶（供動態科目使用）
	 */
	private function resolvePaymentAccount(): string
	{
		return $this->getPaymentAccount();
	}

	/**
	 * 解析收入帳戶（供動態科目使用）
	 */
	private function resolveRevenueAccount(): string
	{
		return $this->getRevenueAccount();
	}
	
    private function resolveCostAccount(): string
    {
        return config('business.accounting_accounts.cost.cost_of_goods_sold', '5401');
    }

    private function resolveChannelFeeAccount(): string
    {
        $channel = $this->getChannelCode();

        if ($channel === 'shopee') {
            return config('business.accounting_accounts.expenses.platform_fee', '560105');
        }

        return config('business.accounting_accounts.expenses.commission', '560111');
    }

    private function resolveDiscountAccount(): string
    {
        return config('business.accounting_accounts.expenses.discount', '500110');
    }

    private function resolveReturnFeeAccount(?string $subType): string
    {
        return match($subType) {
            'shipping' => config('business.accounting_accounts.expenses.shipping_fee', '560106'),
            default    => config('business.accounting_accounts.expenses.other_expense', '560108'),
        };
    }

    // =========================================================================
    // SECTION: ORM 關係宣告 (Relationships)
    // =========================================================================

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function items(): HasMany { return $this->hasMany(SaleItem::class); }
    public function shop(): BelongsTo { return $this->belongsTo(Shop::class, 'shop_id'); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function fees(): HasMany { return $this->hasMany(SaleFee::class); }
    public function returns(): HasMany { return $this->hasMany(SalesReturn::class, 'sale_id'); }
    public function channel(): BelongsTo { return $this->belongsTo(Channel::class, 'channel_id'); }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class, 'reference_id')
            ->where('reference_type', 'like', 'sale:%');
    }
}