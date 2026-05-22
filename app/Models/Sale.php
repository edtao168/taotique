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
		'subtotal' => 'decimal:4',
        'customer_total' => 'decimal:4',
        'final_net_amount' => 'decimal:4',
    ];

    // 快取費用類型配置
    private static ?array $feeTypesCache = null;
	
    /**
     * 獲取銷售事件的會計規則
     * 🎯 完全參照 AccountingRule 架構，回歸資料庫動態配置
     */
	public function getAccountingRules(string $eventType): array
	{
		// 成本規則統一用 sale_cost
		if ($eventType === 'sale_cost') {
			return $this->buildDynamicCostRules();
		} 
		// 收入規則根據渠道動態選擇
		else if ($eventType === 'sale_revenue') {
			$ruleEventType = $this->getRevenueRuleType();
		}
		else {
			$ruleEventType = $eventType;
		}
		
		$rule = AccountingRule::where('event_type', $ruleEventType)
			->where('shop_id', $this->shop_id ?? 1)
			->where('is_active', true)
			->with(['lines' => function($q) {
				$q->where('is_active', true)->orderBy('sort_order');
			}])
			->first();

		if (!$rule) {
			throw new \RuntimeException("找不到會計規則：{$ruleEventType}");
		}

		$formattedRules = [];
		foreach ($rule->lines as $line) {
			$formattedRules[] = [
				'account_code' => $line->account->code,
				'amount_source' => $line->amount_source,
				'side' => $line->entry_type,
				'ratio' => (string) ($line->ratio ?? '1.0000'),
			];
		}

		return $formattedRules;
	}
	
	/**
	 * 動態產生成本規則（從庫存系統即時計算）
	 */
	private function buildDynamicCostRules(): array
	{
		// 計算總成本
		$totalCost = $this->calculateTotalCost();
		
		if (bccomp($totalCost, '0', 4) === 0) {
			// 沒有成本資料，不產生分錄
			return [];
		}
		
		// 直接回傳帶金額的規則（不用 amount_source）
		return [
			[
				'account_code' => '5401',  // 主營業務成本
				'amount' => $totalCost,
				'side' => 'debit',
				'note' => '銷售成本結轉',
			],
			[
				'account_code' => '1405',   // 庫存商品
				'amount' => $totalCost,
				'side' => 'credit',
				'note' => '庫存商品出庫',
			],
		];
	}

	/**
	 * 計算銷售總成本
	 */
	private function calculateTotalCost(): string
	{
		$totalCost = '0.0000';
		
		foreach ($this->items as $item) {
			// 從 Product 取得成本
			$unitCost = $item->product->cost ?? 0;
			
			$itemCost = bcmul((string) $unitCost, (string) $item->quantity, 4);
			$totalCost = bcadd($totalCost, $itemCost, 4);
		}
		
		return $totalCost;
	}

	/**
	 * 根據銷售渠道決定使用哪個收入規則
	 */
	private function getRevenueRuleType(): string
	{
		// 可根據 channel_id 或 payment_method 判斷
		$channel = $this->channel?->code ?? $this->payment_method;
		
		return match($channel) {
			'retail', 'cash' => 'sale_revenue_cash_retail',
			'shopee', 'shopee_pay' => 'sale_revenue_shopee',
			'facebook' => 'sale_revenue_facebook',
			'live' => 'sale_revenue_live',
			default => 'sale_revenue_cash_retail',
		};
	}

    /**
     * 收入確認規則
     * 
     * T字帳範例（售價 1,000，運費 60，手續費 30）：
     * 借：1121 應收帳款      1,030  (1,000 + 60 - 30)
     * 借：5601 銷售費用         30  (平台手續費)
     * 貸：5001 主營收入      1,000
     * 貸：2241 代收運費         60
     */
    private function buildRevenueRules(): array
    {
        $rules = [];

        // 1. 借方：應收帳款/現金（買家實付）
        $rules[] = [
            'account_code'  => $this->resolvePaymentAccountCode(),
            'amount_source' => 'customer_total',
            'side'          => 'debit',
            'note'          => '銷售收入-應收/現金',
        ];

        // 2. 貸方：主營業務收入
        $rules[] = [
            'account_code'  => '5001',
            'amount_source' => 'subtotal',
            'side'          => 'credit',
            'note'          => '銷售收入-主營收入',
        ];

        // 3. 動態費用項目
        $feeConfigs = config('business.fee_types', []);
        foreach ($feeConfigs as $feeType => $config) {
            $amount = (string) ($this->$feeType ?? '0.0000');
            if (bccomp($amount, '0', 4) === 0) continue;

            // 決定借貸方向與科目
            [$side, $code] = $this->resolveFeeDirectionAndCode($feeType, $config);

            $rules[] = [
                'account_code'  => $code,
                'amount_source' => "fees.{$feeType}",
                'side'          => $side,
                'note'          => $config['name'] ?? $feeType,
            ];
        }

        return $rules;
    }

    /**
     * 成本結轉規則
     * 
     * T字帳範例（成本 400）：
     * 借：5401 主營業務成本    400
     * 貸：1405 庫存商品        400
     */
    private function buildCostRules(): array
    {
        return [
            [
                'account_code'  => '5401',
                'amount_source' => 'items.sum:unit_cost_twd*quantity',
                'side'          => 'debit',
                'note'          => '銷售成本結轉',
            ],
            [
                'account_code'  => '1405',
                'amount_source' => 'items.sum:unit_cost_twd*quantity',
                'side'          => 'credit',
                'note'          => '庫存商品出庫',
            ],
        ];
    }

    /**
     * 解析付款方式對應科目代碼
     */
    private function resolvePaymentAccountCode(): string
    {
        return match ($this->payment_method ?? 'default') {
            'cash'  => '1001',
            'bank'  => '1002',
            'line_pay', 'shopee', 'ecpay', 'credit_card' => '1122',
            default => '1121',
        };
    }

    /**
     * 解析費用方向與科目代碼
     * 
     * 回傳: [$side, $accountCode]
     */
    private function resolveFeeDirectionAndCode(string $feeType, array $config): array
    {
        // 從配置讀取自定義科目
        $code = $config['account_code'] ?? match ($feeType) {
            'shipping'          => '2241',
            'tax'               => '2221',
            'platform_fee'      => '5601',
            'payment_fee'       => '5601',
            'coupon_discount'   => '5001',
            default             => '6602',
        };

        // 決定方向
        $side = match(true) {
            $config['target'] === 'customer' && $config['operator'] === 'sub' => 'debit',   // 折扣沖收入
            $config['target'] === 'seller' && $config['operator'] === 'add' => 'debit',     // 費用
            $config['target'] === 'seller' && $config['operator'] === 'sub' => 'credit',   // 補貼
            $config['target'] === 'customer' && $config['operator'] === 'add' => 'credit',  // 代收
            default => 'debit',
        };

        return [$side, $code];
    }
	
	/**
     * 執行銷售出庫（精簡後）
     */
    public function processStockOut(bool $allowNegative = false, array $oldItemsQty = []): void
    {
        if ($this->stocked_out_at) {
            throw new \Exception("銷售單 {$this->invoice_number} 已出庫。");
        }

        DB::transaction(function () use ($allowNegative, $oldItemsQty) {
            // 1. 庫存異動 + 成本快照（原有邏輯不變）
            foreach ($this->items as $item) {
                // ... 庫存扣減與成本快照 ...
            }

            // 2. 【統一過帳】收入確認
            $this->postJournal('sale_revenue');

            // 3. 【統一過帳】成本結轉
            $this->postJournal('sale_cost');

            // 4. 標記已出庫
            $this->update(['stocked_out_at' => now()]);
        }, 3);
    }
	
	/**
     * 如果已有關聯的 SalesReturn 
	 * 且狀態為 pending 或 completed，則鎖定
     */
	public function isLocked(): bool
	{
		return $this->returns()->whereIn('status', ['pending', 'approved', 'completed'])->exists();
	}
	
	/**
     * 第一階段判斷：是否存在任何（非作廢）的退貨紀錄
     */
    public function hasReturnRecords(): bool
    {
        // 排除已取消 (cancelled) 的退貨單，僅鎖定處理中、已審核或已完成的單據
        return $this->returns()
            ->whereIn('status', ['pending', 'approved', 'completed'])
            ->exists();
    }
	
	/**
     * 第二階段：綜合判斷是否允許變動（刪除、修改、再次退貨）
     */
    public function canBeModified(): bool
    {
        // 如果已經有退貨紀錄，則不允許任何變動
        if ($this->hasReturnRecords()) {
            return false;
        }

        // 此外可增加其他判斷，例如：單據是否已結案 (completed)
        return $this->status !== 'completed';
    }

    /**
     * 動態攔截所有費用屬性
     */
    public function getAttribute($key)
    {
        // 初始化快取
        if (self::$feeTypesCache === null) {
            self::$feeTypesCache = config('business.fee_types', []);
        }
        
        // 如果是定義的費用類型，從 sale_fees 計算
        if (isset(self::$feeTypesCache[$key])) {
            // 關聯已載入時直接計算
            if ($this->relationLoaded('fees')) {
                return (string) $this->fees->where('fee_type', $key)->sum('amount');
            }
            // 未載入時使用查詢（避免 N+1）
            return (string) $this->fees()->where('fee_type', $key)->sum('amount');
        }
        
        // 其他屬性走預設邏輯
        return parent::getAttribute($key);
    }

    protected static function booted()
    {
        static::deleting(function ($sale) {
			if ($sale->hasReturnRecords()) {
				throw new \Exception('此銷售單已有退貨紀錄，禁止刪除。');
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

            if ($lastOrder) {
                $lastNumber = (int) substr($lastOrder->invoice_number, -$digits);
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            return $fullPrefix . str_pad($nextNumber, $digits, '0', STR_PAD_LEFT);
        });
    }
    
    public function getPaymentMethodNameAttribute(): string
    {
        return collect(config('business.payment_methods'))
            ->firstWhere('id', $this->payment_method)['name'] ?? $this->payment_method;
    }

    /**
	 * 【修正】解決 $sale 變數未定義的問題
	 */
	public static function createWithCalculations(array $data, array $items)
	{
		return DB::transaction(function () use ($data, $items) {
            $feeConfigs = config('business.fee_types', []);
            
            // 1. 過濾掉不屬於 sales 主表的欄位（即費用欄位）
            $saleFields = array_diff_key($data, $feeConfigs);
            
            // 2. 建立 Sale 主表紀錄
            $sale = self::create($saleFields);
            
            $allowNegative = Setting::get('allow_negative_stock', false);

            foreach ($items as $item) {
                $warehouseId = $item['warehouse_id'] ?? $data['warehouse_id'];
                
                $inventory = Inventory::where('product_id', $item['product_id'])
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->first();

                $currentQty = $inventory ? $inventory->quantity : 0;

                if (!$allowNegative && bccomp((string)$currentQty, (string)$item['quantity'], 4) === -1) {
                    throw new \Exception("商品 ID {$item['product_id']} 庫存不足。");
                }

                $newQty = bcsub((string)$currentQty, (string)$item['quantity'], 4);

                Inventory::updateOrCreate(
                    ['product_id' => $item['product_id'], 'warehouse_id' => $warehouseId],
                    ['quantity' => $newQty]
                );

                $sale->items()->create([
                    'product_id'   => $item['product_id'],
                    'warehouse_id' => $warehouseId,
                    'price'        => $item['price'],
                    'quantity'     => $item['quantity'],
                    'subtotal'     => bcmul((string)$item['quantity'], (string)$item['price'], 4),
                ]);
            }

            // 3. 儲存費用到 sale_fees 關聯表
            foreach ($data as $key => $value) {
                // 檢查是否為定義的費用類型，且金額不為 0
                if (isset($feeConfigs[$key]) && bccomp((string)$value, '0', 4) !== 0) {
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

    public function updateWithCalculations(array $data, array $items)
    {
        return DB::transaction(function () use ($data, $items) {
            \Log::info('Sale::updateWithCalculations 開始', ['sale_id' => $this->id]);
            
            $oldItems = $this->items->keyBy(function ($item) {
                return $item->product_id . '-' . $item->warehouse_id;
            });

            $feeConfigs = config('business.fee_types');
            $saleData = array_diff_key($data, $feeConfigs);
            
            $this->update($saleData);

            // 更新費用明細
            $this->fees()->delete();
            foreach ($data as $key => $value) {
                if (isset($feeConfigs[$key]) && (float)$value != 0) {
                    $this->fees()->create([
                        'shop_id'  => auth()->user()->shop_id ?? 1,
                        'sale_id'  => $this->id,
                        'fee_type' => $key,
                        'amount'   => $value,
                        'note'     => $feeConfigs[$key]['name'],
                    ]);
                }
            }

            $this->items()->delete();
            
            foreach ($items as $item) {
                if (empty($item['product_id'])) continue;

                $warehouseId = $item['warehouse_id'] ?? $saleData['warehouse_id'];

                $this->items()->create([
                    'product_id'   => $item['product_id'],
                    'warehouse_id' => $warehouseId,
                    'price'        => $item['price'],
                    'quantity'     => $item['quantity'],
                    'subtotal'     => bcmul((string)$item['quantity'], (string)$item['price'], 2),
                ]);

                $key = $item['product_id'] . '-' . $warehouseId;
                $oldQty = $oldItems->has($key) ? (float)$oldItems[$key]->quantity : 0;
                $newQty = (float)$item['quantity'];
                $diff = $newQty - $oldQty;

                if ($diff > 0) {
                    $this->processStockReduction($item['product_id'], $warehouseId, $diff);
                } elseif ($diff < 0) {
                    $this->restoreStock($item['product_id'], $warehouseId, abs($diff));
                }
            }

            return $this;
        });
    }
    
    protected function processStockReduction($productId, $warehouseId, $amount)
    {
        $needed = $amount;
        $stocks = Inventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'in_stock')
            ->where('quantity', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($stocks as $inv) {
            if ($needed <= 0) break;
            if ($inv->quantity >= $needed) {
                $inv->decrement('quantity', $needed);
                $needed = 0;
            } else {
                $needed -= $inv->quantity;
                $inv->update(['quantity' => 0, 'status' => 'sold']);
            }
            if ($inv->fresh()->quantity <= 0) $inv->update(['status' => 'sold']);
        }

        if ($needed > 0) throw new \Exception("庫存不足，尚缺 {$needed} 單位");
    }

    protected function restoreStock($productId, $warehouseId, $amount)
    {
        $inv = Inventory::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'sold')
            ->orderBy('updated_at', 'desc')
            ->first();
        
        if ($inv) {
            $inv->increment('quantity', $amount);
            $inv->update(['status' => 'in_stock']);
        } else {            
            throw new \Exception("無法還原庫存：找不到對應的庫存紀錄");    
        }
    }
	
	// 取得銷售日期
	public function getSoldDateAttribute()
	{
		return $this->sold_at->format('Y-m-d');
	}

	// 取得成交時間
	public function getSoldTimeAttribute()
	{
		return $this->sold_at->format('H:i');
	}
    
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
	
	/**
     * 關聯日記帳 (多型或一對一)
     */
    public function journal()
    {
        return $this->morphOne(Journal::class, 'reference');
    }
}