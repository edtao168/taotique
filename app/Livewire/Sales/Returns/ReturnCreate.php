<?php // app/Livewire/Sales/Returns/ReturnCreate.php

namespace App\Livewire\Sales\Returns;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnFee;
use App\Models\Product;
use App\Models\Warehouse;
use App\Traits\HasProductSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

class ReturnCreate extends Component
{
    use Toast, HasProductSearch;
	
	// 基礎屬性
    public Sale $sale; // 原銷貨單實例
    public $warehouse_id;
    public array $return_items = []; // 退貨明細
    public array $fees = [];         // 動態費用明細

    // 從設定檔讀取費用類型
    public array $feeTypes = [];

    /**
     * 掛載時載入原訂單資料
     */
    public function mount($sale)
    {
        // 載入費用類型設定
        $this->feeTypes = config('business.return_fee_types', []);
        
        // 處理傳入的參數（可能是 ID 或模型）
        if ($sale instanceof Sale) {
            $this->sale = $sale->load(['customer', 'items.product']);
        } else {
            // 關鍵：使用 with 載入 items 和 product 關聯
            $this->sale = Sale::with(['customer', 'items.product'])
                ->findOrFail($sale);
        }
        
        // 從第一筆商品取得倉庫 ID
        $firstItem = $this->sale->items->first();
        $this->warehouse_id = $firstItem ? $firstItem->warehouse_id : null;
        
        // 預設帶入一筆費用供填寫
        $this->addFee();
    }
	
	/**
     * 將商品加入退貨清單
     */
    public function addItemToReturn($productId)
    {
        // 從原銷售單中尋找該商品（使用 SaleItem）
        $saleItem = $this->sale->items->where('product_id', $productId)->first();
        
        if (!$saleItem) {
            $this->error('此商品不在原訂單中');
            return;
        }
        
        $product = $saleItem->product;
        $productName = $product->full_display_name ?? $product->name;
        
        // 獲取商品單價（嘗試多個可能的欄位）
        $unitPrice = $saleItem->unit_price ?? $saleItem->price ?? 0;
        
        if ($unitPrice <= 0) {
            $this->error("商品 {$productName} 的單價為 0，無法退貨");
            return;
        }
        
        // 檢查是否已在退貨清單中
        foreach ($this->return_items as &$item) {
            if ($item['product_id'] === $productId) {
                $newQty = $item['quantity'] + 1;
                if ($newQty > $saleItem->quantity) {
                    $this->error("退貨數量不可超過原購買數量 ({$saleItem->quantity})");
                    return;
                }
                $item['quantity'] = $newQty;
                $item['subtotal'] = $item['quantity'] * $item['unit_price'];
                $this->success("已增加 {$productName} 退貨數量至 {$newQty}");
                return;
            }
        }
        
        // 新增退貨商品
        $this->return_items[] = [
            'sale_item_id' => $saleItem->id,
            'product_id' => $productId,
            'product_name' => $productName,
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'subtotal' => $unitPrice,
        ];
        
        $this->success("已加入退貨商品: {$productName}");
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
     * 新增一筆空費用
     */
    public function addFee(): void
    {
        // 使用設定檔中第一筆費用類型作為預設
        $defaultFeeType = !empty($this->feeTypes) ? $this->feeTypes[0]['id'] : 'shipping_fee_customer';
        
        $this->fees[] = [
            'fee_type' => $defaultFeeType,
            'amount' => '0.0000',
            'note' => ''
        ];
    }

    /**
     * 移除指定索引的費用
     */
    public function removeFee(int $index): void
    {
        unset($this->fees[$index]);
        $this->fees = array_values($this->fees);
    }

    /**
     * 即時計算費用總和 (Computed Property)
     */
    #[Computed]
    public function itemsTotal(): string
    {
        return array_reduce($this->return_items, fn($carry, $item) => bcadd($carry, $item['subtotal'], 4), '0.0000');
    }

    #[Computed]
    public function feesTotal(): string
    {
        return array_reduce($this->fees, fn($carry, $item) => bcadd($carry, $item['amount'] ?: '0', 4), '0.0000');
    }

    #[Computed]
    public function netRefundTotal(): string
    {
        return bcsub($this->itemsTotal, $this->feesTotal, 4);
    }
		
	/**
	 * 計算所有金額
	 */
	public function calculateTotals()
	{
		// 1. 取得商品退款小計
		$this->form['items_total_amount'] = array_reduce($this->selectedItems, function ($carry, $item) {
			return bcadd($carry, (string)($item['subtotal'] ?? 0), 4);
		}, '0.0000');

		// 2. 彙整所有退貨費用 (fees 陣列)
		$this->form['fees_total_amount'] = array_reduce($this->fees, function ($carry, $fee) {
			return bcadd($carry, (string)($fee['amount'] ?? 0), 4);
		}, '0.0000');

		// 3. 計算最終退款金額 (商品總計 - 費用總計)
		$this->form['total_refund_amount'] = bcsub(
			$this->form['items_total_amount'], 
			$this->form['fees_total_amount'], 
			4
		);
	}
	
	/**
     * 儲存邏輯
     */
    public function save()
    {
        if (empty($this->return_items)) {
            $this->error('請至少加入一項退貨商品');
            return;
        }
        
        return DB::transaction(function () {
            // 1. 建立退回單主表
            $return = SalesReturn::create([
                'shop_id' => auth()->user()->shop_id,
                'sale_id' => $this->sale->id,
                'warehouse_id' => $this->warehouse_id,
                'return_no' => 'SR-' . now()->format('YmdHis'),
                'created_by' => auth()->id(),
                'status' => 'pending',
            ]);

            // 2. 寫入費用明細 (SalesReturnFee)
            foreach ($this->fees as $fee) {
                if ($fee['amount'] > 0) {
                    $return->fees()->create($fee);
                }
            }

            // 3. 寫入退貨商品 (SalesReturnItem)
            foreach ($this->return_items as $item) {
                $return->items()->create($item);
            }

            // 4. 強制執行 BCMath 匯總更新
            $return->updateTotals();

            $this->success('銷貨退回單已建立，請等待審核。');
            return redirect()->route('sales.returns.index');
        });
    }

    public function render()
    {
        return view('livewire.sales.returns.return-create', [
            'warehouses' => Warehouse::all()
        ]);
    }
}