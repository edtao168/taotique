<?php
// 檔案路徑：app/Livewire/Sales/Create.php

namespace App\Livewire\Sales;

use App\Models\Customer;
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

    public function mount(Sale $sale = null)
	//元件建構函式，負責初始化資料和狀態，且只執行一次
    {
        $this->sale = ($sale && $sale->exists) ? $sale : new Sale();
		$this->productOptions = $this->search();

        if ($this->sale->exists) {
            // --- 載入模式 ---
            $this->sale->load('items.product');
            $this->customer_id = $this->sale->customer_id;
            $this->invoice_number = $this->sale->invoice_number;
            $this->sold_at = $this->sale->sold_at->format('Y-m-d');
            $this->channel = $this->sale->channel;
            $this->payment_method = $this->sale->payment_method;
			$this->productOptions = $this->search();
            
            // 金額載入
            $this->subtotal = $this->sale->subtotal;
            $this->discount = $this->sale->discount;
            $this->platform_coupon = $this->sale->platform_coupon;
            $this->shipping_fee_customer = $this->sale->shipping_fee_customer;
            $this->shipping_fee_platform = $this->sale->shipping_fee_platform;
            $this->platform_fee = $this->sale->platform_fee;
            $this->order_adjustment = $this->sale->order_adjustment;
            $this->customer_total = $this->sale->customer_total;

            foreach ($this->sale->items as $item) {
                $this->items[] = [
                    'product_id' => $item->product_id,
                    'quantity' => (float)$item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                ];
            }
        } else {
            $this->sold_at = now()->format('Y-m-d');
            $this->invoice_number = 'S' . now()->format('YmdHis');
            $this->addRow();
			$this->productOptions = $this->search();
        }
    }
	
	public function render()
	//元件渲染器，負責將資料傳遞給視圖並在每次更新時重新執行。
    {
        return view('livewire.sales.create', [
            'customers' => Customer::all(),
			'warehouses' => Warehouse::all(),
            //'products' => Product::where('is_active', true)->get(),
        ]);
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
	
    public function addRow()
    {
        $this->items[] = [
            'product_id' => null,
            'warehouse_id' => Warehouse::first()?->id ?? 1,
            'quantity' => '1.0000',
			'price' => '0.0000'
        ];
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

    public function save()
    {
        // 此處完全沿用您原始代碼的 validate 與 try-catch 邏輯
        $this->validate([
            'customer_id' => 'required',
            'sold_at'     => 'required|date',
            'items.*.product_id' => 'required',
            'items.*.quantity'   => 'required|numeric|min:1',
            'items.*.price'      => 'required|numeric',
        ]);

        $allData = [
            'customer_id'           => $this->customer_id,
            'invoice_number'        => $this->invoice_number,
            'channel'               => $this->channel,
            'payment_method'        => $this->payment_method,
            'subtotal'              => $this->subtotal,
            'discount'              => $this->discount,
            'platform_coupon'       => $this->platform_coupon,
            'shipping_fee_customer' => $this->shipping_fee_customer,
            'shipping_fee_platform' => $this->shipping_fee_platform,
            'platform_fee'          => $this->platform_fee,
            'order_adjustment'      => $this->order_adjustment,
            'sold_at'               => $this->sold_at,
        ];

        try {
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

        // 獲取當前庫存（考慮 store_id 預設為 1）
        $currentStock = DB::table('inventories')
            ->where('product_id', $product->id)
            ->where('store_id', 1)
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