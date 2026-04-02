<?php // app/Livewire/Sales/ReturnCreate.php

namespace App\Livewire\Sales\Returns;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnFee;
use App\Models\Product;
use App\Models\Warehouse;
use App\Traits\HasBarcodeScanner;
use App\Traits\HasProductSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

class ReturnCreate extends Component
{
    use Toast, HasBarcodeScanner, HasProductSearch;
	
	// 基礎屬性
    public Sale $sale; // 原銷貨單實例
    public $warehouse_id;
    public array $return_items = []; // 退貨明細
    public array $fees = [];         // 動態費用明細

    // 定義可選的費用類型 (可從 Config 或資料庫讀取)
    public array $feeTypes = [
        ['id' => 'shipping_fee_customer', 'name' => '買家支付運費'],
        ['id' => 'platform_fee', 'name' => '平台成交手續費'],
        ['id' => 'payment_processing', 'name' => '金流服務費'],
        ['id' => 'other_service_fee', 'name' => '其它服務費'],
    ];
	
	/**
     * 掛載時載入原訂單資料
     */
    public function mount($sale)
    {
        // 處理傳入的參數（可能是 ID 或模型）
        if ($sale instanceof Sale) {
            $this->sale = $sale->load(['customer', 'items.product']);
        } else {
            // 關鍵：使用 with 載入 items 和 product 關聯
            $this->sale = Sale::with(['customer', 'items.product'])
                ->findOrFail($sale);
        }
        
        // 預設帶入一筆「平台手續費」供填寫
        $this->addFee();
    }
	
	 /**
     * 將商品加入退貨清單
     */
    public function addProductToReturn($productId)
    {
        // 從原銷售單中尋找該商品（使用 SaleItem）
        $saleItem = $this->sale->items->where('product_id', $productId)->first();
        
        if (!$saleItem) {
            $this->error('此商品不在原訂單中');
            return;
        }
        
        $product = $saleItem->product;
        
        // 獲取商品單價（嘗試多個可能的欄位）
        $unitPrice = $saleItem->unit_price ?? $saleItem->price ?? 0;
        
        if ($unitPrice <= 0) {
            $this->error("商品 {$product->name} 的單價為 0，無法退貨");
            return;
        }
        
        // 檢查是否已在退貨清單中
        foreach ($this->return_items as $index => &$item) {
            if ($item['product_id'] === $productId) {
                $newQty = $item['quantity'] + 1;
                if ($newQty > $saleItem->quantity) {
                    $this->error("退貨數量不可超過原購買數量 ({$saleItem->quantity})");
                    return;
                }
                $item['quantity'] = $newQty;
                $item['subtotal'] = $item['quantity'] * $item['unit_price'];
                $this->success("已增加 {$product->name} 退貨數量至 {$newQty}");
                return;
            }
        }
        
        // 新增退貨商品
        $this->return_items[] = [
            'sale_item_id' => $saleItem->id,  // 記錄原銷售明細 ID
            'product_id' => $productId,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'subtotal' => $unitPrice,
        ];
        
        $this->success("已加入退貨商品: {$product->name}");
    }
	
	/**
     * 實作 Trait 要求的抽象方法 [核心修正]
     * 處理掃碼槍掃入條碼後的邏輯
     */
    public function onBarcodeScanned(string $barcode, ?int $index = null): void
{
		$product = Product::where('barcode', $barcode)->first();

		if (!$product) {
			$this->error("找不到條碼: {$barcode}");
			return;
		}

		// 從原銷售單中尋找該商品
		$saleItem = $this->sale->items->where('product_id', $product->id)->first();

		if (!$saleItem) {
			$this->warning("此商品不在原訂單中");
			return;
		}

		// 建立退貨明細格式
		$this->return_items[] = [
			'product_id' => $product->id,
			'product_name' => $product->name,
			'quantity' => 1,
			'unit_price' => $saleItem->unit_price,
			'subtotal' => $saleItem->unit_price, // 規範 1: 初次加入可直接賦值，後續計算需用 bcmul
		];

		$this->success("已加入: {$product->name}");
	}

    /**
     * 加入商品到退回陣列
     */
    private function addItemToReturn(Product $product, $saleItem): void
    {
        // 檢查是否已在清單中，若在則累加數量
        foreach ($this->return_items as &$item) {
            if ($item['product_id'] === $product->id) {
                // 數量檢查：不可超過原購買數量
                $newQty = bcadd($item['quantity'], '1', 4);
                if (bccomp($newQty, $saleItem->quantity, 4) === 1) {
                    $this->error("退貨數量不可超過原購買數量 ({$saleItem->quantity})");
                    return;
                }
                $item['quantity'] = $newQty;
                $item['subtotal'] = bcmul($item['quantity'], $item['unit_price'], 4);
                return;
            }
        }

        // 若不在清單中，則新增一列
        $this->return_items[] = [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sale_item_id' => $saleItem->id,
            'quantity' => '1.0000',
            'unit_price' => $saleItem->unit_price, // 以原銷售單價為準
            'subtotal' => $saleItem->unit_price,
            'is_restock' => true,
        ];
    }

    /**
     * 新增一筆空費用
     */
    public function addFee(): void
    {
        $this->fees[] = [
            'fee_type' => 'shipping_fee_customer',
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
        $this->fees = array_values($this->fees); // 重置索引確保 Blade 綁定正確
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
     * 儲存邏輯 (資深架構師範例)
     */
    public function save()
    {
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
                $return->fees()->create($fee);
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
        return view('livewire.sales.returns.return-create');
    }
}