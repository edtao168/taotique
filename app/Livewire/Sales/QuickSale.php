<?php

namespace App\Livewire\Sales;

use Livewire\Component;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Product;
use App\Traits\HasProductSearch;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;

class QuickSale extends Component
{
    use HasProductSearch, Toast;

    // 關鍵：宣告 Sale 物件，解決 PropertyNotFoundException
    public Sale $sale; 

    // 表單屬性
    public $sold_at;
    public $customer_id = 1;
    public $invoice_number;
    public $items = []; 
    
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
    {
        // 1. 初始化 Sale 物件
        $this->sale = ($sale && $sale->exists) ? $sale : new Sale();

        if ($this->sale->exists) {
            // --- 修改模式：載入現有資料 ---
            $this->sale->load('items.product');
            $this->customer_id = $this->sale->customer_id;
            $this->invoice_number = $this->sale->invoice_number;
            $this->channel = $this->sale->channel;
            $this->payment_method = $this->sale->payment_method;
            $this->sold_at = $this->sale->sold_at->format('Y-m-d');
            
            $this->discount = $this->sale->discount;
            $this->platform_coupon = $this->sale->platform_coupon;
            $this->shipping_fee_customer = $this->sale->shipping_fee_customer;
            $this->shipping_fee_platform = $this->sale->shipping_fee_platform;
            $this->platform_fee = $this->sale->platform_fee;
            $this->order_adjustment = $this->sale->order_adjustment;

            $this->items = $this->sale->items->map(fn($item) => [
                'product_id' => $item->product_id,
                'quantity'   => (float)$item->quantity,
                'price'      => (float)$item->price,
            ])->toArray();

            // 確保搜尋清單包含已選商品
            $productIds = collect($this->items)->pluck('product_id')->filter();
            $this->products = Product::whereIn('id', $productIds)->get()->map(fn($p) => [
                'id' => $p->id,
                'name' => "{$p->sku}：{$p->name}",
                'sku' => $p->sku
            ]);
        } else {
            // --- 新增模式：初始化 ---
            $this->sold_at = date('Y-m-d');
            $this->items = [['product_id' => '', 'quantity' => 1, 'price' => 0]];
            $this->search();
        }
        
        $this->calculateTotal();
    }

    public function addItem()
    {
        $this->items[] = ['product_id' => '', 'quantity' => 1, 'price' => 0];
    }

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->calculateTotal();
    }

    // 當 items.0.product_id 改變時自動觸發
    public function updated($property, $value)
    {
        if (str_contains($property, 'product_id')) {
            $index = explode('.', $property)[1];
            $this->fillProductData($index, $value, 'items');
        }
    }

    public function calculateTotal()
    {
        $this->subtotal = collect($this->items)->reduce(function ($carry, $item) {
            return bcadd($carry, bcmul((string)($item['quantity'] ?? 0), (string)($item['price'] ?? 0), 2), 2);
        }, '0.00');

        $step1 = bcadd($this->subtotal, (string)$this->shipping_fee_customer, 2);
        $step2 = bcsub($step1, (string)$this->discount, 2);
        $this->customer_total = bcsub($step2, (string)$this->platform_coupon, 2);
    }

    public function save()
    {
        $this->validate([
            'customer_id'    => 'required',
            'sold_at'        => 'required|date',
            'channel'        => 'required',
            'payment_method' => 'required',
            'items'                 => 'required|array|min:1',
			'items.*.product_id'    => 'required',
			'items.*.quantity'      => 'required|numeric|min:1',
			'items.*.price'         => 'required|numeric',
        ]);

        // 包含所有金額欄位
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
            // 判斷新增或更新
            if ($this->sale->exists) {
                $this->sale->updateWithCalculations($allData, $this->items);
                $this->success('訂單已更新');
            } else {
                Sale::createWithCalculations($allData, $this->items);
                $this->success('新訂單已建立');
            }
            return redirect()->route('sales.index');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
    
	public function render()
    {
        return view('livewire.sales.quick-sale', [
            // 這裡抓取所有客戶資料，傳給 Blade 裡的 :options="$customers"
            'customers' => Customer::all(),
        ]);
    }
	
}