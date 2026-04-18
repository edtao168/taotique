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
        } else {           
            $this->invoice_number = Sale::generateInvoiceNumber();
            
            $this->form = [                
                'customer_id'      => 1,
                'user_id'          => auth()->id() ?? 1,
                'sold_at'          => now()->format('Y-m-d'),
                'invoice_number'   => $this->invoice_number, // ← 使用生成的單號
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
        // 即時驗證，讓使用者馬上看到錯誤
        $this->validateOnly($propertyName);
    }

    /**
	 * 【修正】計算邏輯 - 確保所有數值都是字串型態
	 */
	public function calculateAll()
	{
		// 1. 基礎商品小計 (Raw Subtotal)
		$itemsSubtotal = '0.0000';
		foreach ($this->items as $item) {
			$subtotal = bcmul((string)$item['price'], (string)$item['quantity'], 4);
			$itemsSubtotal = bcadd($itemsSubtotal, $subtotal, 4);
		}
		$this->form['items_subtotal'] = $itemsSubtotal;

		// 2. 初始化兩個維度的總額
		$customerTotal = $itemsSubtotal; // 買家應付從商品小計開始加減
		$sellerNet = $itemsSubtotal;     // 賣家實收從商品小計開始加減

		// 3. 根據配置中的 target 獨立計算
		$feeTypes = config('business.fee_types', []);
		
		foreach ($feeTypes as $key => $config) {
			$amount = (string)($this->form[$key] ?? '0.0000');
			$op = $config['operator']; // 'add' 或 'sub'
			$target = $config['target']; // 'customer' 或 'seller'

			if ($target === 'customer') {
				// 影響買家支付金額 (如：運費加項、折扣減項)
				$customerTotal = ($op === 'add') 
					? bcadd($customerTotal, $amount, 4) 
					: bcsub($customerTotal, $amount, 4);
			} elseif ($target === 'seller') {
				// 影響賣家實收金額 (如：平台抽成減項、廣告費減項)
				$sellerNet = ($op === 'add') 
					? bcadd($sellerNet, $amount, 4) 
					: bcsub($sellerNet, $amount, 4);
			}
		}

		// 4. 存回 Form 供前端顯示與過帳
		$this->form['customer_total'] = $customerTotal; // 買家最後要付多少
		$this->form['final_net_amount'] = $sellerNet;  // 賣家最後入帳多少
	}

    public function updatedForm($value, $key)
    {
        $fees = array_keys(config('business.fee_types'));
        if (in_array($key, $fees) || $key === 'subtotal') {
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

        try {
            // 使用 DB::transaction 確保資料一致性
            DB::transaction(function () {
                // 明確使用 $this->isEdit 與 $this->sale，PHP 閉包會自動綁定 $this
                if ($this->isEdit && $this->sale) {
                    $this->sale->updateWithCalculations($this->form, $this->items);
                    $msg = '訂單已更新';
                } else {
                    Sale::createWithCalculations($this->form, $this->items);
                    $msg = '新訂單已建立';
                }
                
                \Log::info('銷售儲存成功', ['invoice' => $this->form['invoice_number']]);
                
                // 提示成功並跳轉
                $this->success($msg, redirectTo: route('sales.index'));
            });
        } catch (\Exception $e) {
            \Log::error('銷售過帳失敗', [
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            // 丟出明確錯誤訊息給前端
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