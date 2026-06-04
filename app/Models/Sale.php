<?php
// app/Models/Sale.php

namespace App\Models;

use App\Models\Setting;
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
        'sold_at'          => 'datetime:Y-m-d H:i:s',
        'stocked_out_at'   => 'datetime:Y-m-d H:i:s',
        'exchange_rate'    => 'decimal:4',
        'subtotal'         => 'decimal:4',
    ];
    
    private static ?array $feeTypesCache = null;
    private const DECIMAL_PRECISION = 4;
    
    // ==============================================
    // 全通路通用動態過帳資料清洗 Accessors (完美留存)
    // ==============================================

    /**
     * 🌟 全通用動態規則一之貸方：純商品商品淨額 (500101)
     * 數值嚴謹性：商品總價 - 賣家自營活動折讓 (本單蝦皮券與賣家無關，為0)
     */
    public function getSubtotalAfterDiscountAttribute(): string
    {
        $subtotal = (string)($this->attributes['subtotal'] ?? '0.0000');
        $sellerDiscount = (string)($this->attributes['seller_discount'] ?? '0.0000');
        return bcsub($subtotal, $sellerDiscount, 4);
    }

    /**
     * 🌟 全通用動態規則一之借方：顧客實付總額 (DYNAMIC:sale:payment)
     * 通用公式：純商品商品淨額 + 稅額 + 買家自付運費
     * 🛡️ 蝦皮資料清洗：freight_amount 在建單時不計入賣家流向(為0)，因此 customer_total = 398 + 0 = 398.0000
     */
    public function getCustomerTotalAttribute(): string
    {
        $net = $this->subtotal_after_discount;
        $tax = (string)($this->attributes['tax_amount'] ?? '0.0000');
        $freight = (string)($this->attributes['freight_amount'] ?? '0.0000');
        
        $total = bcadd($net, $tax, 4);
        return bcadd($total, $freight, 4);
    }

    /**
     * 🌟 全通用動態規則二之貸方對沖基準：賣家最終實收金額 (對齊後台下拉選單：final_net_amount)
     * 通用公式：清洗後顧客總額 398.0000 - 平台摩擦費用總計 30.0000 = 368.0000
     */
    public function getFinalNetAmountAttribute(): string
    {
        $platformFee     = (string)($this->platform_fee ?? '0.0000');
        $commission      = (string)($this->commission ?? '0.0000');
        $sellerDiscount  = (string)($this->seller_discount ?? '0.0000');
        $shippingFeePlat = (string)($this->shipping_fee_platform ?? '0.0000');

        // 計算平台費用總計 (total_fees)
        $totalFees = bcadd($platformFee, $commission, 4);
        $totalFees = bcadd($totalFees, $sellerDiscount, 4);
        $totalFees = bcadd($totalFees, $shippingFeePlat, 4);

        return bcsub($this->customer_total, $totalFees, 4);
    }
    
    public function getSoldDateAttribute(): string
    {
        return $this->sold_at->format('Y-m-d');
    }
    
    public function getSoldTimeAttribute(): string
    {
        return $this->sold_at->format('H:i');
    }
    
    public function getPaymentMethodNameAttribute(): string
    {
        return collect(config('business.payment_methods'))
            ->firstWhere('id', $this->payment_method)['name'] ?? $this->payment_method;
    }
    
    // ==============================================
    // Accounting Rules (全動態規則優化)
    // ==============================================
    
    /**
     * 獲取動態會計規則線路
     */
    public function getAccountingRules(string $eventType): array
    {
        // 直接使用通用規則事件：sale_revenue, sale_fee, sale_cost
        $rule = AccountingRule::where('event_type', $eventType)
            ->where('is_active', true)
            ->with(['lines' => fn($q) => $q->orderBy('sort_order')])
            ->first();
        
        if (!$rule) {
            throw new \RuntimeException("找不到通用動態會計規則：{$eventType}");
        }
        
        return $rule->lines->toArray();
    }
    
    // ==============================================
    // Stock Management (🛡️ 嚴格完整召回，強制控制併發與排他鎖)
    // ==============================================
    
    /**
     * 執行銷售出庫
     */
    public function processStockOut(bool $allowNegative = false): void
    {
        if ($this->stocked_out_at) {
            throw new \Exception("銷售單 {$this->invoice_number} 已出庫。");
        }
        
        if ($this->hasReturnRecords()) {
            throw new \Exception("銷售單 {$this->invoice_number} 已有退貨紀錄，無法出庫。");
        }
        
        DB::transaction(function () use ($allowNegative) {
            
            // 1. 核心：鎖定並扣減庫存
            $this->deductInventory($this->getCurrentItemsQuantity(), $allowNegative);
            
            // 2. 重新載入最新的關聯資料（包含明細與費用快照）
            $this->fresh(['items.product', 'fees']);; 
            
            // 3. 執行傳票過帳（過帳引擎內部去解析 DYNAMIC: 標籤）
            $this->postJournal('sale_revenue');
            $this->postJournal('sale_cost');
            
            if ($this->fees->isNotEmpty()) {
                $this->postJournal('sale_fee');
            }
            
            // 4. 更新出庫時間與單據狀態
            $this->update([
                'stocked_out_at' => now(),
            ]);
        }, 3);
    }
    
    /**
     * 編輯銷售單時的庫存調整
     */
    public function adjustStockForEdit(array $oldItems, array $newItems, bool $allowNegative = false): void
    {
        if ($this->stocked_out_at && !$this->canBeModified()) {
            throw new \Exception('已出庫的銷售單無法修改庫存。');
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
    
    /**
     * 計算庫存變化量
     */
    private function calculateInventoryChanges(array $oldItems, array $newItems): array
    {
        $oldQtyMap = [];
        foreach ($oldItems as $item) {
            $key = $item['product_id'] . '-' . $item['warehouse_id'];
            $oldQtyMap[$key] = $item['quantity'];
        }
        
        $changes = [];
        foreach ($newItems as $item) {
            $key = $item['product_id'] . '-' . $item['warehouse_id'];
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
    
    /**
     * 扣減庫存（多商品）
     */
    private function deductInventory(array $itemsQuantity, bool $allowNegative): void
    {
        foreach ($itemsQuantity as $key => $quantity) {
            [$productId, $warehouseId] = explode('-', $key);
            $this->deductSingleProduct((int)$productId, (int)$warehouseId, (float)$quantity, $allowNegative);
        }
    }
    
    /**
     * 扣減單一商品庫存 (併發控制強制 lockForUpdate)
     */
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
            throw new \Exception("商品 {$product?->name} 庫存不足。可用：{$currentQty}，需扣：{$quantity}");
        }
        
        $newQty = bcsub((string)$currentQty, (string)$quantity, self::DECIMAL_PRECISION);
        
        if ($inventory) {
            $inventory->update(['quantity' => $newQty]);
        } else {
            Inventory::create([
                'product_id'   => $productId,
                'warehouse_id' => $warehouseId,
                'quantity'     => $newQty,
                'shop_id'      => $this->shop_id,
            ]);
        }
    }
    
    /**
     * 回補單一商品庫存 (併發控制強制 lockForUpdate)
     */
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
                'product_id'   => $productId,
                'warehouse_id' => $warehouseId,
                'quantity'     => $quantity,
                'shop_id'      => $this->shop_id,
            ]);
        }
    }
    
    /**
     * 取得目前所有商品的數量（用於出庫）
     */
    private function getCurrentItemsQuantity(): array
    {
        $quantities = [];
        foreach ($this->items as $item) {
            $key = $item->product_id . '-' . $item->warehouse_id;
            $quantities[$key] = $item->quantity;
        }
        return $quantities;
    }
    
    // ==============================================
    // Status Checks (🛡️ 嚴謹狀態鎖定全數補回)
    // ==============================================
    
    /**
     * 🌟 補回重要資產：判斷單據是否被會計或業務流程鎖定
     */
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
    
    // ==============================================
    // Dynamic Attribute (Fees)
    // ==============================================
    
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
    
    // ==============================================
    // Model Events
    // ==============================================
    
    protected static function booted()
    {
        static::deleting(function ($sale) {
            if ($sale->hasReturnRecords()) {
                throw new \Exception('此銷售單已有退貨紀錄，禁止刪除。');
            }
            if ($sale->stocked_out_at) {
                throw new \Exception('已出庫的銷售單禁止刪除。');
            }
        });
        
        static::updating(function ($sale) {
            if ($sale->hasReturnRecords()) {
                throw new \Exception('此銷售單已有退貨紀錄，禁止修改。');
            }
        });
        
        static::creating(function ($sale) {
            if (empty($sale->invoice_number)) {
                $sale->invoice_number = self::generateInvoiceNumber();
            }
            if (empty($sale->shop_id)) {
                $sale->shop_id = auth()->user()->shop_id ?? 1;
            }
        });
    }
    
    // ==============================================
    // Invoice Number Generator
    // ==============================================
    
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
            
            $nextNumber = $lastOrder 
                ? (int) substr($lastOrder->invoice_number, -$digits) + 1
                : 1;
            
            return $fullPrefix . str_pad($nextNumber, $digits, '0', STR_PAD_LEFT);
        });
    }
    
    // ==============================================
    // Create/Update with Calculations
    // ==============================================
    
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
                    Log::warning('建立銷售項目時商品成本為 0', [
                        'product_id'   => $item['product_id'],
                        'product_name' => $product?->name,
                        'sale_id'      => $sale->id,
                    ]);
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
                        'shop_id'  => $sale->shop_id,
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
            if (!$this->canBeModified()) {
                throw new \Exception('此銷售單無法修改（已有退貨或已完成）。');
            }
            
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
                        'shop_id'  => $this->shop_id,
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
    
    /**
     * 🛡️ 靜態多型容錯盾
     */
    public static function withTrashed()
    {
        return static::query();
    }
    
    // ==============================================
    // Relationships
    // ==============================================
    
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    
    public function items(): HasMany 
    {
        return $this->hasMany(SaleItem::class); 
    }
    
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }
    
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function fees(): HasMany
    {
        return $this->hasMany(SaleFee::class);
    }
    
    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class, 'sale_id');
    }
    
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
    
    public function journal()
    {
        return $this->morphOne(Journal::class, 'reference');
    }
}