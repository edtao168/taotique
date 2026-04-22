<?php

namespace App\Livewire\Purchases;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Traits\HasBarcodeScanner;
use App\Traits\HasProductSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

class Create extends Component
{
    use HasBarcodeScanner, HasProductSearch, Toast;

    public ?Purchase $purchase = null;
    public bool $isEdit = false;
	
	public $supplier_id;
	public $warehouse_id = 1;
	public $purchase_number;
    public $purchased_at;
    public $currency = 'CNY';
    public $exchange_rate;
	public $shipping_fee = 0;	
	public $discount = 0;
	public $tax = 0;
	public $other_fees = 0;	
    public $remark;
    public array $items = [];
    public array $productOptions = [];

    /**
     * 
     */
	public function mount(?Purchase $purchase = null)
    {
        if ($purchase && $purchase->exists) {
            $this->isEdit = true;
            $this->purchase = $purchase;
            $this->supplier_id = $purchase->supplier_id;
			$this->warehouse_id = $purchase->warehouse_id;
			$this->purchased_at = $purchase->purchased_at->format('Y-m-d');
			$this->currency = $purchase->currency;
			$this->exchange_rate = $purchase->exchange_rate;

			// 【修正】費用數據載入：確保欄位對應正確且不歸零
			$this->shipping_fee = (string) ($purchase->shipping_fee ?? '0.0000');
			$this->discount = (string) ($purchase->discount ?? '0.0000');
			$this->tax = (string) ($purchase->tax ?? '0.0000');
			$this->other_fees = (string) ($purchase->other_fees ?? '0.0000');			
			$this->remark = $purchase->remark;
            
            $this->items = $purchase->items->map(fn($item) => [
                'product_id' => $item->product_id,
				'name' => $item->product?->full_display_name ?? '',
                'warehouse_id' => $item->warehouse_id,
                'quantity' => $item->quantity,
                'foreign_price' => $item->foreign_price,
            ])->toArray();
        } else {
            $this->purchase_number = Purchase::generatePurchaseNumber();
			$this->warehouse_id = Warehouse::first()?->id ?? 1;
			$this->purchased_at = now()->format('Y-m-d');	
			$this->updateExchangeRate();
            $this->addRow();
        }
    }
	
	/**
	 * 當商品選擇變動時同步名稱
	 */
	public function updatedItems($value, $key)
	{
		if (str_ends_with($key, '.product_id')) {
			$parts = explode('.', $key);
			$index = $parts[0];

			if ($value) {
				$product = \App\Models\Product::find($value);
				if ($product) {
					// 使用 full_display_name 包含規格/描述資訊
					$this->items[$index]['name'] = $product->full_display_name;
					$this->items[$index]['foreign_price'] = $product->last_purchase_price ?? 0;
				}
			}
		}
	}

	/**
	 * 監聽幣別切換
	 */
	public function updatedCurrency($value)
	{		
		$baseCurrency = Setting::get('base_currency', 'TWD');
		
		if ($value === $baseCurrency) {
			$this->exchange_rate = '1.0000';
			return;
		}
		
		$rates = Setting::get('currency_rates', []);
	
		if (isset($rates[$value])) {
            $this->exchange_rate = $rates[$value];
        } else {
            $this->updateExchangeRate();
        }
	}

	/**
	 * 從配置檔抓取基準匯率
	 */
	protected function updateExchangeRate()
	{
		$this->exchange_rate = config("business.currencies.{$this->currency}.default_rate", '1.0000');
	}

	/**
     * 增加空的一行
     */
	public function addRow()
    {
        $this->items[] = [
            'product_id' => null,
			'name' => '',
            'warehouse_id' => Warehouse::first()?->id ?? 1,
            'quantity' => 1,
            'foreign_price' => 0,
        ];
		$this->search('');
    }
	
	/**
	 * 計算最終應付總額 (外幣)
	 * 公式：(商品小計 + 運費) - 折扣
	 */
	public function calculateTotal(): string
	{
		$subtotal = array_reduce($this->items, function($carry, $item) {
			return bcadd($carry, bcmul($item['quantity'], $item['foreign_price'], 4), 4);
		}, '0.0000');

		$total = bcadd($subtotal, $this->shipping_fee, 4);
		return bcsub($total, $this->discount, 4);
	}

	/**
     * 定義校驗規則
     */
    protected function rules()
    {
        return [
            'supplier_id'   => 'required|exists:suppliers,id',
            'warehouse_id'  => 'required|exists:warehouses,id',
            'purchased_at'  => 'required|date',
            'currency'      => 'required|string|max:3',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'items'         => 'required|array|min:1',
            'items.*.product_id'    => 'required|exists:products,id',
            'items.*.quantity'      => 'required|numeric|min:0.0001',
            'items.*.foreign_price' => 'required|numeric|min:0',
        ];
    }

    /**
     * 自定義校驗錯誤訊息 (選配)
     */
    protected function validationAttributes()
    {
        return [
            'supplier_id' => '供應商',
            'warehouse_id' => '倉庫',
            'items' => '採購明細',
            'items.*.quantity' => '數量',
            'items.*.foreign_price' => '採購單價',
        ];
    }
	
