<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;



class Sale extends Model
{
    protected $guarded = [];
    protected $casts = [
        'sold_at' => 'datetime', // 確保日期格式正確
    ];

	public static function createWithCalculations(array $data, array $items)
	{
		return DB::transaction(function () use ($data, $items) {
			$data['invoice_number'] = $data['invoice_number'] ?: date('YmdHis') . substr(str_replace('.', '', microtime(true)), -3);
			$data['user_id'] = auth()->id();
			
			self::prepareFinancials($data);
			$sale = self::create($data);
			$sale->processItemsAndStock($items);			
			return $sale;
		});
	}

	public function updateWithCalculations(array $data, array $items)
	{
		return DB::transaction(function () use ($data, $items) {
			// 1. 取得舊明細 (以 product_id 為 key 方便比對)
			$oldItems = $this->items->keyBy('product_id');

			// 2. 更新主檔金額與資訊
			self::prepareFinancials($data);
			$this->update($data);

			// 3. 處理明細與庫存差額
			foreach ($items as $item) {
				$productId = $item['product_id'];
				$newQty = (float)$item['quantity'];
				$oldQty = $oldItems->has($productId) ? (float)$oldItems[$productId]->quantity : 0;

				// 計算差額 (新數量 - 舊數量)
				$diff = $newQty - $oldQty;

				if ($diff > 0) {
					// 數量增加了：只需要額外扣除「差額」部分
					$this->processStockReduction($productId, $diff);
				} elseif ($diff < 0) {
					// 數量減少了：還原多扣的庫存 (差額是負的，所以取絕對值)
					$this->restoreStock($productId, abs($diff));
				}
				// 如果 diff == 0，則完全不動庫存
			}

			// 4. 刪除舊明細並建立新明細 (或更新明細)
			$this->items()->delete();
			foreach ($items as $item) {
				$this->items()->create([
					'product_id' => $item['product_id'],
					'price'      => $item['price'],
					'quantity'   => $item['quantity'],
					'subtotal'   => bcmul((string)$item['quantity'], (string)$item['price'], 2),
				]);
			}

			return $this;
		});
	}

	/**
	 * 抽離出的 FIFO 扣庫存邏輯 (供新增與修改共用)
	 */
	protected function processItemsAndStock(array $items)
	{
		foreach ($items as $item) {
			if (empty($item['product_id'])) continue;

			$this->items()->create([
				'product_id' => $item['product_id'],
				'price'      => $item['price'],
				'quantity'   => $item['quantity'],
				'subtotal'   => bcmul((string)$item['quantity'], (string)$item['price'], 2),
			]);

			$needed = $item['quantity'];
			$stocks = Inventory::where('product_id', $item['product_id'])
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
			if ($needed > 0) throw new \Exception("庫存不足");
		}
	}	

	/**
     * 定義銷售單屬於哪位客戶
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * 定義銷售單擁有多個明細
     */
	public function items(): HasMany 
	{
		return $this->hasMany(SaleItem::class); 
	}
	
	/**
	 * 抽離出的金額計算邏輯
	 */
	private static function prepareFinancials(array &$data) {
		// 確保所有數值至少為 '0' 且轉為字串以符合 BCMath 要求
		$subtotal = (string)($data['subtotal'] ?? '0');
		$discount = (string)($data['discount'] ?? '0');
		$platCoupon = (string)($data['platform_coupon'] ?? '0');
		$shipCust = (string)($data['shipping_fee_customer'] ?? '0');
		
		$shipPlat = (string)($data['shipping_fee_platform'] ?? '0');
		$platFee  = (string)($data['platform_fee'] ?? '0');
		$adj      = (string)($data['order_adjustment'] ?? '0');

		// 1. 計算【買家支付總額】 (customer_total)
		// 公式：小計 + 買家運費 - 賣場折扣 - 平台優惠券
		$buyTotal = bcadd($subtotal, $shipCust, 2);
		$buyTotal = bcsub($buyTotal, $discount, 2);
		$data['customer_total'] = bcsub($buyTotal, $platCoupon, 2);

		// 2. 計算【賣家最終淨進帳】 (final_net_amount)
		// 公式：小計 - 平台代付運費 - 平台手續費 - 帳款調整
		// 注意：折扣由賣家負擔，所以是從 subtotal 開始扣除支出端
		$net = bcsub($subtotal, $shipPlat, 2);
		$net = bcsub($net, $platFee, 2);
		$data['final_net_amount'] = bcsub($net, $adj, 2);
	}
	
	/**
	 * 專門負責扣除庫存的邏輯 (FIFO)
	 */
	protected function processStockReduction($productId, $amount)
	{
		$needed = $amount;
		$stocks = Inventory::where('product_id', $productId)
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

	/**
	 * 專門負責還原庫存的邏輯
	 */
	protected function restoreStock($productId, $amount)
	{
		// 找到最後被扣除的紀錄還原，或簡單加回最新的一筆在庫批次
		$inv = Inventory::where('product_id', $productId)
			->where('status', 'sold')
			->orderBy('updated_at', 'desc')
			->first();
		
		if ($inv) {
			$inv->increment('quantity', $amount);
			$inv->update(['status' => 'in_stock']);
		}
	}
}