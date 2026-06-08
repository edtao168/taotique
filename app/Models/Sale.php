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
use App\Traits\HasAccounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Sale extends Model
{
    use HasAccounting;

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

    public function getSubtotalAfterDiscountAttribute(): string
    {
        $subtotal = (string)($this->attributes['subtotal'] ?? '0.0000');
        $sellerDiscount = (string)($this->attributes['seller_discount'] ?? '0.0000');
        return bcsub($subtotal, $sellerDiscount, 4);
    }

    public function getCustomerTotalAttribute(): string
    {
        $net = $this->subtotal_after_discount;
        $tax = (string)($this->attributes['tax_amount'] ?? '0.0000');
        $freight = (string)($this->attributes['freight_amount'] ?? '0.0000');

        $total = bcadd($net, $tax, 4);
        return bcadd($total, $freight, 4);
    }

    public function getFinalNetAmountAttribute(): string
    {
        $platformFee     = (string)($this->platform_fee ?? '0.0000');
        $commission      = (string)($this->commission ?? '0.0000');
        $sellerDiscount  = (string)($this->seller_discount ?? '0.0000');
        $shippingFeePlat = (string)($this->shipping_fee_platform ?? '0.0000');

        $totalFees = bcadd($platformFee, $commission, 4);
        $totalFees = bcadd($totalFees, $sellerDiscount, 4);
        $totalFees = bcadd($totalFees, $shippingFeePlat, 4);

        return bcsub($this->customer_total, $totalFees, 4);
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

    public function getAccountingRules(string $eventType): array
    {
        $shopId = $this->shop_id ?? 1;
        $rule = AccountingRule::where('event_type', $eventType)
            ->where('shop_id', $shopId)
            ->where('is_active', true)
            ->with(['lines' => fn($q) => $q->orderBy('sort_order')])
            ->first();

        if (!$rule) {
            throw new \RuntimeException("找不到通用動態會計規則：[{$eventType}]，店鋪 ID: {$shopId}");
        }

        return $rule->lines->toArray();
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
            $this->fresh(['items.product', 'fees']); 

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

    public function getAmountFromSource(string $amountSource, ?string $eventType = null): string
    {
        if ($amountSource === 'cost_amount') {
            return $this->calculateTotalCost();
        }

        if ($amountSource === 'total_fees') {
            return $this->calculateTotalFees();
        }

        return match($amountSource) {
            'customer_total'          => $this->customer_total,
            'subtotal_after_discount' => $this->subtotal_after_discount,
            'final_net_amount'        => $this->final_net_amount,
            'tax_amount'              => $this->tax_amount ?? '0.0000',
            'freight_amount'          => $this->freight_amount ?? '0.0000',
            'subtotal'                => $this->subtotal ?? '0.0000',
            'platform_fee'            => $this->platform_fee ?? '0.0000',
            'commission'              => $this->commission ?? '0.0000',
            'seller_discount'         => $this->seller_discount ?? '0.0000',
            'shipping_fee_platform'   => $this->shipping_fee_platform ?? '0.0000',
            default                   => $this->getAttribute($amountSource) ?? '0.0000',
        };
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

    private function calculateTotalFees(): string
    {
        $platformFee    = (string)($this->platform_fee ?? '0.0000');
        $commission     = (string)($this->commission ?? '0.0000');
        $sellerDiscount = (string)($this->seller_discount ?? '0.0000');
        $shippingFee    = (string)($this->shipping_fee_platform ?? '0.0000');

        $total = bcadd($platformFee, $commission, 4);
        $total = bcadd($total, $sellerDiscount, 4);
        return bcadd($total, $shippingFee, 4);
    }

    // =========================================================================
    // SECTION: 動態科目解析（專屬於 Sale）
    // =========================================================================

    public function resolveDynamicAccount(string $dynamicSpec, ?string $context = null): string
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

        return $mapping[$channelCode] ?? 'retail';
    }

    private function resolvePaymentAccount(): string
    {
        $channel = $this->getChannelCode();
        $payment = $this->payment_method ?? 'default';

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

        $revenueConfig = config("business.accounting_accounts.revenue.{$channel}");

        if ($revenueConfig && isset($revenueConfig['code'])) {
            return $revenueConfig['code'];
        }

        return config('business.accounting_accounts.revenue.default', '500101');
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