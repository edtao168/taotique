<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    protected $fillable = [
		'name',
		'sku',
		'is_unique',
		'price',
		'cost',
		'min_stock',
		'unit',
		'category_code',
		'bb_code',
		'c_code',
		'remark',
		'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:4',
		'is_unique' => 'boolean',
        'is_active' => 'boolean',
    ];
	
	/**
     * 定義 full_display_name 屬性	
     */	
	public function getFullDisplayNameAttribute()
    {
        return "[{$this->sku}] {$this->name}";
    }

    /**
     * 獲取商品的銷售項目
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * 獲取商品的銷售記錄（透過銷售項目）
     */
    public function sales()
    {
        return $this->hasManyThrough(Sale::class, SaleItem::class);
    }
	
	/**
	 * 格式化顯示成本 (TWD)
	 */
	public function getFormattedCostAttribute(): string
	{
		return 'NT$ ' . number_format($this->cost, 2);
	}
	
	// 取得所有倉庫的總庫存數量
	public function getTotalStockAttribute(): string
	{
		// 直接加總該商品在所有(目前店鋪)倉庫的數量
		return $this->inventories->sum('quantity'); 
	}
	
	/**
	 * 取得該商品的平均進貨成本 (使用 bcmath)
	 */
	public function getAverageCostAttribute(): string
	{
		$inventories = $this->hasMany(Inventory::class)->where('quantity', '>', 0)->get();
		
		if ($inventories->isEmpty()) return '0.00';

		$totalValue = '0.00';
		$totalQty = '0';

		foreach ($inventories as $inv) {
			// 總價值 = 數量 * 成本
			$itemValue = bcmul($inv->quantity, $inv->cost, 4);
			$totalValue = bcadd($totalValue, $itemValue, 4);
			$totalQty = bcadd($totalQty, $inv->quantity, 0);
		}

		return bcdiv($totalValue, $totalQty, 2);
	}

	/**
     * 定義與 Inventory 的關聯
     */
    public function inventories()
	{
		return $this->hasMany(Inventory::class);
	}

	/**
	 * 取得所有商品的庫存總價值 (加權平均成本 * 庫存量)
	 */
	public static function totalInventoryValue(): string
	{
		// 直接加總所有庫存記錄，不考慮倉庫所屬店鋪
		$total = self::join('inventories', 'products.id', '=', 'inventories.product_id')
			->select(DB::raw('SUM(inventories.quantity * products.cost) as total_value'))
			->value('total_value') ?? '0';

		return (string)$total;
	}
	
	/**
	 * 多人多店的擴展接口
	 */
	public function scopeInCurrentShop($query)
	{
		// 初期一人店：先固定過濾 shop_id 為 1
		// 未來多人店：改為 auth()->user()->shop_id
		return $query->whereHas('inventories.warehouse', function ($q) {
			$q->where('shop_id', 1); 
		}); 
	}

	// 取得包含分倉庫存的查詢
	public function scopeWithStockDistributions($query)
	{
		return $query->with(['inventories.warehouse.shop']);
	}

	// 快速判斷是否低於警戒水位
	public function getIsLowStockAttribute(): bool
	{
		return $this->total_stock <= $this->min_stock;
	}
	
	/**
     * 更新產品成本（加權平均法）
     * * @param string $inboundQuantity 入庫數量
     * @param string $inboundCostTWD 入庫單價（已換算為本幣）
     */
    public function updateWeightedAverageCost(string $inboundQuantity, string $inboundCostTWD)
    {
        // 1. 取得當前庫存（不包含本次入庫）
		$currentQuantity = (string) $this->inventories()
			->where('created_at', '<', now()) // 排除本次新增的記錄
			->sum('quantity');
		
		$currentCost = (string) $this->cost;

		// 2. 🛡️ 防護機制：首次進貨或成本為 0 時，直接使用新進貨成本
		if (bccomp($currentQuantity, '0', 4) <= 0 || bccomp($currentCost, '0', 4) <= 0) {
			$this->update(['cost' => $inboundCostTWD]);
			return;
		}

        // 3. 計算當前總價值: Current Qty * Current Cost
        $currentTotalValue = bcmul($currentQuantity, $currentCost, 4);

        // 4. 計算新入庫總價值: Inbound Qty * Inbound Cost
        $inboundTotalValue = bcmul($inboundQuantity, $inboundCostTWD, 4);

        // 5. 計算新的總數量
        $newTotalQuantity = bcadd($currentQuantity, $inboundQuantity, 4);

        // 6. 計算新的加權平均單價: (Total Value) / (Total Qty)
		$newTotalValue = bcadd($currentTotalValue, $inboundTotalValue, 4);
		$newCost = bcdiv($newTotalValue, $newTotalQuantity, 4);
		
		$this->update(['cost' => $newCost]);
    }
	
	public function images()
	{
		return $this->hasMany(ProductImage::class);
	}
}