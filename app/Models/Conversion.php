<?php
// app/Models/Conversion.php

namespace App\Models;

use App\Enums\WorkflowStatus;
use App\Models\Traits\ShopScoped;
use App\Traits\HasAccounting;
use App\Traits\HasWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Conversion extends Model
{
    use SoftDeletes, HasWorkflow, HasAccounting, ShopScoped;

    protected $table = 'conversions';

    protected $fillable = [
        'shop_id',
        'warehouse_id',
        'conversion_no',
        'status',
        'process_date',
        'user_id',
        'remark',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'status' => WorkflowStatus::class,
        'process_date' => 'datetime',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    private const DECIMAL_PRECISION = 4;
	
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
            // 審核流程
			['from' => 'pending', 'to' => 'approved', 'event' => 'approve', 'label' => '審核通過'],
			['from' => 'draft', 'to' => 'approved', 'event' => 'approve', 'label' => '審核通過'],
			
			// 過帳/結案
			['from' => 'approved', 'to' => 'completed', 'event' => 'post', 'label' => '庫存結轉並過帳'],
			
			// 取消
			['from' => 'pending', 'to' => 'cancelled', 'event' => 'cancel', 'label' => '取消'],
			['from' => 'draft', 'to' => 'cancelled', 'event' => 'cancel', 'label' => '取消'],
		];
    }

    // =========================================================================
    // HasAccounting Trait 所需方法
    // =========================================================================

    public static function getDocumentNumberField(): string
    {
        return 'conversion_no';
    }

    public function getDocumentNumber(): string
    {
        return $this->conversion_no;
    }

    public static function getReferenceType(): string
    {
        return 'conversion';
    }

    /**
     * 解析動態會計科目
     */
    public function resolveDynamicAccount(string $dynamicSpec, ?array $context = null): string
    {
        $parts = explode(':', $dynamicSpec);
        $prefix = $parts[0] ?? '';

        return match ($prefix) {
            'conversion' => $this->resolveConversionDynamicAccount($parts[1] ?? null, $context),
            'auto' => $this->resolveAutoDynamicAccount($parts[1] ?? null),
            default => $this->resolveDefaultAccount($dynamicSpec),
        };
    }

    /**
     * 拆裝模組的動態科目解析
     */
    private function resolveConversionDynamicAccount(?string $subType, ?array $context): string
    {
        // 損益科目直接回傳
        if ($subType === 'loss') {
            return '5711'; // 營業外支出
        }
        if ($subType === 'gain') {
            return '5301'; // 營業外收入
        }

        // 直接從資料庫載入 items.product
        $this->loadMissing(['items.product']);
        
        // 根據 subType 決定要取投入(type=1)還是產出(type=2)
        $type = ($subType === 'input') ? 1 : 2;
        $item = $this->items->where('type', $type)->first();
        
        if (!$item || !$item->product) {
            \Log::warning('Conversion item product not found', [
                'conversion_id' => $this->id,
                'type' => $type,
                'subType' => $subType,
            ]);
            return '140509';
        }
        
        // SKU 第一碼 → 14050x
        $sku = $item->product->sku ?? '';
        $firstChar = substr($sku, 0, 1);
        
        return '14050' . $firstChar;
    }

    private function resolveAutoDynamicAccount(?string $subType): string
    {
        return match ($subType) {
            'inventory' => config('business.accounting_accounts.cost.inventory', '1405'),
            'cost' => config('business.accounting_accounts.cost.cost_of_goods_sold', '5401'),
            default => '1405',
        };
    }

    private function resolveDefaultAccount(string $dynamicSpec): string
    {
        \Illuminate\Support\Facades\Log::warning("未預期的動態科目規格: {$dynamicSpec}", [
            'model' => get_class($this),
            'id' => $this->id,
        ]);

        return '140509';
    }

    /**
     * 解析金額來源
     */
    public function getAmountFromSource(string $source, mixed $context = null): string
    {
        $input = $this->getInputTotalCost();
        $output = $this->getOutputTotalCost();
        $variance = bcsub($input, $output, 4);
        
        return match ($source) {
            'input_total_cost' => $input,
            'output_total_cost' => $output,
            'cost_variance_loss' => $variance > 0 ? $variance : '0.0000',
            'cost_variance_gain' => $variance < 0 ? bcsub('0.0000', $variance, 4) : '0.0000',
            default => $this->getAttribute($source) ?? '0.0000',
        };
    }

    // =========================================================================
    // 金額計算方法
    // =========================================================================

    public function getInputTotalCost(): string
    {
        $total = '0.0000';
        $this->loadMissing(['items']);

        foreach ($this->items->where('type', 1) as $item) {
            $subtotal = bcmul(
                (string) ($item->cost_snapshot ?? '0.0000'),
                (string) ($item->quantity ?? '0'),
                4
            );
            $total = bcadd($total, $subtotal, 4);
        }

        return $total;
    }

    public function getOutputTotalCost(): string
    {
        $total = '0.0000';
        $this->loadMissing(['items']);

        foreach ($this->items->where('type', 2) as $item) {
            $subtotal = bcmul(
                (string) ($item->cost_snapshot ?? '0.0000'),
                (string) ($item->quantity ?? '0'),
                4
            );
            $total = bcadd($total, $subtotal, 4);
        }

        return $total;
    }

    /**
     * 取得成本差異（投入 - 產出）
     */
    public function getCostVariance(): string
    {
        $input = $this->getInputTotalCost();
        $output = $this->getOutputTotalCost();
        return bcsub($input, $output, 4);
    }

    // =========================================================================
    // 業務方法（參考採購退貨）
    // =========================================================================

    /**
     * 檢查是否可以過帳
     */
    public function canBePosted(): bool
    {
        return !$this->isFinalized() && $this->status !== WorkflowStatus::CANCELLED;
    }

    /**
     * 過帳（庫存異動 + 會計分錄）
     * 參考 PurchaseReturn::post()
     */
    public function post(): void
    {
        DB::transaction(function () {
            if (!$this->canBePosted()) {
                throw new \Exception("單據 #{$this->conversion_no} 目前狀態為 {$this->status->label()}，不符合過帳條件。");
            }

            // 1. 載入關聯資料
            $this->load(['items.product', 'warehouse']);

            // 2. 庫存異動
            foreach ($this->items as $item) {
                $productLabel = ($item->product->sku ?: '') . ' - ' . ($item->product->name ?: '未知商品');
                
                // type = 1: 投入（庫存減少）
                // type = 2: 產出（庫存增加）
                $quantity = $item->type == 1 
                    ? -abs($item->quantity)  // 減少
                    : abs($item->quantity);   // 增加

                $inventory = Inventory::where([
                    'shop_id' => $this->shop_id,
                    'warehouse_id' => $this->warehouse_id,
                    'product_id' => $item->product_id,
                ])->lockForUpdate()->first();

                if ($item->type == 1) {
                    // 投入：必須有庫存才能減少
                    if (!$inventory) {
                        throw new \Exception("商品 {$productLabel} 庫存記錄不存在，無法執行投入扣庫。");
                    }

                    $newQty = bcadd((string) $inventory->quantity, (string) $quantity, self::DECIMAL_PRECISION);
                    if (bccomp($newQty, '0', self::DECIMAL_PRECISION) < 0) {
                        throw new \Exception("商品 {$productLabel} 庫存不足。當前庫存：{$inventory->quantity}，投入數量：{$item->quantity}");
                    }
                    $inventory->quantity = $newQty;
                    $inventory->save();
                } else {
                    // 產出：如果沒有庫存記錄就新增
                    if ($inventory) {
                        $inventory->quantity = bcadd((string) $inventory->quantity, (string) $quantity, self::DECIMAL_PRECISION);
                        $inventory->save();
                    } else {
                        Inventory::create([
                            'shop_id' => $this->shop_id,
                            'warehouse_id' => $this->warehouse_id,
                            'product_id' => $item->product_id,
                            'quantity' => $quantity,
                            'cost' => $item->cost_snapshot ?? 0,
                        ]);
                    }
                }

                // 庫存流水
                DB::table('inventory_movements')->insert([
                    'shop_id' => $this->shop_id,
                    'warehouse_id' => $this->warehouse_id,
                    'product_id' => $item->product_id,
                    'quantity' => (float) $quantity,
                    'cost_snapshot' => $item->cost_snapshot ?? '0.0000',
                    'type' => $item->type == 1 ? 'conversion_input' : 'conversion_output',
                    'reference' => $this->conversion_no,
                    'remark' => $item->type == 1 ? '拆裝投入扣庫' : '拆裝產出入庫',
                    'user_id' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 3. 會計過帳（使用 conversion_post 規則）
            $this->postJournal('conversion_post');

            // 4. 更新狀態為已結案
            $this->status = WorkflowStatus::COMPLETED;
            $this->approved_by = auth()->id();
            $this->approved_at = now();
            $this->save();

            \Log::info("拆裝單過帳完成", [
                'conversion_id' => $this->id,
                'conversion_no' => $this->conversion_no,
                'input_total_cost' => $this->getInputTotalCost(),
                'output_total_cost' => $this->getOutputTotalCost(),
                'variance' => $this->getCostVariance(),
            ]);
        });
    }

    // =========================================================================
    // 關聯關係
    // =========================================================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function items()
    {
        return $this->hasMany(ConversionItem::class);
    }

    // =========================================================================
    // Boot 事件
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->conversion_no)) {
                $model->conversion_no = $model->generateConversionNo();
            }
            if (empty($model->user_id)) {
                $model->user_id = auth()->id();
            }
            if (empty($model->shop_id)) {
                $model->shop_id = auth()->user()?->shop_id ?? 1;
            }
            if (empty($model->status)) {
                $model->status = WorkflowStatus::DRAFT;
            }
        });

        static::deleting(function ($conversion) {
            if ($conversion->isFinalized()) {
                throw new \Exception('已結案或已取消的拆裝單無法刪除');
            }
        });
    }

    public static function generateConversionNo(): string
    {
        $prefix = 'IC-';
        $date = now()->format('Ymd');

        $lastOrder = self::where('conversion_no', 'like', $prefix . $date . '%')
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastOrder ? (int) substr($lastOrder->conversion_no, -4) + 1 : 1;
        $digits = 4;

        return $prefix . $date . str_pad($sequence, $digits, '0', STR_PAD_LEFT);
    }
}