	/**
     * 採購單儲存邏輯
     */	
	public function save()
	{
		$this->validate();

		try {
			DB::transaction(function () {
				// 1. 取得全域設定：是否自動入庫
				$autoStockIn = Setting::getBool('po_auto_stock_in', true);

				// 2. 準備主表資料 (包含各項費用)
				$purchaseData = [
					'supplier_id'   => $this->supplier_id,
					'warehouse_id'  => $this->warehouse_id,
					'user_id'       => auth()->id() ?? 1,
					'currency'      => $this->currency,
					'exchange_rate' => $this->exchange_rate,
					'shipping_fee'  => $this->shipping_fee,
					'tax'           => $this->tax,
					'other_fees'    => $this->other_fees,
					'discount'      => $this->discount,
					'purchased_at'  => $this->purchased_at,
					'remark'        => $this->remark,
					'subtotal'      => $this->subTotal, // 來自 Computed 屬性
					'total_amount'  => $this->totalAmount, // 來自 Computed 屬性
					'total_twd'     => $this->totalTwd, // 來自 Computed 屬性
				];

				if ($this->isEdit && $this->purchase) {
					// 編輯模式：檢查是否已過帳
					if ($this->purchase->stocked_in_at) {
						throw new \Exception("此單據已入庫過帳，不允許修改。請執行退貨或反過帳程序。");
					}
					
					$currentPurchase = Purchase::where('id', $this->purchase->id)->lockForUpdate()->first();
					$currentPurchase->update($purchaseData);
					$currentPurchase->items()->delete();
					$msg = "採購單 {$currentPurchase->purchase_number} 修改成功";
				} else {
					// 新增模式
					$purchaseData['purchase_number'] = $this->purchase_number;
					$currentPurchase = Purchase::create($purchaseData);
					$msg = "採購單 {$currentPurchase->purchase_number} 建立成功";
				}

				// 3. 處理明細與入庫
				if ($autoStockIn) {
					// 自動入庫：呼叫模型層的厚邏輯（處理 Inventory、WeightedAverageCost、stocked_in_at）
					$currentPurchase->processInbound($this->items);
					$msg .= "並完成入庫過帳";
				} else {
					// 僅存檔：純粹記錄明細 Snapshot
					foreach ($this->items as $item) {
						$currentPurchase->items()->create([
							'product_id'    => $item['product_id'],
							'warehouse_id'  => $item['warehouse_id'],
							'quantity'      => $item['quantity'],
							'foreign_price' => $item['foreign_price'],
							'cost_twd'      => bcmul($item['foreign_price'], $this->exchange_rate, 4),
							'subtotal_twd'  => bcmul(bcmul($item['foreign_price'], $this->exchange_rate, 4), $item['quantity'], 4),
						]);
					}
					$msg .= " (待入庫)";
				}

				$this->success($msg, redirectTo: route('purchases.index'));
			});
		} catch (\Exception $e) {
			$this->error('儲存失敗：' . $e->getMessage());
		}
	}
    
    /**
     * 
     */
	public function updated($property, $value)
    {
        if (str_contains($property, 'product_id') && $value) {
            $parts = explode('.', $property);
            $index = $parts[1];
            $this->fillProductData($index, $value, 'items');
        }
    }

	/**
     * 
     */
	public function removeRow($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /**
     * 實現掃描回調（必須實現抽象方法）
     */
    public function onBarcodeScanned(string $barcode, ?int $index = null): void
    {
        $product = $this->findProductByBarcode($barcode);
        
        if (!$product) {
            $this->error("找不到條碼為 {$barcode} 的商品");
            return;
        }

        // 如果有指定行索引，填入該行
        if ($index !== null && isset($this->items[$index])) {
            $this->items[$index]['product_id'] = $product->id;
            $this->items[$index]['name'] = $product->name;
            $this->items[$index]['foreign_price'] = $product->last_purchase_price ?? 0;
            $this->success("已選擇商品：{$product->name}");
            $this->productOptions = $this->search();
            return;
        }

        // 檢查是否已存在於 items 中（自動增加數量）
        foreach ($this->items as $i => $item) {
            if ($item['product_id'] == $product->id) {
                $this->items[$i]['quantity'] = bcadd($this->items[$i]['quantity'], '1', 4);
                $this->success("已增加 {$product->name} 的數量");
                return;
            }
        }

        // 新增一行
        $this->items[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'warehouse_id' => Warehouse::first()?->id ?? 1,
            'quantity' => 1,
            'foreign_price' => $product->last_purchase_price ?? 0,
        ];

        $this->success("已加入商品：{$product->name}");
    }
	
	/**
	 * 即時計算商品純小計 (原始幣別)
	 */
	#[Computed]
	public function subTotal(): string
	{
		return array_reduce($this->items, function($carry, $item) {
			$itemSum = bcmul($item['quantity'] ?? 0, $item['foreign_price'] ?? 0, 4);
			return bcadd($carry, $itemSum, 4);
		}, '0.0000');
	}

	/**
	 * 即時計算最終應付總額 (原始幣別)
	 */
	#[Computed]
	public function totalAmount(): string
	{
		// 公式：小計 + 運費 + 稅金 + 雜費 - 折扣
		$sum = bcadd($this->subTotal, $this->shipping_fee ?: 0, 4);
		$sum = bcadd($sum, $this->tax ?: 0, 4);
		$sum = bcadd($sum, $this->other_fees ?: 0, 4);		
		return bcsub($sum, $this->discount ?: 0, 4);
	}

	/**
	 * 即時計算本幣總額 (TWD)
	 */
	#[Computed]
	public function totalTwd(): string
	{
		// totalAmount * exchange_rate
		return bcmul($this->totalAmount, $this->exchange_rate ?: 0, 4);
	}
	
	/**
     * 
     */
	public function render()
    {
        return view('livewire.purchases.create', [
            'suppliers' => Supplier::all(),
            'warehouses' => Warehouse::all(),
        ]);
    }
}