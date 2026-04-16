<?php

namespace App\Livewire\Purchases;

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
			$this->shipping_fee = $purchase->shipping_fee;
            $this->discount = $purchase->discount;
			$this->tax = $purchase->tax_amount;
			$this->other_fees = $purchase->other_fees;
            $this->remark = $purchase->remark;
            
            $this->items = $purchase->items->map(fn($item) => [
                'product_id' => $item->product_id,
				'name' => $item->product->name,
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
     * 
     */
	public function save()
    {
        $this->validate([
            'supplier_id' => 'required',
            'purchased_at' => 'required|date',
            'items.*.product_id' => 'required',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        DB::transaction(function () {
			$totalForeign = $this->calculateTotal();	
			$totalTwd = bcmul($totalForeign, $this->exchange_rate, 4);
            if ($this->isEdit) {
                // 1. 修改模式：精確沖銷舊明細對應的庫存
                foreach ($this->purchase->items as $oldItem) {
                    Inventory::where('product_id', $oldItem->product_id)
                        ->where('warehouse_id', $oldItem->warehouse_id)
                        ->where('purchase_item_id', $oldItem->id)
                        ->delete();
                }
                $this->purchase->items()->delete();
                
                $this->purchase->update([
                    'supplier_id' => $this->supplier_id,
					'warehouse_id' => $this->warehouse_id,
                    'exchange_rate' => $this->exchange_rate,
                    'purchased_at' => $this->purchased_at,
                    'remark' => $this->remark,
                ]);
                $target = $this->purchase;
            } else {
                // 2. 新增模式
                $target = Purchase::create([                  
                    'purchase_number' => $this->purchase_number,
					'supplier_id' => $this->supplier_id,
					'warehouse_id' => $this->warehouse_id,
					'user_id' => auth()->id(),
					'currency' => $this->currency,
					'exchange_rate' => $this->exchange_rate,
					'total_amount' => $totalForeign,
					'total_twd' => $totalTwd,
					'shipping_fee' => $this->shipping_fee,
					'discount' => $this->discount,
					'purchased_at' => $this->purchased_at,
					'shop_id' => 1,
                ]);
            }

            // 3. 呼叫 Model 層的進貨處理程序 (處理加權平均成本與新庫存寫入)
            $target->processInbound($this->items);
        });

        $this->success($this->isEdit ? '採購單修改完成' : '採購入庫成功', redirectTo: route('purchases.index'));
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