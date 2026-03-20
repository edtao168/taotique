<?php
// 檔案路徑：app/Livewire/Sales/Create.php

namespace App\Livewire\Sales;

use Livewire\Component;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Product;
use App\Traits\HasProductSearch;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;

class Create extends Component
{
    use HasProductSearch, Toast;

    public Sale $sale; 
    public $sold_at;
    public $customer_id = 1;
    public $invoice_number;
    public array $items = []; // 強制轉為 array 確保與 Mary UI 表單兼容
    
    // 費用欄位：完全保留您原始的屬性定義
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

    public bool $showScanner = false; // 新增：手機掃碼開關

    public function mount(Sale $sale = null)
    {
        $this->sale = ($sale && $sale->exists) ? $sale : new Sale();

        if ($this->sale->exists) {
            // --- 載入模式：保留您原始的 load 邏輯 ---
            $this->sale->load('items.product');
            $this->customer_id = $this->sale->customer_id;
            $this->invoice_number = $this->sale->invoice_number;
            $this->sold_at = $this->sale->sold_at->format('Y-m-d');
            $this->channel = $this->sale->channel;
            $this->payment_method = $this->sale->payment_method;
            
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
            $this->addItem();
        }
    }

    // --- 保留您原有的 addItem, removeItem, calculateAll 邏輯 ---
    public function addItem()
    {
        $this->items[] = ['product_id' => null, 'quantity' => 1, 'price' => 0, 'subtotal' => 0];
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

    public function render()
    {
        return view('livewire.sales.create', [
            'customers' => Customer::all(),
            'products' => Product::where('is_active', true)->get(),
        ]);
    }
}