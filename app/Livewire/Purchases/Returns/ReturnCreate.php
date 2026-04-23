<?php // app/Livewire/Purchases/ReturnCreate.php

namespace App\Livewire\Purchases\Returns;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Traits\HasProductSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;

class ReturnCreate extends Component
{
    use Toast, HasProductSearch;

    public Purchase $purchase;
    public $warehouse_id;
    public array $return_items = [];
	public $shipping_fee = 0;
    public $other_fees = 0;

    /**
     * 掛載時載入原採購單資料
     */
	public function mount($purchase)
	{
		// 處理傳入的參數（可能是 ID 或模型）
		if ($purchase instanceof Purchase) {
			$this->purchase = $purchase->load(['items.product', 'supplier']);
		} else {
			$this->return_no = Purchase::generatePurchaseReturnNumber();
			$this->purchase = Purchase::with(['items.product', 'supplier'])
				->findOrFail($purchase);
		}
		
		$firstItem = $this->purchase->items->first();
		$this->warehouse_id = $firstItem ? $firstItem->warehouse_id : null;		
	}

	/**
     * 監聽退貨數量變動，即時更新該列小計
     */
    public function updatedReturnItems($value, $key)
    {
        // 格式如 "0.quantity"
        if (str_ends_with($key, '.quantity')) {
            $index = explode('.', $key)[0];
            $item = $this->return_items[$index];
            
            // 重新計算該列小計：數量 * 原採購外幣單價
            $this->return_items[$index]['subtotal'] = bcmul(
                $item['quantity'] ?: 0, 
                $item['foreign_price'], 
                4
            );
        }
    }
	
    /**
     * 將商品加入退貨清單 (從採購單選取)
     */
	public function addItemToReturn($productId)
    {
        $purchaseItem = $this->purchase->items()->where('product_id', $productId)->first();
        
        if (!$purchaseItem) return;

        // 檢查是否已在清單中
        foreach ($this->return_items as $item) {
            if ($item['product_id'] == $productId) {
                $this->warning('商品已在退貨清單中');
                return;
            }
        }

        $this->return_items[] = [
            'product_id' => $productId,
			      'purchase_item_id' => $purchaseItem->id,
						'product_name' => $purchaseItem->product->full_display_name,
            'quantity' => $purchaseItem->quantity, // 預設帶入全退
            'foreign_price' => $purchaseItem->foreign_price,
						'unit_price' => $purchaseItem->unit_price, 
            'subtotal' => bcmul($purchaseItem->quantity, $purchaseItem->foreign_price, 4),
        ];
    }
	
    /**
     * 移除退貨清單中的項目
     */
    public function removeReturnItem($index)
    {
        if (isset($this->return_items[$index])) {
            $itemName = $this->return_items[$index]['product_name'];
            unset($this->return_items[$index]);
            $this->return_items = array_values($this->return_items);
            $this->success("已移除 {$itemName}");
        }
    }

	/**
     * 計算純商品總額 (原始幣別)
     */
    #[Computed]
    public function itemsTotalAmount(): string
    {
        return array_reduce($this->return_items, fn($carry, $item) => bcadd($carry, $item['subtotal'], 4), '0.0000');
    }

    /**
     * 計算最終退回總額 (商品總額 + 費用)
     */
    #[Computed]
    public function totalReturnAmount(): string
    {
        $total = $this->itemsTotalAmount;
        $total = bcadd($total, $this->shipping_fee ?: 0, 4);
        $total = bcadd($total, $this->other_fees ?: 0, 4);
        return $total;
    }

    /**
     * 儲存採購退回單
     */
    public function save()
	{
		if (empty($this->return_items)) {
			$this->error('請至少加入一項商品');
			return;
		}

		// 調用您在 Purchase Model 新增的專業方法
		$returnNo = PurchaseReturn::generatePurchaseReturnNumber();

		try {
			return DB::transaction(function () use ($returnNo) {
				$return = PurchaseReturn::create([
					'shop_id' => auth()->user()->shop_id ?? 1,
					'purchase_id' => $this->purchase->id,
					'warehouse_id' => $this->warehouse_id,
					'return_no' => $returnNo, // 使用剛剛生成的單號
					'exchange_rate' => $this->purchase->exchange_rate, 
					'items_total_amount' => $this->itemsTotalAmount,
					'fees_total_amount' => bcadd((string)($this->shipping_fee ?: 0), (string)($this->other_fees ?: 0), 4),
					'total_return_amount' => $this->totalReturnAmount,
					'created_by' => auth()->id(),
					'status' => 'pending',
				]);

				foreach ($this->return_items as $item) {
					$return->items()->create([
						'shop_id'      => auth()->user()->shop_id ?? 1,
						'purchase_item_id'   => $item['purchase_item_id'],
						'product_id' => $item['product_id'],
						'quantity' => $item['quantity'],
						'unit_price' => $item['foreign_price'],
						'subtotal' => $item['subtotal'],
					]);
				}

				$this->success('採購退回單已建立', redirectTo: route('purchases.index'));
			});
		} catch (\Exception $e) {
			$this->error('儲存失敗：' . $e->getMessage());
		}
	}
    
    public function render()
    {
        return view('livewire.purchases.returns.return-create', [
            'warehouses' => Warehouse::all()
        ]);
    }
}