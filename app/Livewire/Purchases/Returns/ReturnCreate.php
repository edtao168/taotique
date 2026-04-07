<?php // app/Livewire/Purchases/ReturnCreate.php

namespace App\Livewire\Purchases\Returns;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
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

    /**
     * 掛載時載入原採購單資料
     */
	public function mount($purchase)
	{
		// 處理傳入的參數（可能是 ID 或模型）
		if ($purchase instanceof Purchase) {
			$this->purchase = $purchase->load(['items.product', 'supplier']);
		} else {
			$this->purchase = Purchase::with(['items.product', 'supplier'])
				->findOrFail($purchase);
		}
		
		$firstItem = $this->purchase->items->first();
		$this->warehouse_id = $firstItem ? $firstItem->warehouse_id : null;
	}

    /**
     * 將商品加入退貨清單 (從採購單選取)
     */
	public function addItemToReturn($productId)
    {
        // 1. 驗證商品是否在原採購單中
        $pItem = $this->purchase->items->where('product_id', $productId)->first();
        
        if (!$pItem) {
            $this->error('此商品不在原採購訂單中');
            return;
        }
		
		$product = $pItem->product;
		$productName = $product->full_display_name ?? $product->name;
		$unitPrice = $pItem->unit_price;

        // 2. 檢查是否已在退貨清單中，若在則累加數量
        foreach ($this->return_items as &$item) {
			if ($item['product_id'] === $productId) {
				// 數量檢查：不可超過原採購數量
				$newQty = $item['quantity'] + 1;
				if ($newQty > $pItem->quantity) {
					$this->error("退貨數量不可超過原採購數量 ({$pItem->quantity})");
					return;
				}
				$item['quantity'] = $newQty;
				$item['subtotal'] = $item['quantity'] * $item['unit_price'];
				$this->success("已增加 {$productName} 退貨數量至 {$newQty}");
				return;
			}
		}

        // 3. 新增退貨項目 (採用原單快照單價)
        $this->return_items[] = [
            'purchase_item_id' => $pItem->id,
            'product_id' => $productId,
            'product_name' => $productName,
            'unit_price' => $unitPrice, 
            'quantity' => 1,
            'subtotal' => $unitPrice,
        ];

        $this->success("已加入退貨項目: {$productName}");
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

		
    // 計算退貨總額
	#[Computed]
    public function totalAmount(): string
    {
        return array_reduce($this->return_items, fn($carry, $item) => bcadd($carry, $item['subtotal'], 4), '0.0000');
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

        return DB::transaction(function () {
            $return = PurchaseReturn::create([
                'shop_id' => auth()->user()->shop_id ?? 1, // 修正為 shop_id
                'purchase_id' => $this->purchase->id,
                'warehouse_id' => $this->warehouse_id, // 來自 mount 時從明細抓取的 ID
                'return_no' => 'PR-' . now()->format('YmdHis'),
                'exchange_rate' => $this->purchase->exchange_rate, 
                'items_total_amount' => $this->totalAmount,
                'total_return_amount' => $this->totalAmount,
                'created_by' => auth()->id(),
                'status' => 'pending',
            ]);

            foreach ($this->return_items as $item) {
                $return->items()->create($item);
            }

            $this->success('採購退回單已建立。');
            return redirect()->route('purchases.returns.index');
        });
    }

    
    public function render()
    {
        return view('livewire.purchases.returns.return-create', [
            'warehouses' => Warehouse::all()
        ]);
    }
}