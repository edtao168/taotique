<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $table = 'sale_items';
    
    protected $fillable = [
        'sale_id',
        'product_id',
        'warehouse_id',		
        'price',
        'quantity',
        'subtotal',
        'unit_cost',
        'original_unit_cost',
        'original_currency',
        'exchange_rate',
    ];
    
    protected $casts = [
        'quantity'           => 'decimal:4',
        'price'              => 'decimal:4',
        'subtotal'           => 'decimal:4',
        'unit_cost'          => 'decimal:4',
        'original_unit_cost' => 'decimal:4',
        'exchange_rate'      => 'decimal:6',
    ];
    
    // total_cost 由資料庫 generated column 自動處理，不需在 Model 中定義
    
    /**
     * 取得總成本（輔助方法）
     */
    public function getTotalCostAttribute(): string
    {
        return bcmul((string)$this->unit_cost, (string)$this->quantity, 4);
    }
    
    /**
     * 取得原始總成本（若有原始幣別）
     */
    public function getOriginalTotalCostAttribute(): ?string
    {
        if ($this->original_unit_cost && $this->quantity) {
            return bcmul((string)$this->original_unit_cost, (string)$this->quantity, 4);
        }
        return null;
    }
    
    /**
     * 轉換成本幣別（例如：原始幣別 → 功能幣別）
     */
    public function convertCost(string $targetCurrency, float $rate): void
    {
        if ($this->original_currency && $this->original_unit_cost) {
            $this->unit_cost = bcmul((string)$this->original_unit_cost, (string)$rate, 4);
            $this->exchange_rate = $rate;
            $this->save();
        }
    }
    
    // ==============================================
    // Relationships
    // ==============================================
    
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
    
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}