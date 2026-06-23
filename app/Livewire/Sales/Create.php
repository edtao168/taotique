<?php
// 檔案路徑：app/Livewire/Sales/Create.php

namespace App\Livewire\Sales;

use App\Models\Channel;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Warehouse; 
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\Shop;
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
    public bool $stockoutWhenSold = true;
    public array $items = [];
    public array $productOptions = [];
    public bool $showScanner = false;
    public string $invoice_number = '';
    
    // 強型別結構宣告：防止 Livewire 在 OCI 生產環境下 Hydrate 丟失變數鍵值
    public array $form = [
        'shop_id'          => 1, // 多店預留，初期預設為 1
        'customer_id'      => null,
        'user_id'          => null,
        'sold_at'          => null,
        'invoice_number'   => '',
        'warehouse_id'     => null,
        'channel_id'       => null,
        'payment_method'   => 'cash',
        'remark'           => '',
        'subtotal'         => '0.0000',
        'customer_total'   => '0.0000',
        'final_net_amount' => '0.0000',		
    ];

    public string $items_subtotal = '0.0000';
    public string $customer_total = '0.0000';
    public string $final_net_amount = '0.0000';
	
	public array $fees = [];

    public function mount(?Sale $sale = null)
    {
        $this->stockoutWhenSold = false;
        
        // 初始化動態費用欄位
        foreach (config('business.fee_types', []) as $key => $config) {
            $this->form[$key] = '0.0000';
        }

        if ($sale && $sale->exists) {
            $this->isEdit = true;
            $this->sale = $sale;
            
            // 修正：完全以 stocked_out_at 是否有值來當作「是否已出庫」的邏輯判斷參數
            $this->stockoutWhenSold = !is_null($sale->stocked_out_at);
            
            // 嚴謹封裝與型別強轉，確保對接 Mary UI 元件時不會因為型態錯誤而顯示空白或 null
            $this->form['id'] = $sale->id;
            $this->form['shop_id'] = (int) ($sale->shop_id ?? 1); 
            $this->form['customer_id'] = (int) $sale->customer_id;
            $this->form['user_id'] = (int) $sale->user_id;
            $this->form['sold_at'] = $sale->sold_at->format('Y-m-d\TH:i');
            $this->form['invoice_number'] = $sale->invoice_number;
            $this->form['warehouse_id'] = (int) $sale->warehouse_id;
            $this->form['channel_id'] = (int) $sale->channel_id;
            $this->form['payment_method'] = $sale->payment_method;
            $this->form['remark'] = $sale->remark ?? '';
            $this->form['subtotal'] = (string) $sale->subtotal;
            $this->form['customer_total'] = (string) $sale->customer_total;
            $this->form['final_net_amount'] = (string) $sale->final_net_amount;

            foreach (config('business.fee_types', []) as $key => $config) {
                $this->form[$key] = (string) ($sale->$key ?? '0.0000');
            }
            
            $this->items = $sale->items->map(function ($item) {
                return [
                    'product_id'   => (int) $item->product_id,
                    'warehouse_id' => (int) $item->warehouse_id, // 強轉 int 確保發貨倉庫 Select 可對接
                    'quantity'     => (string) $item->quantity,   // 數值嚴謹性：使用字串
                    'price'        => (string) $item->price,
                    'sku'          => $item->product->sku ?? '',
                    'name'         => $item->product?->full_display_name ?? '',
                    'subtotal'     => (string) $item->subtotal,
                ];
            })->toArray();
            
            $this->calculateAll();
        } else {           
            $this->invoice_number = Sale::generateInvoiceNumber();
            
            $this->form['shop_id'] = (int) (auth()->user()->shop_id ?? 1); 
            $this->form['customer_id'] = 1;
            $this->form['user_id'] = (int) (auth()->id() ?? 1);
            $this->form['sold_at'] = now()->format('Y-m-d\TH:i');
            $this->form['invoice_number'] = $this->invoice_number;
            $this->form['warehouse_id'] = (int) Setting::get('default_warehouse_id', 1);
            $this->form['channel_id'] = (int) (Channel::active()->first()?->id ?? 1);
            $this->form['payment_method'] = 'cash';
            $this->form['remark'] = '';
            
            $this->addRow();
        }
    }

    protected function rules()
    {
        $saleId = $this->sale?->id ?? 'NULL';
        
        return [
            'form.shop_id'         => 'required|integer|exists:shops,id',
            'form.customer_id'     => 'required|integer|exists:customers,id',
            'form.user_id'         => 'required|integer|exists:users,id',
            'form.sold_at'         => 'required|date',
            'form.warehouse_id'    => 'required|integer|exists:warehouses,id',
            'form.invoice_number'  => 'required|string|unique:sales,invoice_number,' . $saleId . ',id',
            'form.channel_id'      => 'required|integer|exists:channels,id',
            'form.payment_method'  => 'required|string',
            'form.remark'          => 'nullable|string',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|integer|exists:products,id',
            'items.*.quantity'     => 'required|numeric|min:0.0001',
            'items.*.price'        => 'required|numeric|min:0',
            'items.*.warehouse_id' => 'required|integer|exists:warehouses,id', // 嚴謹驗證明細發貨倉庫
        ];
    }

	/**
     * 自動生成如「客戶 是必填的」這樣的訊息。
     */
    public function validationAttributes()
    {
        return [
            'form.shop_id'        => '分店',
            'form.customer_id'    => '客戶',
            'form.sold_at'        => '成交時間',
            'form.warehouse_id'   => '業務歸屬倉庫',
            'form.invoice_number' => '銷售單號',
            'form.channel_id'     => '通路',
            'items'               => '商品明細',
            'items.*.product_id'  => '商品',
            'items.*.quantity'    => '數量',
            'items.*.price'       => '單價',
            'items.*.warehouse_id'=> '發貨倉庫',
        ];
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    public function calculateAll()
    {
        $subtotal = '0.0000';

        foreach ($this->items as $index => $item) {
            $price = (string)($item['price'] ?? '0');
            $qty = (string)($item['quantity'] ?? '0');
            $lineTotal = bcmul($price, $qty, 4); // 零售金額嚴謹計算
            
            $this->items[$index]['subtotal'] = $lineTotal;
            $subtotal = bcadd($subtotal, $lineTotal, 4);
        }

        $this->form['subtotal'] = $subtotal;
		$sellerDiscount = (string)($this->form['seller_discount'] ?? '0.0000');
		$adjustedSubtotal = bcsub($subtotal, $sellerDiscount, 4);
        $cTotal = $adjustedSubtotal; 
        $sNet = $adjustedSubtotal;   
        $feeConfigs = config('business.fee_types', []);

        foreach ($feeConfigs as $key => $config) {
            if ($key === 'seller_discount') continue;
			
			$val = (string)($this->form[$key] ?? '0.0000');
            $target = $config['target'] ?? '';
        
			// 根據 operator 決定加減
			$isAdd = ($config['operator'] === 'add');
			
			switch ($target) {
				case 'customer':
					$cTotal = $isAdd ? bcadd($cTotal, $val, 4) : bcsub($cTotal, $val, 4);
					break;
				case 'seller':
					$sNet = $isAdd ? bcadd($sNet, $val, 4) : bcsub($sNet, $val, 4);
					break;
				case 'both':
					// 同時影響買家實付和賣家實收
					$cTotal = $isAdd ? bcadd($cTotal, $val, 4) : bcsub($cTotal, $val, 4);
					$sNet = $isAdd ? bcadd($sNet, $val, 4) : bcsub($sNet, $val, 4);
					break;
				case 'revenue_adjustment':
                // 收入調整：只影響買家實付（因為是從收入中扣除）
                $cTotal = $isAdd ? bcadd($cTotal, $val, 4) : bcsub($cTotal, $val, 4);
                // 不影響賣家實收（因為這是收入抵減，不是費用）
                break;
			}
        }

        $this->form['customer_total'] = $cTotal;
        $this->form['final_net_amount'] = $sNet;
    }

    public function updatedForm($value, $key)
    {
        $feeKeys = array_keys(config('business.fee_types', []));
        if (in_array($key, $feeKeys) || $key === 'order_adjustment' || $key === 'warehouse_id') {
            // 當業務歸屬倉庫變動時，自動同步明細中未填寫的發貨倉庫
            if ($key === 'warehouse_id' && !empty($value)) {
                foreach ($this->items as $index => $item) {
                    if (empty($item['warehouse_id'])) {
                        $this->items[$index]['warehouse_id'] = (int) $value;
                    }
                }
            }
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
                    $this->items[$index]['price'] = (string) $product->price;					
                    
                    $this->productOptions = Product::whereIn('id', collect($this->items)->pluck('product_id')->filter())
                        ->get()
                        ->map(fn($p) => [
                            'id' => $p->id,
                            'name' => $p->full_display_name,
                        ])
                        ->toArray();
                }
            }
        }
        $this->calculateAll();
    }

    public function addRow()
    {
        $this->items[] = [
            'product_id'   => null,            
            'warehouse_id' => (int) ($this->form['warehouse_id'] ?? 1),
            'quantity'     => '1.0000',
            'price'        => '0.0000',			
        ];
        $this->search('');
        $this->calculateAll();
    }
    
    public function removeRow($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->calculateAll();
    }
    
    /**
     * 处理扫描到的条码
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
	
	public function updatedSubtotal()
	{
		$discount = $this->discount ?? '0';
		$this->customer_total = bcsub($this->subtotal, $discount, 2);
	}

    /**
     * 核心儲存邏輯 (涵蓋新增、修改、併發重試、負庫存控制與全自動會計過帳)
     */
	public function save()
	{
		$this->validate();

		try {
			$allowNegative = (bool) Setting::get('allow_negative_inventory', false);

			DB::transaction(function () use ($allowNegative) {
				
				// 1. 建立或更新主表
				$currentSale = $this->isEdit ? $this->sale : new Sale();
				$currentSale->fill([
					'shop_id'          => $this->form['shop_id'] ?? 1,
					'customer_id'      => $this->form['customer_id'],
					'user_id'          => auth()->id() ?? $this->form['user_id'],
					'sold_at'          => $this->form['sold_at'] ?? now(),
					'invoice_number'   => $this->isEdit ? $currentSale->invoice_number : Sale::generateInvoiceNumber(),
					'warehouse_id'     => $this->form['warehouse_id'],
					'channel_id'       => $this->form['channel_id'],
					'payment_method'   => $this->form['payment_method'],
					'remark'           => $this->form['remark'] ?? '',
					'subtotal'         => $this->form['subtotal'],
					'customer_total'   => $this->form['customer_total'],
					'final_net_amount' => $this->form['final_net_amount'],
				]);
				$currentSale->save();

				// 2. 擷取修改前的舊數量（若為修改模式）
				$oldItemsQty = [];
				if ($this->isEdit) {
					$oldItemsQty = SaleItem::where('sale_id', $currentSale->id)
						->pluck('quantity', 'product_id')
						->toArray();
					$currentSale->items()->delete();
				}

				// 3. 重新建立明細
				foreach ($this->items as $item) {
					$currentSale->items()->create([
						'shop_id'        => $currentSale->shop_id,
						'warehouse_id'   => $item['warehouse_id'],
						'product_id'     => $item['product_id'],
						'quantity'       => $item['quantity'],
						'price'          => $item['price'],
						'subtotal'       => bcmul($item['quantity'], $item['price'], 4),
					]);
				}

				// 4. 處理費用
				$currentSale->fees()->delete();
				$feeConfigs = config('business.fee_types', []);
				foreach ($feeConfigs as $feeType => $config) {
					$amount = $this->form[$feeType] ?? '0.0000';
					
					// 只儲存非零金額的費用
					if (bccomp($amount, '0', 4) !== 0) {
						$currentSale->fees()->create([
							'shop_id'  => $currentSale->shop_id,
							'fee_type' => $feeType,
							'amount'   => $amount,
							'note'     => $config['name'] ?? $feeType,
						]);
					}
				}
			});

			$this->success($this->isEdit ? '銷售單修改成功' : '銷售單建立成功', redirectTo: route('sales.index'));
			
		} catch (\Exception $e) {
			$this->error('儲存失敗：' . $e->getMessage());
		}
	}

    public function render()
    {
        return view('livewire.sales.create', [
            'customers' => Customer::all(),
            'warehouses' => Warehouse::where('is_active', true)->get(),
            'shops' => Shop::all()->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
            ]),
            'channels' => Channel::all(),
        ]);
    }
}