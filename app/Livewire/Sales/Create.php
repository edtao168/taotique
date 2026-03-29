<?php
// 檔案路徑：app/Livewire/Sales/Create.php

namespace App\Livewire\Sales;

use App\Models\Customer;
use App\Models\Warehouse; 
use App\Models\Product;
use App\Models\Sale;
use App\Traits\HasProductSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class Create extends Component
{
    use HasProductSearch, Toast;

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
    public $subtotal = '0.00'; 
    public $discount = 0;
    public $platform_coupon = 0;
    public $shipping_fee_customer = 0;
    public $shipping_fee_platform = 0;
    public $platform_fee = 0;
    public $order_adjustment = 0;
    public $customer_total = '0.00';

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
     * 當選中商品後觸發 (覆寫 updated 鉤子)
     */
    public function updated($property, $value)
    {
        if (str_contains($property, 'product_id')) {
            $parts = explode('.', $property);
            $index = $parts[1];
            
            if ($value) {
                $this->fillProductData($index, $value, 'items');
                
                // --- 即時庫存檢查提醒 ---
                $product = Product::withSum('inventories as stock', 'quantity')->find($value);
                $currentStock = $product->stock ?? 0;
                
                if ($currentStock <= 0) {
                    $this->warning("警告：{$product->name} 目前無庫存！", position: 'toast-bottom toast-end');
                } elseif ($currentStock < 5) {
                    $this->info("注意：{$product->name} 庫存僅剩 {$currentStock} 件。", position: 'toast-bottom toast-end');
                }
            }
			
            // 選中後自動重新計算總額
            $this->calculateAll();
        }
		
		// 其他費用異動時重新計算總額
        if (in_array($property, ['discount', 'platform_coupon', 'shipping_fee_customer', 'platform_fee', 'order_adjustment'])) {
            $this->calculateAll();
        }
    }

    public function addRow()
    {
        $this->items[] = [
            'product_id' => null,
            'warehouse_id' => Warehouse::first()?->id ?? 1,
            'quantity' => 1,
            'price' => 0,
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
     * 核心計算邏輯：確保所有金額符合 BC Math 規範
     */
    public function calculateAll()
    {
        $newSubtotal = '0.0000';

        // 1. 計算商品明細總額
        foreach ($this->items as $index => $item) {
            $price = $item['price'] ?: '0';
            $qty = $item['quantity'] ?: '0';
            
            // 單項小計 = price * quantity
            $rowTotal = bcmul((string)$price, (string)$qty, 4);
            $this->items[$index]['subtotal'] = $rowTotal;
            
            // 累加至總額
            $newSubtotal = bcadd($newSubtotal, $rowTotal, 4);
        }

        $this->subtotal = $newSubtotal;

        // 2. 計算買家應付 (Customer Total)
        // 公式：銷售總額 + 買家付運費 - 賣場折扣 - 平台優惠券
        $customerTotal = bcadd($this->subtotal, (string)($this->shipping_fee_customer ?: 0), 4);
        $customerTotal = bcsub($customerTotal, (string)($this->discount ?: 0), 4);
        $customerTotal = bcsub($customerTotal, (string)($this->platform_coupon ?: 0), 4);
        
        $this->customer_total = $customerTotal;
    }
}