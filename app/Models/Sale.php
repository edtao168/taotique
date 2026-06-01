<?php

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
        'customer_total'   => 'decimal:4',
        'final_net_amount' => 'decimal:4',
    ];
    
    private static ?array $feeTypesCache = null;
    private const DECIMAL_PRECISION = 4;
    
    // ==============================================
    // Accessors
    // ==============================================
    
    public function getCalculatedFinalNetAmountAttribute(): string
    {
        return $this->calculateAmountByTarget('seller');
    }
    
    public function getCalculatedCustomerTotalAttribute(): string
    {
        return $this->calculateAmountByTarget('customer');
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
    
    /**
     * 根據目標計算金額（買家/賣家）
     */
    private function calculateAmountByTarget(string $target): string
    {
        $amount = $this->subtotal;
        $feeConfigs = config('business.fee_types', []);
        
        foreach ($feeConfigs as $key => $config) {
            if ($config['target'] === $target || $config['target'] === 'both') {
                $val = (string)($this->$key ?? '0.0000');
                $amount = $config['operator'] === 'add' 
                    ? bcadd($amount, $val, self::DECIMAL_PRECISION)
                    : bcsub($amount, $val, self::DECIMAL_PRECISION);
            }
        }
        
        return $amount;
    }
    
    // ==============================================
    // Accounting Rules
    // ==============================================
    
    public function getAccountingRules(string $eventType): array
    {
        $eventTypeKey = match($eventType) {
            'sale_revenue' => $this->getRevenueRuleType(),
			'sale_fee'     => $this->getFeeRuleType(),
			'sale_cost'    => 'sale_cost',
            default        => $eventType,
        };
        
        $rule = AccountingRule::where('event_type', $eventTypeKey)
            ->where('is_active', true)
            ->with(['lines' => fn($q) => $q->orderBy('sort_order')])
            ->first();
        
        if (!$rule) {
            throw new \RuntimeException("找不到會計規則：{$eventTypeKey}");
        }
        
        return $rule->lines->toArray();
    }
    
    private function getRevenueRuleType(): string
	{
		$channel = $this->channel?->code;
		
		return match(true) {
			$channel === 'shopee' || $this->payment_method === 'shopee_pay' => 'sale_revenue_shopee',
			$channel === 'facebook_live' => 'sale_revenue_facebook',
			$channel === 'live' => 'sale_revenue_live',
			default => 'sale_revenue_retail',  // 實體店（不分付款方式）
		};
	}

	private function getFeeRuleType(): string
	{
		$channel = $this->channel?->code;
		
		return match(true) {
			$channel === 'shopee' || $this->payment_method === 'shopee_pay' => 'sale_fee_shopee',
			$channel === 'facebook_live' => 'sale_fee_facebook',
			$channel === 'live' => 'sale_fee_live',
			default => 'sale_fee_retail',  // 實體店（不分付款方式）
		};
	}
    
    // ==============================================
    // Stock Management
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
        
        // 1. 外部先過濾基本狀態，減少不必要的 DB 連線佔用
		DB::transaction(function () use ($allowNegative) {
        
        // 2. 核心：在扣減庫存與計算成本前，必須對產品或庫存記錄進行 lockForUpdate()
        // 註：這應該在 $this->deductInventory() 內部實作，確保加權平均成本計算時數據不被夾擊
        $this->deductInventory($this->getCurrentItemsQuantity(), $allowNegative);
        
        // 3. 重新載入最新的關聯資料（包含明細與費用快照）
        $this->load(['items.product', 'fees']); 
        
        // 4. 拋出事件或直接執行傳票過帳
        // 嚴謹性檢查：在 postJournal 內部必須使用 BC Math 檢查：
        // sale_revenue 借貸必須平衡
        // sale_cost 必須 >= 0
        // sale_fee 如果當下沒有綁定任何費用（例如客人付現、無佣金），應自動跳過不產生空傳票
        $this->postJournal('sale_revenue');
        $this->postJournal('sale_cost');
        
        if ($this->fees->isNotEmpty()) {
            $this->postJournal('sale_fee');
        }
        
        // 5. 更新出庫時間與單據狀態
        $this->update([
            'stocked_out_at' => now(),
            //'status' => 'completed' // 建議增加狀態欄位以便索引
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
                $this->deductSingleProduct($productId, $warehouseId, $change, $allowNegative);
            } elseif ($change < 0) {
                $this->restoreSingleProduct($productId, $warehouseId, abs($change));
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
            $change = $item['quantity'] - $oldQty;
            if ($change != 0) {
                $changes[$key] = $change;
            }
            unset($oldQtyMap[$key]);
        }
        
        // 處理被刪除的商品
        foreach ($oldQtyMap as $key => $oldQty) {
            $changes[$key] = -$oldQty;
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
            $this->deductSingleProduct($productId, $warehouseId, $quantity, $allowNegative);
        }
    }
    
    /**
     * 扣減單一商品庫存
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
     * 回補單一商品庫存
     */
    private function restoreSingleProduct(int $productId, int $warehouseId, float $quantity): void
    {
        if ($quantity <= 0) return;
        
        Inventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->increment('quantity', $quantity);
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
    // Status Checks
    // ==============================================
    
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
        });
    }
    
    // ==============================================
    // Invoice Number Generator
    // ==============================================
    
    public static function generateInvoiceNumber(): string
    {
        return DB::transaction(function () {
            $prefix = Setting::get('so_prefix', 'SO-');
            $digits = (int) Setting::get('number_digits', 5);
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
            
            // 分離費用欄位
            $saleFields = array_diff_key($data, $feeConfigs);
            $sale = self::create($saleFields);
            
            // 建立銷售項目並快照成本
            foreach ($items as $item) {
                $warehouseId = $item['warehouse_id'] ?? $data['warehouse_id'] ?? null;
                $product = Product::find($item['product_id']);
                // ✅ 確保正確獲取成本
				$unitCost = $product?->cost ?? 0;
				
				// ✅ 如果成本為0，記錄警告
				if ($unitCost == 0) {
					\Log::warning('Product cost is zero when creating sale item', [
						'product_id' => $item['product_id'],
						'product_name' => $product?->name,
						'sale_id' => $sale->id,
					]);
				}
                $sale->items()->create([
                    'product_id'   	=> $item['product_id'],
                    'warehouse_id' 	=> $warehouseId,
                    'price'        	=> $item['price'],
                    'quantity'     	=> $item['quantity'],
                    'subtotal'     	=> bcmul((string)$item['quantity'], (string)$item['price'], self::DECIMAL_PRECISION),                    
					'unitCost' 		=> $unitCost,
					'unitCost_type' => gettype($unitCost),
            ]);
            }
            
            // 建立費用記錄
            $allowNegative = Setting::get('allow_negative_stock', false);
            $sale->deductInventory($sale->getCurrentItemsQuantity(), $allowNegative);
            
            foreach ($data as $key => $value) {
                if (isset($feeConfigs[$key]) && bccomp((string)$value, '0', self::DECIMAL_PRECISION) !== 0) {
                    $sale->fees()->create([
                        'shop_id'  => auth()->user()->shop_id ?? 1,
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
            
            // 保存舊資料用於庫存調整
            $oldItems = $this->items->map(fn($item) => [
                'product_id'   => $item->product_id,
                'warehouse_id' => $item->warehouse_id,
                'quantity'     => $item->quantity,
            ])->toArray();
            
            $feeConfigs = config('business.fee_types');
            $saleData = array_diff_key($data, $feeConfigs);
            
            // 更新基本資料
            $this->update($saleData);
            
            // 更新費用
            $this->fees()->delete();
            foreach ($data as $key => $value) {
                if (isset($feeConfigs[$key]) && (float)$value != 0) {
                    $this->fees()->create([
                        'shop_id'  => auth()->user()->shop_id ?? 1,
                        'fee_type' => $key,
                        'amount'   => $value,
                        'note'     => $feeConfigs[$key]['name'] ?? $key,
                    ]);
                }
            }
            
            // 更新銷售項目
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
                    'unit_cost'    => $product?->cost ?? 0,  // ✅ 修正欄位名稱
                ]);
            }
            
            // 調整庫存
            $newItems = collect($items)->map(fn($item) => [
                'product_id'   => $item['product_id'],
                'warehouse_id' => $item['warehouse_id'] ?? $saleData['warehouse_id'] ?? null,
                'quantity'     => $item['quantity'],
            ])->toArray();
            
            $allowNegative = Setting::get('allow_negative_stock', false);
            $this->adjustStockForEdit($oldItems, $newItems, $allowNegative);
            
            return $this;
        });
    }
	
	/**
     * 🛡️ 靜態多型容錯盾：防止全域監聽器或會計引擎動態盲踩 withTrashed() 導致系統 500 崩潰
     * 因為本模型不使用軟刪除，當被呼叫時直接返回查詢構造器本身，達成熱修復相容
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