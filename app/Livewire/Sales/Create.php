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

    public ?Sale $sale = null;
    public bool $isEdit = false;
    public array $items = [];
    public array $productOptions = [];
    public bool $showScanner = false;
    public string $invoice_number = '';
    
    // 將 form 初始化為空的，我們在 mount 中動態填充
    public array $form = [];

    /**
     * 驗證規則：採動態定義，確保 business.php 增加費用時不需改動此處
     */
    protected function rules()
    {
        $rules = [
            'form.customer_id'   => 'required|integer',
			'form.user_id'	     => 'required|integer',
            'form.sold_at'       => 'required|date',
            'form.warehouse_id'  => 'required|integer',
            'form.invoice_number'=> 'nullable|string',
            'form.channel'       => 'required|integer',
            'form.payment_method'=> 'required|string',
            'form.payment_note'  => 'nullable|string',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity'   => 'required|numeric|min:0.0001',
            'items.*.price'      => 'required|numeric',
        ];

        // 從 business.php 動態加入費用驗證
        foreach (config('business.fee_types') as $key => $config) {
            $rules["form.{$key}"] = 'nullable|numeric';
        }

        return $rules;
    }

    public function mount(?Sale $sale = null)
	{
		if ($sale && $sale->exists) {
			$this->isEdit = true;
			$this->sale = $sale;
			$this->form = $sale->toArray();
			$this->form['sold_at'] = $sale->sold_at->format('Y-m-d');
			$this->items = $sale->items->map(function ($item) {
				return [
					'product_id'   => $item->product_id,
					'warehouse_id' => $item->warehouse_id,
					'quantity'     => $item->quantity,
					'price'        => $item->price,
					'sku'          => $item->product->sku ?? '',
					'name'         => $item->product?->full_display_name ?? '',
				];
			})->toArray();
			
			// 【新增】從 sale_fees 載入費用到 form
			foreach ($sale->fees as $fee) {
				$this->form[$fee->fee_type] = $fee->amount;
			}
		} else {
			// 基礎欄位初始化（不變）
			$this->form = [                
				'customer_id'      => 1,
				'user_id'          => auth()->id() ?? 1,
				'sold_at'          => now()->format('Y-m-d'),
				'invoice_number'   => '',
				'warehouse_id'     => Setting::get('default_warehouse_id', 1),
				'channel'          => auth()->user()->shop_id ?? 1,
				'payment_method'   => 'cash',
				'payment_note'     => '',
				'subtotal'         => '0.00',
				'customer_total'   => '0.00',
				'final_net_amount' => '0.00',
			];

			// 動態初始化所有費用欄位為 0
			foreach (config('business.fee_types') as $key => $config) {
				$this->form[$key] = '0.00';
			}
			
			$this->invoice_number = Sale::generateInvoiceNumber();
			$this->addRow();
		}
	}

    /**
     * 計算所有金額 (強制使用 BC Math)
     */
    public function calculateAll()
    {
        $subtotal = '0.0000';
        foreach ($this->items as $item) {
            $lineTotal = bcmul($item['quantity'], $item['price'], 4);
            $subtotal = bcadd($subtotal, $lineTotal, 4);
        }
        $this->form['subtotal'] = $subtotal;

        // 初始化動態計算
        $customer_total = $subtotal;
        $final_net_amount = $subtotal;

        $feeConfigs = config('business.fee_types');

        foreach ($feeConfigs as $key => $config) {
            $val = (string)($this->form[$key] ?? '0');
            
            // 處理買家支付總額
            if ($config['target'] === 'customer') {
                $customer_total = ($config['operator'] === 'add') 
                    ? bcadd($customer_total, $val, 4) 
                    : bcsub($customer_total, $val, 4);
            }

            // 處理賣家最終淨利 (進帳 = 買家付的 - 賣家支出的費用)
            // 注意：若買家端有折扣(sub)，賣家收入也會減少，所以所有費用都要計算進來
            $final_net_amount = ($config['operator'] === 'add')
                ? bcadd($final_net_amount, $val, 4)
                : bcsub($final_net_amount, $val, 4);
        }

        $this->form['customer_total'] = $customer_total;
        $this->form['final_net_amount'] = $final_net_amount;
    }

	/**
	 * 當 items 陣列中的數據更新時觸發
	 * $value: 更新後的值, $key: 格式為 "index.field" (例如 "0.product_id")
	 */
	public function updatedItems($value, $key)
	{
		// 解析是哪個索引的 product_id 發生變化
		if (str_ends_with($key, '.product_id')) {
			$parts = explode('.', $key);
			$index = $parts[0];

			if ($value) {
				$product = Product::find($value);
				if ($product) {
					// 自動填入單價
					$this->items[$index]['name'] = $product->full_display_name;
					$this->items[$index]['price'] = $product->price;					
					
					// 若有商品名稱需求也可在此填入
					$this->productOptions = Product::whereIn('id', collect($this->items)->pluck('product_id')->filter())
                    ->get()
					->map(fn($p) => [
                        'id' => $p->id,
                        'name' => $p->full_display_name,
                    ])
                    ->toArray();
					
					$this->calculateAll();
				}
			}
		}
	}

    /**
     * 增加空的一行
     */
	public function addRow()
    {
		$this->items[] = [
			'product_id' => null,            
			'warehouse_id' => $this->form['warehouse_id'] ?? 1,
			'quantity' => 1,
			'price' => 0,			
		];
		$this->search('');
    }
	
	/**
	 * 刪除行
	 */
	public function removeRow($index)
	{
		unset($this->items[$index]);
		$this->items = array_values($this->items); // 重新索引，這對 Livewire 迴圈很重要
		$this->calculateAll();
	}
	
	/**
     * 
     */
	public function save()
    {
        // 1. 執行 rules() 中定義的動態驗證
        $this->validate();

        try {
            DB::transaction(function () {
                // 2. 呼叫 Model 層的嚴謹儲存邏輯 (包含庫存鎖定 lockForUpdate)
                if ($this->isEdit) {
                    $this->sale->updateWithCalculations($this->form, $this->items);
                    $msg = '訂單已更新';
                } else {
                    Sale::createWithCalculations($this->form, $this->items);
                    $msg = '新訂單已建立';
                }
                
                $this->success($msg, redirectTo: route('sales.index'));
            });
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * 掃碼器邏輯
     */
    public function onBarcodeScanned(string $barcode, ?int $index = null): void
    {
        $product = Product::where('barcode', $barcode)->first();
        if (!$product) {
            $this->error("找不到條碼: {$barcode}");
            return;
        }

        $this->items[] = [
            'product_id' => $product->id,
            'name'       => $product->name,
            'quantity'   => 1,
            'price'      => $product->price,
            'subtotal'   => $product->price,
        ];
        $this->calculateAll();
    }

    public function render()
    {
        return view('livewire.sales.create', [
            'customers' => Customer::all(),
            'warehouses' => Warehouse::where('is_active', true)->get(),
			'shops' => \App\Models\Shop::all()->map(fn($s) => [
				'id' => $s->id,  // 確保是字串
				'name' => $s->name,
			]),
        ]);
    }
}