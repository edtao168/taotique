<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Conversion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_id', 
        'conversion_no', 
        'process_date', 
        'user_id', 
        'remark'
    ];

    /**
     * 關聯明細
     */
    public function items(): HasMany
    {
        return $this->hasMany(ConversionItem::class);
    }

    /**
     * 執行拆裝過帳核心邏輯
     * 包含：悲觀鎖定、庫存增減、流水帳錄入, 執行拆裝過帳並更新 WAC
     */
	public function post(): void
	{
		\DB::transaction(function () {
			foreach ($this->items as $item) {
				// 鎖定特定店、倉、產品的庫存
				$inventory = Inventory::where('store_id', $this->store_id)
					->where('warehouse_id', $item->warehouse_id)
					->where('product_id', $item->product_id)
					->lockForUpdate()
					->firstOrCreate([
						'store_id' => $this->store_id,
						'warehouse_id' => $item->warehouse_id,
						'product_id' => $item->product_id,
					]);

				if ($item->type === 1) { 
					// --- 投入/領料：僅扣減數量 ---
					$changeQty = bcmul($item->quantity, '-1', 4);
					$inventory->quantity = bcadd($inventory->quantity, $changeQty, 4);
				} else {
					// --- 產出/入庫：執行加權平均成本 (WAC) ---
					$newQty = $item->quantity;
					$newPrice = $item->cost_snapshot;

					// 使用您的 'cost' 欄位計算 WAC
					$currentValue = bcmul($inventory->quantity, $inventory->cost, 4);
					$addedValue = bcmul($newQty, $newPrice, 4);
					$totalQty = bcadd($inventory->quantity, $newQty, 4);
					
					if (bccomp($totalQty, '0', 4) > 0) {
						$totalValue = bcadd($currentValue, $addedValue, 4);
						$inventory->cost = bcdiv($totalValue, $totalQty, 4);
					}
					$inventory->quantity = $totalQty;
				}

				$inventory->save();

				// 寫入流水帳：對齊您的欄位 store_id 與 cost_snapshot
				InventoryMovement::create([
					'store_id' => $this->store_id,
					'product_id' => $item->product_id,
					'warehouse_id' => $item->warehouse_id,
					'quantity' => ($item->type === 1) ? bcmul($item->quantity, '-1', 4) : $item->quantity,
					'cost_snapshot' => ($item->type === 1) ? $inventory->cost : $item->cost_snapshot,
					'type' => 'CONVERSION',
					'reference' => $this->conversion_no,
					'user_id' => $this->user_id,
				]);
			}
		});
	}
}