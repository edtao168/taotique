<?php
// app/Models/Conversion.php

namespace App\Models;

use App\Traits\HasAccounting;
use App\Traits\HasShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Conversion extends Model
{
    use SoftDeletes, HasShop, HasAccounting;

    protected $fillable = [
        'shop_id', 
        'warehouse_id',
        'conversion_no', 
        'process_date', 
        'user_id', 
        'remark'
    ];

    protected $casts = [
        'process_date' => 'date',
    ];

    // =========================================================================
    // SECTION: Eloquent 事件（對齊 Sale 模型）
    // =========================================================================

    protected static function booted()
    {
        static::creating(function ($conversion) {
            if (empty($conversion->conversion_no)) {
                $conversion->conversion_no = self::generateConversionNo();
            }
            if (empty($conversion->shop_id)) {
                $conversion->shop_id = auth()->user()->shop_id ?? 1;
            }
            if (empty($conversion->user_id)) {
                $conversion->user_id = auth()->id();
            }
        });
    }

    // =========================================================================
    // SECTION: 單號生成（對齊 Sale 模型的 generateInvoiceNumber）
    // =========================================================================

    public static function generateConversionNo(): string
    {
        return DB::transaction(function () {
            $prefix = Setting::get('ic_prefix', 'IC-');
            $digits = (int) Setting::get('conversion_number_digits', 4);
            $datePart = now()->format('Ymd');
            $fullPrefix = $prefix . $datePart;

            $lastConversion = self::where('conversion_no', 'like', "{$fullPrefix}%")
                ->lockForUpdate()
                ->orderBy('conversion_no', 'desc')
                ->first();

            $nextNumber = $lastConversion 
                ? (int) substr($lastConversion->conversion_no, -$digits) + 1 
                : 1;

            return $fullPrefix . str_pad($nextNumber, $digits, '0', STR_PAD_LEFT);
        });
    }

    // =========================================================================
    // SECTION: 會計自動規則對齊介面
    // =========================================================================

    public static function getDocumentNumberField(): string
    {
        return 'conversion_no';
    }

    public function getDocumentNumber(): string
    {
        return $this->conversion_no ?? 'IC-' . $this->id;
    }

    public static function getReferenceType(): string
    {
        return 'conversion';
    }

    /**
     * 解析金額來源（供 AccountingService 呼叫）
     */
    public function getAmountFromSource(string $amountSource, ?string $eventType = null): string
    {
        return match($amountSource) {
            'input_total_cost'  => $this->getInputTotalCost(),
            'output_total_cost' => $this->getOutputTotalCost(),
            default => $this->getAttribute($amountSource) ?? '0.0000',
        };
    }

    /**
     * 計算領料投入總成本
     */
    public function getInputTotalCost(): string
    {
        $total = '0.0000';
        foreach ($this->items as $item) {
            if ($item->type == 1) {
                $total = bcadd($total, bcmul($item->cost_snapshot, $item->quantity, 4), 4);
            }
        }
        return $total;
    }

    /**
     * 計算成品產出總成本
     */
    public function getOutputTotalCost(): string
    {
        $total = '0.0000';
        foreach ($this->items as $item) {
            if ($item->type == 2) {
                $total = bcadd($total, bcmul($item->cost_snapshot, $item->quantity, 4), 4);
            }
        }
        return $total;
    }

    // =========================================================================
    // SECTION: 動態科目解析
    // =========================================================================

    /**
     * 動態科目解析（供 HasAccounting Trait 使用）
     */
    public function resolveDynamicAccount(string $dynamicSpec, ?string $context = null): string
    {
        $parts = explode(':', $dynamicSpec);
        $domain = $parts[0] ?? '';
        $type = $parts[1] ?? '';
        
        if ($domain === 'conversion') {
            return $this->resolveConversionAccount($type);
        }
        
        return match($domain) {
            'auto' => $this->resolveAutoAccount($type),
            default => throw new \RuntimeException("未知的動態科目網域: {$domain}"),
        };
    }

    /**
     * 處理拆裝模組動態科目
     */
    private function resolveConversionAccount(string $type): string
    {
        return match($type) {
            'input'  => $this->getAccountForInput(),
            'output' => $this->getAccountForOutput(),
            default  => '140503',
        };
    }

    /**
     * 取得投入科目（貸方）
     * 根據投入的商品類型決定科目
     */
    private function getAccountForInput(): string
    {
        foreach ($this->items as $item) {
            if ($item->type == 1 && $item->product) {
                $product = $item->product;
                $categoryCode = $product->category_code ?? '';
                
                // 如果是成品拆解，投入的是成品
                if ($this->isDisassembly()) {
                    return $this->getFinishedGoodsAccount($categoryCode);
                }
                
                // 組裝作業：投入的是半成品
                return '140509';  // 配件半成品
            }
        }
        return '140509';
    }

    /**
     * 取得產出科目（借方）
     * 根據產出的商品類型決定科目
     */
    private function getAccountForOutput(): string
    {
        foreach ($this->items as $item) {
            if ($item->type == 2 && $item->product) {
                $product = $item->product;
                $categoryCode = $product->category_code ?? '';
                
                // 組裝作業：產出的是成品
                if (!$this->isDisassembly()) {
                    return $this->getFinishedGoodsAccount($categoryCode);
                }
                
                // 拆解作業：產出的是半成品
                return '140509';  // 配件半成品
            }
        }
        return '140503';
    }

    /**
     * 判斷是否為拆解作業（成品 → 半成品）
     */
    private function isDisassembly(): bool
    {
        $hasFinishedGoodInput = false;
        $hasRawMaterialOutput = false;
        
        foreach ($this->items as $item) {
            if ($item->type == 1 && $item->product) {
                $categoryCode = $item->product->category_code ?? '';
                // 投入的是成品（非半成品）
                if (!in_array($categoryCode, ['part', 'accessory', 'raw_material'])) {
                    $hasFinishedGoodInput = true;
                }
            }
            
            if ($item->type == 2 && $item->product) {
                $categoryCode = $item->product->category_code ?? '';
                // 產出的是半成品
                if (in_array($categoryCode, ['part', 'accessory', 'raw_material'])) {
                    $hasRawMaterialOutput = true;
                }
            }
        }
        
        return $hasFinishedGoodInput && $hasRawMaterialOutput;
    }

    /**
     * 取得成品科目（依產品分類）
     */
    private function getFinishedGoodsAccount(string $categoryCode): string
    {
        return match($categoryCode) {
            'pendant'   => '140501',   // 吊墜項鍊
            'bracelet'  => '140502',   // 手鍊手鐲
            'earring'   => '140505',   // 耳環
            'ring'      => '140506',   // 戒指
            default     => '140503',   // 百貨
        };
    }

    /**
     * 處理 auto 域動態科目
     */
    private function resolveAutoAccount(string $type): string
    {
        return match($type) {
            'inventory' => '140501',
            'cost'      => '400101',
            default     => '140501',
        };
    }

    // =========================================================================
    // SECTION: 庫存異動與會計過帳（核心方法）
    // =========================================================================

    /**
     * 執行拆裝作業過帳
     */
    public function post(string $varianceTreatment = 'expense'): void
    {
        DB::transaction(function () use ($varianceTreatment) {
            $this->updateInventory();
            $this->postJournal($varianceTreatment);
            
            logger("拆裝作業過帳完成", [
                'conversion_id' => $this->id,
                'conversion_no' => $this->conversion_no,
                'type' => $this->isDisassembly() ? '拆解' : '組裝',
                'input_total' => $this->getInputTotalCost(),
                'output_total' => $this->getOutputTotalCost(),
            ]);
        });
    }

    /**
     * 更新庫存
     */
    protected function updateInventory(): void
    {
        foreach ($this->items as $item) {
            $inventory = Inventory::where('shop_id', $this->shop_id)
                ->where('warehouse_id', $item->warehouse_id)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->firstOrCreate([
                    'shop_id' => $this->shop_id,
                    'warehouse_id' => $item->warehouse_id,
                    'product_id' => $item->product_id,
                ], ['quantity' => '0.0000', 'cost' => '0.0000']);

            if ($item->type === 1) { 
                // 投入：扣庫存
                $inventory->quantity = bcsub($inventory->quantity, $item->quantity, 4);
                $costSnapshot = $inventory->cost;
            } else {
                // 產出：加庫存，WAC 計算
                $newQty = $item->quantity;
                $newPrice = $item->cost_snapshot;

                $currentValue = bcmul($inventory->quantity, $inventory->cost, 4);
                $addedValue = bcmul($newQty, $newPrice, 4);
                $totalQty = bcadd($inventory->quantity, $newQty, 4);
                
                if (bccomp($totalQty, '0', 4) > 0) {
                    $totalValue = bcadd($currentValue, $addedValue, 4);
                    $inventory->cost = bcdiv($totalValue, $totalQty, 4);
                }
                $inventory->quantity = $totalQty;
                $costSnapshot = $newPrice;
            }
            $inventory->save();

            InventoryMovement::create([
                'shop_id' => $this->shop_id,
                'product_id' => $item->product_id,
                'warehouse_id' => $item->warehouse_id,
                'quantity' => ($item->type === 1) ? bcmul($item->quantity, '-1', 4) : $item->quantity,
                'cost_snapshot' => $costSnapshot,
                'type' => 'CONVERSION',
                'reference' => $this->conversion_no,
                'user_id' => $this->user_id,
            ]);
        }
    }

    /**
     * 會計過帳
     */
    protected function postJournal(string $varianceTreatment): void
    {
        $entries = [];
        
        foreach ($this->items as $item) {
            $amount = bcmul($item->cost_snapshot, $item->quantity, 4);
            
            if ($item->type == 2) {
                // 產出：借方
                $entries[] = [
                    'entry_type' => 'debit',
                    'account_code' => $this->getAccountForOutput(),
                    'amount' => $amount,
                    'description' => "拆裝產出：{$item->product->name}",
                ];
            } else {
                // 投入：貸方
                $entries[] = [
                    'entry_type' => 'credit',
                    'account_code' => $this->getAccountForInput(),
                    'amount' => $amount,
                    'description' => "拆裝投入：{$item->product->name}",
                ];
            }
        }
        
        // 處理成本差異（若有）
        $inputTotal = $this->getInputTotalCost();
        $outputTotal = $this->getOutputTotalCost();
        $variance = bcsub($inputTotal, $outputTotal, 4);
        
        if (bccomp($variance, '0', 4) !== 0) {
            $entries = $this->addVarianceEntry($entries, $variance, $varianceTreatment);
        }
        
        if (empty($entries)) {
            throw new \Exception("拆裝單 {$this->conversion_no} 無有效明細");
        }
        
        $accountingService = app(\App\Services\AccountingService::class);
        $journal = $accountingService->createJournalFromEntries(
            documentable: $this,
            entries: $entries,
            description: "拆裝作業 - {$this->conversion_no}",
            journalDate: $this->process_date,
            referenceNo: $this->conversion_no,
        );
        
        if (!$journal) {
            throw new \Exception("會計過帳失敗");
        }
    }

    /**
     * 添加成本差異分錄
     */
    private function addVarianceEntry(array $entries, string $variance, string $treatment): array
    {
        $isLoss = bccomp($variance, '0', 4) > 0; // 耗損（投入 > 產出）
        $absVariance = $isLoss ? $variance : bcsub('0', $variance, 4);
        
        $varianceAccount = match($treatment) {
            'expense' => '560101',      // 製造費用
            'capitalize' => '140509',   // 在製品成本
            'inventory' => '540101',    // 存貨盤損益
            default => '560101',
        };
        
        if ($isLoss) {
            // 耗損：借方費用
            $entries[] = [
                'entry_type' => 'debit',
                'account_code' => $varianceAccount,
                'amount' => $absVariance,
                'description' => "拆裝耗損",
            ];
        } else {
            // 盤盈：貸方沖減成本
            $entries[] = [
                'entry_type' => 'credit',
                'account_code' => $varianceAccount,
                'amount' => $absVariance,
                'description' => "拆裝盤盈",
            ];
        }
        
        return $entries;
    }

    // =========================================================================
    // SECTION: 關聯關係
    // =========================================================================

    public function items(): HasMany 
    { 
        return $this->hasMany(ConversionItem::class); 
    }
    
    public function user(): BelongsTo 
    { 
        return $this->belongsTo(User::class); 
    }
    
    public function warehouse(): BelongsTo 
    { 
        return $this->belongsTo(Warehouse::class); 
    }

    public function fees(): HasMany
    {
        return $this->hasMany(ConversionItem::class, 'conversion_id')->whereRaw('1 = 0');
    }
}