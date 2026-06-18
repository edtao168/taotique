<?php
// 檔案路徑：app/Models/Sale.php

namespace App\Models;

use App\Models\Setting;
use App\Models\AccountingRule;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\SaleFee;
use App\Models\SalesReturn;
use App\Models\Journal;
use App\Services\AccountingService;
use App\Traits\HasAccountAndDynamicSearch;
use App\Traits\HasAccounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Sale extends Model
{
    use HasAccounting, HasAccountAndDynamicSearch;

    protected $guarded = [];

    protected $casts = [
        'shop_id'          => 'integer',
        'warehouse_id'     => 'integer',
        'sold_at'          => 'datetime:Y-m-d H:i:s',
        'stocked_out_at'   => 'datetime:Y-m-d H:i:s',
        'exchange_rate'    => 'decimal:4',
        'subtotal'         => 'decimal:4',
        'tax_amount'       => 'decimal:4',
        'freight_amount'   => 'decimal:4',
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
            'DYNAMIC:sale:revenue' => $this->getRevenueAccount(),
            'DYNAMIC:auto:inventory' => '1405',
            'DYNAMIC:auto:cost' => '5401',
            'DYNAMIC:sale:channel_fee' => '5601',
            'DYNAMIC:sale:discount' => '5602',
        ];
    }
    
    protected function getPaymentAccount(): string
    {
        // 根據付款方式回傳科目
        return match($this->payment_method) {
            'cash' => '1001',
            'bank_transfer' => '1002',
            default => '1122',
        };
    }
    
    protected function getRevenueAccount(): string
    {
        // 根據通路回傳科目
        return match($this->channel) {
            'retail' => '5001',
            'online' => '5002',
            default => '5001',
        };
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
		$net = $this->subtotal_after_discount ?? '0.0000';
		$tax = (string) ($this->tax_amount ?? '0.0000');
		$freight = (string) ($this->freight_amount ?? '0.0000');

		$total = bcadd($net, $tax, 4);
		return bcadd($total, $freight, 4);
	}

	public function getSubtotalAfterDiscountAttribute(): string
	{
		$subtotal = (string) ($this->subtotal ?? '0.0000');
		$discount = $this->getFeeTotal('seller_discount');
		return bcsub($subtotal, $discount, 4);
	}

	public function calculateTotalFees(): string
	{
		$fees = ['platform_fee', 'commission', 'seller_discount', 'shipping_fee_platform', 'order_adjustment'];
		$total = '0.0000';
		
		foreach ($fees as $feeType) {
			$amount = $this->getFeeTotal($feeType);
			$total = bcadd($total, $amount, 4);
		}
		
		return $total;
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
    // SECTION: 庫存管理核心控制
    // =========================================================================

    /**
     * 執行銷售單實體出庫，同步鎖定並發動三份大傳票自動過帳與強校驗
     * 
     * 🎯 核心邏輯：
     * 1. 檢查是否已出庫（stocked_out_at）
     * 2. 檢查是否有退貨紀錄
     * 3. 扣減庫存
     * 4. 驅動三份傳票自動過帳（每份獨立 Journal）
     * 5. 更新出庫狀態
     */
    public function processStockOut(bool $allowNegative = false): void
    {
        if ($this->stocked_out_at) {
            throw new \Exception("銷售單 {$this->invoice_number} 已完成出庫，請勿重複執行。");
        }

        if ($this->hasReturnRecords()) {
            throw new \Exception("銷售單 {$this->invoice_number} 已有退貨紀錄，無法出庫。");
        }

        DB::transaction(function () use ($allowNegative) {
            // 1. 執行嚴謹的庫存扣減
            $this->deductInventory($this->getCurrentItemsQuantity(), $allowNegative);

            // 2. 重新載入最新關聯數據
            $this->fresh(['items.product', 'fees', 'channel']); 

            // 3. 驅動傳票自動結轉
            // 🎯 每個 event_type 產生獨立的 Journal（由 AccountingService 支持）
            $this->postJournal('sale_revenue');
            $this->postJournal('sale_cost');

            $totalFees = $this->calculateTotalFees();
            if (bccomp($totalFees, '0.0000', 4) > 0) {
                $this->postJournal('sale_fee');
            }

            // 4. 更新出庫狀態
            $this->update([
                'stocked_out_at' => now(),
                'status'         => 'completed'
            ]);
        }, 3);
    }

    /**
     * 🎯 新增：撤銷出庫（反向操作）
     * 
     * 業務場景：出庫後發現錯誤，需要回滾庫存並撤銷傳票。
     * 限制：已有退貨紀錄的單據不允許撤銷（避免複雜的連鎖回滾）。
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

            // 1. 撤銷三份傳票（標記為 reversed）
            $accountingService->reverseJournal('sale_revenue', $this);
            $accountingService->reverseJournal('sale_cost', $this);

            $totalFees = $this->calculateTotalFees();
            if (bccomp($totalFees, '0.0000', 4) > 0) {
                $accountingService->reverseJournal('sale_fee', $this);
            }

            // 2. 回滾庫存（將出庫數量加回）
            foreach ($this->items as $item) {
                $this->restoreSingleProduct(
                    $item->product_id,
                    $item->warehouse_id ?? $this->warehouse_id,
                    (float)$item->quantity
                );
            }

            // 3. 清除出庫狀態
            $this->update([
                'stocked_out_at' => null,
                'status'         => 'pending'
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
        return !$this->hasReturnRecords() && $this->status !== 'completed';
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
            $sale = self::create($saleFields);

            foreach ($items as $item) {
                $warehouseId = $item['warehouse_id'] ?? $data['warehouse_id'] ?? null;
                $product = Product::find($item['product_id']);
                $unitCost = $product?->cost ?? '0.0000'; 

                if (bccomp((string)$unitCost, '0', self::DECIMAL_PRECISION) === 0) {
                    Log::warning("建立銷售項目時商品 [{$product?->name}] 成本快照為 0");
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

    /**
     * 獲取指定金額來源的數值
     * 🎯 依據 sale_items 真實 Schema 進行對接，根除 0 元結轉異常
     */
	public function getAmountFromSource(string $source, mixed $context = null): string
	{
		// 🎯 先確保必要的關聯已載入
		if (!$this->relationLoaded('items')) {
			$this->load('items');
		}
		
		return match ($source) {
			// ===== 銷售收入相關 =====
			'customer_total' => (string) ($this->customer_total ?? '0.0000'),
			'subtotal_after_discount' => (string) ($this->subtotal_after_discount ?? $this->subtotal ?? '0.0000'),
			'tax_amount' => (string) ($this->tax_amount ?? '0.0000'),
			'freight_amount' => (string) ($this->freight_amount ?? '0.0000'),
			
			// ===== 銷售費用相關 =====
			'platform_fee' => $this->getFeeTotal('platform_fee'),
			'commission' => $this->getFeeTotal('commission'),
			'seller_discount' => $this->getFeeTotal('seller_discount'),
			'shipping_fee_platform' => $this->getFeeTotal('shipping_fee_platform'),
			'order_adjustment' => $this->getFeeTotal('order_adjustment'),
			'total_fees' => $this->calculateTotalFees(),
			
			// ===== 銷售成本相關 =====
			'cost_amount' => $this->calculateRealtimeCost(),
			
			// ===== 退貨相關 =====
			'return_total' => (string) ($this->return_total ?? '0.0000'),
			'return_cost' => (string) ($this->return_cost ?? '0.0000'),
			'return_cost_base' => (string) ($this->return_cost_base ?? '0.0000'),
			
			// ===== 採購相關（如果 Sale 也有用到） =====
			'purchase_base_items' => (string) ($this->purchase_base_items ?? '0.0000'),
			'purchase_base_tax' => (string) ($this->purchase_base_tax ?? '0.0000'),
			'purchase_base_shipping' => (string) ($this->purchase_base_shipping ?? '0.0000'),
			'purchase_base_other_fees' => (string) ($this->purchase_base_other_fees ?? '0.0000'),
			'purchase_base_total' => (string) ($this->purchase_base_total ?? '0.0000'),
			
			default => '0.0000',
		};
	}

	/**
	 * 輔助方法：取得特定費用類型的總額
	 */
	private function getFeeTotal(string $feeType): string
	{
		if ($this->relationLoaded('fees')) {
			$total = $this->fees->where('fee_type', $feeType)->sum('amount');
		} else {
			$total = $this->fees()->where('fee_type', $feeType)->sum('amount');
		}
		return (string) ($total ?: '0.0000');
	}

    // =========================================================================
    // SECTION: 🎯 增補核心業務方法 (核心除錯與高複用性封裝)
    // =========================================================================

    /**
     * 實時計算銷售單的銷貨總成本
     * 🎯 解決有成本卻抓到 0 元的問題，穿透虛擬生成欄位與庫存表
     */
    public function calculateRealtimeCost(): string
    {
        // 1. 優先查看主表 sales 是否有歷史快照成本值
        $mainCost = $this->cost_total_amount ?? $this->cost_amount ?? '0.0000';
        if (bccomp((string)$mainCost, '0.0000', 4) > 0) {
            return (string)$mainCost;
        }

        // 2. 主表若尚未回寫，穿透到 sale_items 明細表實時精算
        $calculatedTotalCost = '0.0000';
        $items = $this->items()->get(); 
        
        foreach ($items as $item) {
            // A. 優先使用 sale_items 的 VIRTUAL GENERATED 欄位 total_cost
            if (isset($item->total_cost) && bccomp((string)$item->total_cost, '0.0000', 4) > 0) {
                $calculatedTotalCost = bcadd($calculatedTotalCost, (string)$item->total_cost, 4);
                continue;
            }

            // B. 備援方案：手動使用單項成本 unit_cost * quantity
            $unitCost = $item->unit_cost ?? '0.0000';
            $qty      = $item->quantity ?? '0';

            // 🚨【多層防禦管道】若明細單價 unit_cost 為 0，則向上/向下追溯
            if (bccomp((string)$unitCost, '0.0000', 4) === 0 && isset($item->product_id)) {
                $warehouseId = $item->warehouse_id ?? $this->warehouse_id ?? 1;
                $shopId      = $this->shop_id ?? 1;

                // 優先從庫存表撈取當前加權平均成本
                $inventory = DB::table('inventories')
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $warehouseId)
                    ->where('shop_id', $shopId)
                    ->first();

                if ($inventory && isset($inventory->weighted_average_cost) && bccomp((string)$inventory->weighted_average_cost, '0.0000', 4) > 0) {
                    $unitCost = $inventory->weighted_average_cost;
                } else {
                    // 最終保險：對接真實的 products.cost 欄位 (最近一次進價)
                    $unitCost = DB::table('products')->where('id', $item->product_id)->value('cost') ?? '0.0000';
                }
            }

            $itemTotalCost = bcmul((string)$unitCost, (string)$qty, 4);
            $calculatedTotalCost = bcadd($calculatedTotalCost, $itemTotalCost, 4);
        }

        return $calculatedTotalCost;
    }

    /**
     * 獲取平台手續費總計
     */
    public function getPlatformFeeTotal(): string
    {
        return (string)($this->fees()->where('fee_type', 'platform_fee')->get()
            ->sum(fn($fee) => (string)($fee->amount ?? '0.0000')) ?: '0.0000');
    }

    /**
     * 獲取佣金總計
     */
    public function getCommissionTotal(): string
    {
        return (string)($this->fees()->where('fee_type', 'commission')->get()
            ->sum(fn($fee) => (string)($fee->amount ?? '0.0000')) ?: '0.0000');
    }

    /**
     * 獲取賣家活動折讓總計
     */
    public function getSellerDiscountTotal(): string
    {
        return (string)($this->fees()->where('fee_type', 'seller_discount')->get()
            ->sum(fn($fee) => (string)($fee->amount ?? '0.0000')) ?: '0.0000');
    }

    /**
     * 獲取平台代扣運費總計
     */
    public function getPlatformShippingFeeTotal(): string
    {
        return (string)($this->fees()->where('fee_type', 'shipping_fee_platform')->get()
            ->sum(fn($fee) => (string)($fee->amount ?? '0.0000')) ?: '0.0000');
    }

    /**
     * 獲取所有費用總計
     */
    private function getTotalFeesSum(): string
    {
        if (isset($this->fees_total_amount) && bccomp((string)$this->fees_total_amount, '0.0000', 4) > 0) {
            return (string)$this->fees_total_amount;
        }

        return (string)($this->fees()->get()->sum(fn($fee) => (string)($fee->amount ?? '0.0000')) ?? '0.0000');
    }

    private function calculateTotalCost(): string
    {
        $this->load(['items.product']);

        $totalCost = '0.0000';

        foreach ($this->items as $item) {
            $itemCost = (string)($item->unit_cost ?? '0.0000');

            if (bccomp($itemCost, '0.0000', 4) === 0 && $item->product) {
                $itemCost = (string)($item->product->cost ?? '0.0000');
            }

            $itemQty = (string)($item->quantity ?? '0.0000');
            $totalCost = bcadd($totalCost, bcmul($itemCost, $itemQty, 4), 4);
        }

        return $totalCost;
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
            'revenue'     => $this->resolveRevenueAccount(),
            'cost'        => $this->resolveCostAccount(),
            'channel_fee' => $this->resolveChannelFeeAccount(),
            'discount'    => $this->resolveDiscountAccount(),
            'return_fee'  => $this->resolveReturnFeeAccount($thirdLevel),
            default       => throw new \RuntimeException("未知的銷售子科目類型: {$subType}"),
        };
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
        if (!$this->relationLoaded('channel')) {
            $this->load('channel');
        }

        $channelCode = $this->channel?->code ?? 'retail';
        $mapping = config('business.channel_mapping', []);

        $result = $mapping[$channelCode] ?? 'retail';
    
		info('getChannelCode result', [
			'sale_id' => $this->id,
			'channel_code' => $channelCode,
			'mapped' => $result,
		]);
		
		return $result;
    }

    private function resolvePaymentAccount(): string
    {
        $channel = $this->getChannelCode();
        $payment = $this->payment_method ?? 'default';
info('Resolving payment account', [
        'sale_id' => $this->id,
        'channel' => $channel,
        'payment' => $payment,
    ]);
        $paymentAccount = config("business.payment_accounts.{$payment}");
        if ($paymentAccount) {
            return $paymentAccount;
        }

        $channelReceivables = config("business.accounting_accounts.receivables.{$channel}");
        if ($channelReceivables && isset($channelReceivables[$payment])) {
            return $channelReceivables[$payment];
        }

        if ($channelReceivables && isset($channelReceivables['default'])) {
            return $channelReceivables['default'];
        }

        return config('business.accounting_accounts.receivables.default', '1131');
    }

    private function resolveRevenueAccount(): string
    {
        $channel = $this->getChannelCode();

        return match($channel) {
			'shopee'   => '500102',   // 蝦皮電商收入
			'facebook' => '500103',   // 社群電商收入
			'retail'   => '500101',   // 門市零售收入
			default    => '500101',
		};
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

    /**
     * 🎯 新增：查詢與此銷售單關聯的所有傳票（多態關聯的擴展）
     * 
     * 由於現在每個 event_type 有獨立的 Journal，reference_type 格式為 "sale:sale_revenue"
     * 傳統的 morphOne 無法直接匹配，需要自定義查詢。
     */
    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class, 'reference_id')
            ->where('reference_type', 'like', 'sale:%');
    }
}