<?php
// 檔案路徑：app/Livewire/Sales/Create.php

namespace App\Livewire\Sales;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Warehouse; 
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Traits\HasBarcodeScanner;
use App\Traits\HasProductSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class Create extends Component
{
    use HasBarcodeScanner, HasProductSearch, Toast;

	public Sale $sale;
	public array $items = [];
	public array $productOptions = [];
	public bool $showScanner = false;
	public string $invoice_number = '';
	public $defaultWarehouseId;
	
    public array $form = [
		'customer_id' => 1,
        'sold_at' => null,    
        'invoice_number' => '',
        'warehouse_id' => null,
        'payment_note' => '',
        'channel' => 'shopee',
        'payment_method' => 'shopee-',
        'subtotal' => '0.0000',
        'discount' => '0.0000',
        'shipping_fee_customer' => '0.0000',
        'platform_coupon' => '0.0000',    
        'shipping_fee_platform' => '0.0000',
        'platform_fee' => '0.0000',
        'order_adjustment' => '0.0000',
        'customer_total' => '0.0000',
        'final_net_amount' => '0.0000',
	];

    /**
     * 元件建構函式，負責初始化資料和狀態，且只執行一次
     */	
    public function mount(Sale $sale = null)
    {
        // 修正：初始化 $sale 模型，避免 exists 報錯
        $this->sale = new Sale();
        
        // 初始化預計單號與時間
        $this->invoice_number = Sale::generateInvoiceNumber();
        $this->form['customer_id'] = Customer::first()->id ?? null;
		$this->form['sold_at'] = now()->format('Y-m-d\TH:i');
        
        // 預設倉庫
        $this->defaultWarehouseId = Warehouse::where('is_active', true)->first()?->id ?? 1;        
        $this->form['warehouse_id'] = $this->defaultWarehouseId;
    }
    
    // 元件渲染器，負責將資料傳遞給視圖並在每次更新時重新執行。
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
        $this->items[] = [
            'product_id' => null,
            'warehouse_id' => $this->defaultWarehouseId,
            'quantity' => '1.0000',
            'price' => '0.0000'
        ];
    }

    /**
     * 監聽所有影響金額的屬性異動
     */
    public function updated($property)
    {
        if (str_contains($property, 'form') || $property === 'items') {
            $this->calculateAll();
        }
    }
    
	/**
	 * 計算所有金額 (買家支付與賣家淨收益)
	 */
	public function calculateAll()
    {
        // 1. 小計
        $this->form['subtotal'] = array_reduce($this->items, function ($carry, $item) {
            $line = bcmul((string)($item['price'] ?? 0), (string)($item['quantity'] ?? 0), 4);
            return bcadd($carry, $line, 4);
        }, '0.0000');

        $this->form['customer_total'] = $this->form['subtotal'];
        $this->form['final_net_amount'] = $this->form['subtotal'];

        // 2. 動態費用計算 (從 config 讀取)
        $feeConfigs = config('business.fee_types', []);
        foreach ($feeConfigs as $key => $config) {
            $val = (string)($this->form[$key] ?? '0');
            
            if ($config['target'] === 'customer') {
                $this->form['customer_total'] = ($config['operator'] === 'add') 
                    ? bcadd($this->form['customer_total'], $val, 4) 
                    : bcsub($this->form['customer_total'], $val, 4);
            }

            if ($config['target'] === 'seller') {
                $this->form['final_net_amount'] = ($config['operator'] === 'add') 
                    ? bcadd($this->form['final_net_amount'], $val, 4) 
                    : bcsub($this->form['final_net_amount'], $val, 4);
            }
        }
    }
	
    /**
     * 儲存邏輯
     */
    public function save()
    {
        $this->validate([
            'invoice_number'       => 'required',
            'customer_id'          => 'required',
            'sold_at'              => 'required|date',
            'items.*.product_id'   => 'required',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.quantity'     => 'required|numeric|min:0.0001',
            'items.*.price'        => 'required|numeric',
        ]);

        // 將當前組件的所有屬性打包，對應到 Sale 模型需要的欄位
        $data = [
            'customer_id'           => $this->form['customer_id'],  // 從 form 拿
			'invoice_number'        => $this->invoice_number,
			'channel'               => $this->form['channel'],  // 也要從 form 拿
			'warehouse_id'          => $this->form['warehouse_id'],
			'payment_method'        => $this->form['payment_method'],
			'subtotal'              => $this->form['subtotal'],
			'discount'              => $this->form['discount'],
			'platform_coupon'       => $this->form['platform_coupon'],
			'shipping_fee_customer' => $this->form['shipping_fee_customer'],
			'shipping_fee_platform' => $this->form['shipping_fee_platform'],
			'platform_fee'          => $this->form['platform_fee'],
			'order_adjustment'      => $this->form['order_adjustment'],
			'customer_total'        => $this->form['customer_total'],
			'final_net_amount'      => $this->form['final_net_amount'],
			'sold_at'               => $this->form['sold_at'],
			'payment_note'          => $this->form['payment_note'],
        ];

        try {
            if ($this->sale->exists) {
                // 更新邏輯
                $this->sale->updateWithCalculations($data, $this->items);
                $this->success('訂單已更新', redirectTo: route('sales.index'));
            } else {
                // 新增邏輯：就在這裡執行 Sale::createWithCalculations
                Sale::createWithCalculations($data, $this->items);
                $this->success('新訂單已建立', redirectTo: route('sales.index'));
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }	
	
    /**
     * 實現 Trait 要求的抽象方法
     * 這裡處理銷貨單專用的掃碼邏輯
     */
    public function onBarcodeScanned(string $barcode, ?int $index = null): void
    {
        $product = $this->findProductByBarcode($barcode);
        
        if (!$product) {
            $this->error("找不到條碼為 {$barcode} 的商品");
            return;
        }

        // 檢查是否已在清單，有的話數量 +1
        foreach ($this->items as $i => $item) {
            if ($item['product_id'] == $product->id) {
                $this->items[$i]['quantity'] = bcadd($this->items[$i]['quantity'], '1', 4);
                $this->calculateAll();
                $this->success("已增加 {$product->name} 數量");
                return;
            }
        }

        // 沒在清單則新增行
        $this->items[] = [
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse_id ?? 1,
            'quantity' => '1.0000',
            'price' => (string)$product->price,
        ];

        $this->calculateAll();
        $this->success("已加入商品：{$product->name}");
    }
}