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
    public array $form = [];
	public string $items_subtotal = '0.0000';
	public string $customer_total = '0.0000';
	public string $final_net_amount = '0.0000';

    protected function rules()
    {
        $saleId = $this->sale?->id ?? 'NULL';
        
        return [
            'form.customer_id'    => 'required|integer|exists:customers,id',
            'form.user_id'	      => 'required|integer|exists:users,id',
            'form.sold_at'        => 'required|date',
            'form.warehouse_id'   => 'required|integer|exists:warehouses,id',
            'form.invoice_number' => 'required|string|unique:sales,invoice_number,' . $saleId . ',id',
            'form.channel'        => 'required|integer|exists:shops,id',
            'form.payment_method' => 'required|string',
            'form.remark'  		  => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|integer|exists:products,id',
            'items.*.quantity'    => 'required|numeric|min:0.0001',
            'items.*.price'       => 'required|numeric|min:0',
            'items.*.warehouse_id'=> 'required|integer|exists:warehouses,id',
        ];
    }

    protected function messages()
    {
        return [
            'form.customer_id.required' => '請選擇客戶',
            'form.warehouse_id.required' => '請選擇業務歸屬倉庫',
            'form.invoice_number.required' => '單號不能為空，請重新整理頁面',
            'form.invoice_number.unique' => '單號已存在',
            'items.required' => '請至少添加一個商品',
            'items.*.product_id.required' => '請選擇商品',
        ];
    }

    public function mount(?Sale $sale = null)
    {
        if ($sale && $sale->exists) {
            $this->isEdit = true;
            $this->sale = $sale;
            $this->form = $sale->toArray();
            $this->form['sold_at'] = $sale->sold_at->format('Y-m-d');
            $feeConfigs = config('business.fee_types', []);
			foreach ($feeConfigs as $key => $config) {
				$this->form[$key] = $sale->$key ?? '0.00';
			}
			
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
            
            // 【修正】初始化時計算總計
            $this->calculateAll();
        } else {           
            $this->invoice_number = Sale::generateInvoiceNumber();
            
            $this->form = [                
                'customer_id'      => 1,
                'user_id'          => auth()->id() ?? 1,
                'sold_at'          => now()->format('Y-m-d'),
                'invoice_number'   => $this->invoice_number,
                'warehouse_id'     => Setting::get('default_warehouse_id', 1),
                'channel'          => auth()->user()->shop_id ?? 1,
                'payment_method'   => 'cash',
                'remark'     	   => '',
                'subtotal'         => '0.00',
                'customer_total'   => '0.00',
                'final_net_amount' => '0.00',
            ];

            foreach (config('business.fee_types') as $key => $config) {
                $this->form[$key] = '0.00';
            }
            
            $this->addRow();
        }
    }

    /**
     * 【關鍵修正】即時顯示驗證錯誤
     */
    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    /**
	 * 【修正】計算邏輯 - 確保所有數值都是字串型態
	 */
	public function calculateAll()
	{
		$subtotal = '0.0000';

        // 1. 商品明細計算
        foreach ($this->items as $index => $item) {
            $price = (string)($item['price'] ?? '0');
            $qty = (string)($item['quantity'] ?? '0');
            $lineTotal = bcmul($price, $qty, 4);
            
            $this->items[$index]['subtotal'] = $lineTotal;
            $subtotal = bcadd($subtotal, $lineTotal, 4);
        }

        $this->items_subtotal = $subtotal;
        
        // 【修正】同步更新 form.subtotal 供畫面顯示和資料庫儲存
        $this->form['subtotal'] = $subtotal;

        // 2. 費用加減項計算 (依據 config/business.php)
        $cTotal = $subtotal; // 買家實付起始值
        $sNet = $subtotal;   // 賣家進帳起始值
        $feeConfigs = config('business.fee_types', []);

        foreach ($feeConfigs as $key => $config) {
            $val = (string)($this->form[$key] ?? '0.0000');
            $op = $config['operator'];
            
            if ($config['target'] === 'customer') {
                $cTotal = ($op === 'add') ? bcadd($cTotal, $val, 4) : bcsub($cTotal, $val, 4);
            } elseif ($config['target'] === 'seller') {
                $sNet = ($op === 'add') ? bcadd($sNet, $val, 4) : bcsub($sNet, $val, 4);
            }
        }

        $this->customer_total = $cTotal;
        $this->final_net_amount = $sNet;
        
        // 【修正】同步更新 form 中的顯示值
        $this->form['customer_total'] = $cTotal;
        $this->form['final_net_amount'] = $sNet;
    }

    /**
     * 當 form 中的數值（例如費用項目）變動時，觸發重新計算
     */
	public function updatedForm($value, $key)
    {
        // 【修正】只針對費用相關欄位觸發計算，避免無限迴圈
        $feeKeys = array_keys(config('business.fee_types', []));
        if (in_array($key, $feeKeys) || $key === 'order_adjustment') {
            $this->calculateAll();
        }
    }

    public function updatedItems($value, $key)
    {
        if (str_ends_with($key, '.product_id')) {
            $parts = explode('.', $key);
            $index = $parts[0];

            if ($value) {
                $product = Product::find($value);
                if ($product) {
                    $this->items[$index]['name'] = $product->full_display_name;
                    $this->items[$index]['price'] = $product->price;					
                    
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
        if (str_contains($key, '.price') || str_contains($key, '.quantity')) {
            $this->calculateAll();
        }
    }

    public function addRow()
    {
        $this->items[] = [
            'product_id'   => null,            
            'warehouse_id' => $this->form['warehouse_id'] ?? 1,
            'quantity'     => 1,
            'price'        => 0,			
        ];
        $this->search('');
        // 【修正】新增行後重新計算
        $this->calculateAll();
    }
    
    public function removeRow($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->calculateAll();
    }
    
    /**
     * 【修正】解決 DB::transaction 閉包內的變數範圍問題
     */
    public function save()
    {
        $this->validate();

        // 取得系統設定：是否允許負庫存
        $allowNegative = \App\Models\Setting::get('allow_negative_stock', false);

        if (!$allowNegative) {
            foreach ($this->items as $item) {
                // 檢查該倉庫目前的庫存
                $currentStock = Inventory::where('product_id', $item['product_id'])
                    ->where('warehouse_id', $item['warehouse_id'] ?? $this->form['warehouse_id'])
                    ->value('quantity') ?? 0;

                // 使用 bccomp 進行高精度比較
                if (bccomp($currentStock, $item['quantity'], 4) === -1) {
                    $this->error("商品 [{$item['name']}] 庫存不足 (現有: " . (float)$currentStock . ")");
                    return;
                }
            }
        }
		
		$this->calculateAll();

        try {
            return DB::transaction(function () {
                // 【修正】加入 subtotal 到資料庫欄位
                $saleData = collect($this->form)->only([
                    'customer_id', 'user_id', 'sold_at', 'warehouse_id', 
                    'invoice_number', 'channel', 'payment_method', 'remark',
                    'subtotal' // ← 【新增】這是資料庫必需的欄位
                ])->toArray();

                // 數值嚴謹性：將計算結果賦值給資料庫對應欄位
                $saleData['customer_total'] = $this->customer_total;
                $saleData['final_net_amount']   = $this->final_net_amount;

                $newSale = Sale::create($saleData);

                // 儲存明細與處理庫存邏輯...
                
                $this->success('銷售單建立成功', redirectTo: route('sales.index'));
            });
        } catch (\Exception $e) {
            $this->error('儲存失敗：' . $e->getMessage());
        }
    }

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
                'id' => $s->id,
                'name' => $s->name,
            ]),
        ]);
    }
}