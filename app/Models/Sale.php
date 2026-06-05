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
use App\Traits\HasAccounting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Sale extends Model
{
    use HasAccounting;
    
    /**
     * 允許批量賦值的屬性白名單
     */
    protected $guarded = [];
    
    /**
     * 欄位型態轉換宣告 (數值嚴謹性：金額與匯率強制轉換為 decimal 字串)
     */
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
    
    /**
     * 靜態快取：存儲平台費用項目配置，防止高頻存取資料庫
     */
    private static ?array $feeTypesCache = null;
    
    /**
     * 高頻計算精度約束
     */
    private const DECIMAL_PRECISION = 4;
    
    // =========================================================================
    // SECTION: 前端 Mary UI 表格與卡片渲染 Accessors
    // =========================================================================
    
    /**
     * 獲取銷售日期的純文字快照 (YYYY-MM-DD)
     */
    public function getSoldDateAttribute(): string
    {
        return $this->sold_at ? $this->sold_at->format('Y-m-d') : '';
    }
    
    /**
     * 獲取銷售時間的純文字快照 (HH:MM)
     */
    public function getSoldTimeAttribute(): string
    {
        return $this->sold_at ? $this->sold_at->format('H:i') : '';
    }
    
    /**
     * 解析並翻譯當前銷售單的付款方式繁體名稱
     */
    public function getPaymentMethodNameAttribute(): string
    {
        return collect(config('business.payment_methods'))
            ->firstWhere('id', $this->payment_method)['name'] ?? $this->payment_method;
    }

    // =========================================================================
    // SECTION: 全通路通用動態過帳金額清洗 Accessors (BCMath 嚴謹運算)
    // =========================================================================

    /**
     * 計算折讓後純商品淨額 (自動扣抵賣場自營折扣)
     */
    public function getSubtotalAfterDiscountAttribute(): string
    {
        $subtotal = (string)($this->attributes['subtotal'] ?? '0.0000');
        $sellerDiscount = (string)($this->attributes['seller_discount'] ?? '0.0000');
        return bcsub($subtotal, $sellerDiscount, 4);
    }

    /**
     * 計算買家應實付之總金額 (內含稅額與買家自付運費)
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
     * 計算賣家最終實收淨額 (買家實付總額扣除所有平台抽成、手續費、佣金與代付運費)
     */
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
    
    /**
     * 獲取當前分店與事件類型綁定之已啟用會計規則明細線
     */
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
    // SECTION: 庫存管理核心控制 (排他鎖 lockForUpdate 與原子級併發控制)
    // =========================================================================
    
    /**
     * 執行銷售單實體出庫，同步鎖定並發動三份大傳票自動過帳與強校驗
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
            
            // 2. 重新載入最新關聯數據，確保分錄切分時金額百分之百精準
            $this->fresh(['items.product', 'fees']); 
            
            // 3. 驅動傳票自動結轉 (若不平衡或科目錯誤，內部 transaction 會自動 Rollback)
            $this->postJournal('sale_revenue');
            $this->postJournal('sale_cost');
            
            if ($this->fees->isNotEmpty() || bccomp($this->final_net_amount, $this->customer_total, 4) !== 0) {
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
     * 編輯銷售單明細時的庫存多退少補調整校驗器 (行級鎖定防併發)
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
    
    /**
     * 比較新舊銷售明細，精準計算出每一項商品在特定倉庫的庫存差異權重
     */
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
    
    /**
     * 批次循環扣減多項商品庫存
     */
    private function deductInventory(array $itemsQuantity, bool $allowNegative): void
    {
        foreach ($itemsQuantity as $key => $quantity) {
            [$productId, $warehouseId] = explode('-', $key);
            $this->deductSingleProduct((int)$productId, (int)$warehouseId, (float)$quantity, $allowNegative);
        }
    }
    
    /**
     * 核心排他鎖：扣減單一商品在特定倉庫的實體庫存，並執行負庫存安全阻斷
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
    
    /**
     * 核心排他鎖：歸還/釋放單一商品實體庫存回指定倉庫
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
                'shop_id'      => $this->shop_id ?? 1,
                'product_id'   => $productId,
                'warehouse_id' => $warehouseId,
                'quantity'     => $quantity,
            ]);
        }
    }
    
    /**
     * 歸納當前明細中各商品與倉庫對應的總出貨數量陣列
     */
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
    
    /**
     * 檢查單據是否已被後續流程鎖定 (如已有銷退紀錄)
     */
    public function isLocked(): bool 
    { 
        return $this->hasReturnRecords(); 
    }
    
    /**
     * 判定當前單據是否存在任何有效的退貨申請紀錄
     */
    public function hasReturnRecords(): bool
    {
        return $this->returns()
            ->whereIn('status', ['pending', 'approved', 'completed'])
            ->exists();
    }
    
    /**
     * 判定當前銷售單是否允許被終端用戶編輯修改
     */
    public function canBeModified(): bool
    {
        return !$this->hasReturnRecords() && $this->status !== 'completed';
    }
    
    /**
     * 魔術方法攔截器：動態計算並加總對齊 config/business.php 宣告的電商平台摩擦費明細金額
     */
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
    
    /**
     * 綁定銷售單 Eloquent 監聽器，強制注入安全機制與自動編號
     */
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
    
    /**
     * 高併發互斥鎖下自動遞增生成系統銷售單號 (SO-YYYYMMDD-XXXX)
     */
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
    // SECTION: 工廠批次建立與變更 (自動捕捉、保存歷史移動平均成本)
    // =========================================================================
    
    /**
     * 新增銷售單：自動追蹤捕捉並鎖定商品當下的加權平均成本快照，防止未來成本變動造成歷史利潤失真
     */
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
    
    /**
     * 變更銷售單：同步重算商品小計、更新電商摩擦費明細、並驅動多退少補之庫存微調機制
     */
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
    
    /**
     * 靜態多型容錯盾
     */
    public static function withTrashed() { return static::query(); }
    
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
}