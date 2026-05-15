<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    /**
     * 開放 Mass Assignment
     * 考慮到您目前的開發需求，建議直接使用 $guarded = []
     * 這代表不限制寫入欄位，讓 Sale Model 可以自由更新 quantity 與 status
     */
    protected $guarded = [];
	
	/**
     * 轉型標註
     */    
	
	protected function casts(): array
	{
		return [
			'quantity' => 'decimal:4',
			'cost' => 'decimal:4',
		];
	}
	

	/**
     * 庫存屬於某個產品
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 庫存屬於某個倉庫
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
	
	public function adjustQuantity(float $amount, string $type, $reference = null, string $remark = null)
	{
		return DB::transaction(function () use ($amount, $type, $reference, $remark) {
			// 1. 更新當前庫存量
			$this->increment('quantity', $amount);

			// 2. 寫入流水帳 (厚 Model 邏輯)
			return StockMovement::create([
				'product_id' => $this->product_id,
				'warehouse_id' => $this->warehouse_id,
				'quantity' => $amount,
				'type' => $type,
				'reference_type' => $reference ? get_class($reference) : null,
				'reference_id' => $reference ? $reference->id : null,
				'user_id' => auth()->id(),
				'remark' => $remark,
			]);
		});
	}
}