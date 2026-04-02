<?php
// 檔案路徑：app/Livewire/Sales/Create.php

namespace App\Livewire\Sales;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Warehouse; 
use App\Models\Product;
use App\Models\Sale;
use App\Traits\HasBarcodeScanner;
use App\Traits\HasProductSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class Create extends Component
{
    use HasBarcodeScanner, HasProductSearch, Toast;

    public $customer_id = 1;
	public Sale $sale; 
    public $sold_at;    
    public $invoice_number;
    public array $items = []; // 強制轉為 array 確保與 Mary UI 表單兼容
	public array $productOptions = [];
	public bool $showScanner = false; // 手機掃碼開關
	public $warehouse_id = null;
    
    // 費用欄位
    public $channel = 'shopee';
    public $payment_method = 'shopee-';
    public $subtotal = '0.0000';
    public $discount = '0.0000';
    public $shipping_fee_customer = '0.0000';
    public $platform_coupon = 0;    
    public $shipping_fee_platform = 0;
    public $platform_fee = 0;
    public $order_adjustment = 0;
    public $customer_total = '0.0000';
	public $final_net_amount = '0.0000';

	/**
     * 元件建構函式，負責初始化資料和狀態，且只執行一次
     */	
	public function mount(Sale $sale = null)
	{
		$this->sale = ($sale && $sale->exists) ? $sale : new Sale();

		if ($this->sale->exists) {
			// 1. 填充單據基本資訊
			$this->invoice_number = $this->sale->invoice_number;
			$this->customer_id = $this->sale->customer_id;
			$this->sold_at = $this->sale->sold_at->format('Y-m-d');
			$this->channel = $this->sale->channel;
			$this->warehouse_id = $this->sale->warehouse_id; 
			$this->payment_method = $this->sale->payment_method;
			$this->platform_fee = $this->sale->platform_fee;
			$this->shipping_fee_platform = $this->sale->shipping_fee_platform;
			$this->order_adjustment = $this->sale->order_adjustment;
			$this->shipping_fee_customer = $this->sale->shipping_fee_customer;

			// 2. 關鍵：轉換明細為陣列，並確保數值為字串以利 bcmath 運算
			$this->items = $this->sale->items->map(fn($item) => [
				'product_id' => $item->product_id,
				'warehouse_id' => $item->warehouse_id,
				'price' => (string)$item->price,
				'quantity' => (string)$item->quantity,
			])->toArray();

			// 3. 預載商品下拉選單需要的「名稱」
			$productIds = collect($this->items)->pluck('product_id')->filter()->toArray();
			$this->productOptions = Product::whereIn('id', $productIds)
				->get()
				->map(fn($p) => [
					'id'   => $p->id,
					'name' => $p->full_display_name, // 這裡對應到 blade 的 option-label="name"
				])->toArray();

			// 4. 執行初次計算，確保 $final_net_amount 不是 0
			$this->calculateAll();
		} else {
			$this->sold_at = now()->format('Y-m-d');
			$defaultWarehouse = Warehouse::where('is_active', true)->first();
            $this->warehouse_id = $defaultWarehouse?->id;
			if (empty($this->items)) {
				$this->addRow();
			}
		}
	}
	
	//元件渲染器，負責將資料傳遞給視圖並在每次更新時重新執行。
	public function render()	
    {
        return view('livewire.sales.create', [
            'channels'   => \App\Models\Shop::getOptions(),
			'warehouses' => \App\Models\Warehouse::getOptions(),
			'customers'  => \App\Models\Customer::getOptions(),            
        ]);
    }	
	
    /**
     * 修正：手動新增行時，預設帶入第一個有效倉庫
     */
    public function addRow()
    {
        $defaultWarehouseId = Warehouse::where('is_active', true)->first()?->id ?? 1;
        
        $this->items[] = [
            'product_id' => null,
            'warehouse_id' => $defaultWarehouseId, // 確保畫面上能顯示選中的倉庫
            'quantity' => '1.0000',
            'price' => '0.0000'
        ];
    }

    /**
     * 修正：儲存邏輯
     * 考慮到您在 Index.php 寫了帶有 Warehouse 的交易邏輯，
     * 建議將核心扣庫存邏輯封裝在 Sale Model 的 createWithCalculations 中。
     */
    public function save()
    {
        $this->validate([
            'customer_id' => 'required',
            'sold_at'     => 'required|date',
            'items.*.product_id' => 'required',
            'items.*.warehouse_id' => 'required|exists:warehouses,id', // 驗證倉庫
            'items.*.quantity'   => 'required|numeric|min:0.0001',
            'items.*.price'      => 'required|numeric',
        ]);

        $allData = [
            'customer_id'           => $this->customer_id,
            'invoice_number'        => $this->invoice_number,
            'channel'               => $this->channel,
			'warehouse_id'          => $this->warehouse_id,
            'payment_method'        => $this->payment_method,
            'subtotal'              => $this->subtotal,
            'discount'              => $this->discount,
            'platform_coupon'       => $this->platform_coupon,
            'shipping_fee_customer' => $this->shipping_fee_customer,
            'shipping_fee_platform' => $this->shipping_fee_platform,
            'platform_fee'          => $this->platform_fee,
            'order_adjustment'      => $this->order_adjustment,
            'customer_total'        => $this->customer_total,
            'final_net_amount'      => $this->final_net_amount,
            'sold_at'               => $this->sold_at,
            //'shop_id'              => 1, // 多店預留
        ];

        try {
            // 建議將 DB::transaction 與 lockForUpdate 實作在 Model 層
            // 這裡呼叫 Model 方法
            if ($this->sale->exists) {
                $this->sale->updateWithCalculations($allData, $this->items);
                $this->success('訂單已更新', redirectTo: route('sales.index'));
            } else {
                Sale::createWithCalculations($allData, $this->items);
                $this->success('新訂單已建立', redirectTo: route('sales.index'));
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }	
	
	/**
     * 監聽所有影響金額的屬性異動
     */
    public function updated($property, $value)
    {
        // 商品與庫存邏輯
        if (str_contains($property, 'product_id') && $value) {
            $parts = explode('.', $property);
            $index = $parts[1];
            $product = Product::find($value);
            if ($product) {
                $this->items[$index]['price'] = (string)$product->price;
            }
        }

        // 觸發重新計算的欄位清單
        $calcFields = [
            'items', 'discount', 'platform_coupon', 'shipping_fee_customer', 
            'platform_fee', 'shipping_fee_platform', 'order_adjustment'
        ];

        if (collect($calcFields)->contains(fn($field) => str_contains($property, $field))) {
            $this->calculateAll();
        }
    }

    /**
     * 核心計算邏輯 (BC Math)
     */
    public function calculateAll()
    {
        // 1. 計算商品小計 (Subtotal)
        $newSubtotal = '0.0000';
        foreach ($this->items as $item) {
            $rowTotal = bcmul((string)($item['price'] ?? 0), (string)($item['quantity'] ?? 0), 4);
            $newSubtotal = bcadd($newSubtotal, $rowTotal, 4);
        }
        $this->subtotal = $newSubtotal;

        // 2. 計算買家實付 (Customer Total)
        // 公式：小計 + 買家運費 - 賣場折扣 - 平台優惠券
        $customerTotal = bcadd($this->subtotal, (string)($this->shipping_fee_customer ?: 0), 4);
        $customerTotal = bcsub($customerTotal, (string)($this->discount ?: 0), 4);
        $customerTotal = bcsub($customerTotal, (string)($this->platform_coupon ?: 0), 4);
        $this->customer_total = $customerTotal;

        // 3. 計算賣家實收 (Final Net Amount)
        // 公式：買家實付 - 平台手續費 - 平台代付運費 + 帳款調整
        $net = bcsub($this->customer_total, (string)($this->platform_fee ?: 0), 4);
        $net = bcsub($net, (string)($this->shipping_fee_platform ?: 0), 4);
        $net = bcadd($net, (string)($this->order_adjustment ?: 0), 4);
        $this->final_net_amount = $net;
    }
	
	public function removeRow($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->calculateAll();
    }
	
    public function handleScannedBarcode($barcode)
    {
        $product = Product::where('sku', $barcode)->first();
        if ($product) {
            $this->items[] = [
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => $product->price,
                'subtotal' => $product->price
            ];
            $this->success("已加入: {$product->name}");
        }
    }
	
	/**
     * 🔧 實現掃描回調
     * 零售邏輯：若商品已在清單中，則數量 +1；否則新增一行
     */
    public function onBarcodeScanned(string $barcode, ?int $index = null): void
    {
        $product = $this->findProductByBarcode($barcode);
        
        if (!$product) {
            $this->error("找不到條碼為 {$barcode} 的商品");
            return;
        }

        // 獲取當前庫存（考慮 shop_id 預設為 1）
        $currentStock = DB::table('inventories')
            ->where('product_id', $product->id)
            ->where('shop_id', 1)
            ->sum('quantity');

        // 檢查是否已存在於 items 中
        foreach ($this->items as $i => $item) {
            if ($item['product_id'] == $product->id) {
                $newQty = bcadd($this->items[$i]['quantity'], '1', 4);
                
                // 簡單庫存預警
                if (bccomp($newQty, (string)$currentStock, 4) > 0) {
                    $this->warning("{$product->name} 庫存不足（剩餘 {$currentStock}）");
                }

                $this->items[$i]['quantity'] = $newQty;
                $this->calculateAll();
                $this->success("已增加 {$product->name} 數量至 " . (int)$newQty);
                return;
            }
        }

        // 若不在清單中，則新增一行（或填入指定空行）
        $newRow = [
            'product_id' => $product->id,
            'warehouse_id' => Warehouse::first()?->id ?? 1,
            'quantity' => '1.0000',
            'price' => (string)($product->price ?? '0.0000'), // 自動帶入零售價
        ];

        if ($index !== null && empty($this->items[$index]['product_id'])) {
            $this->items[$index] = $newRow;
        } else {
            $this->items[] = $newRow;
        }

        $this->calculateAll();
        $this->success("已加入商品：{$product->name}");
    }
}