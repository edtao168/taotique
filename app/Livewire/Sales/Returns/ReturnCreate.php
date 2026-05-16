<?php // app/Livewire/Sales/Returns/ReturnCreate.php

namespace App\Livewire\Sales\Returns;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnFee;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Traits\HasProductSearch;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

class ReturnCreate extends Component
{
    use Toast, HasProductSearch;
	
	// 基礎屬性
    public Sale $sale;
    public $warehouse_id;
    public array $return_items = [];
    public array $fees = [];
	public array $feeTypes = [];
    public ?string $return_reason = null;

    /**
     * 掛載時載入原訂單資料
     */
    public function mount(Sale $sale)
	{
		$this->sale = $sale->load('returns');
		// $this->sale = $sale->load(['returns', 'customer', 'items.product']);若找不到客戶時可試試
		// 檢查是否還能進行退貨（可依據業務需求決定是完全鎖定，還是檢查剩餘可退數量）
		if ($this->sale->status === 'completed' && $this->sale->hasReturnRecords()) {
			// 如果您的業務邏輯是「一單僅限一退」，則在此阻斷
			// $this->warning('此單據已有退貨紀錄');
		}
	
		// 載入費用類型設定
        $this->feeTypes = config('business.return_fee_types', []);
        
        // 初始化費用陣列，確保每個類型都有對應的 key
		foreach ($this->feeTypes as $key => $config) {
			$this->fees[$key] = [
				'fee_type' => $key,
				'amount'   => '0.0000',
				'note'     => $config['name']
			];
		}
		
		// 處理傳入的參數（可能是 ID 或模型）
        if ($sale instanceof Sale) {
            $this->sale = $sale->load(['customer', 'items.product']);
        } else {
            // 關鍵：使用 with 載入 items 和 product 關聯
            $this->sale = Sale::with(['customer', 'items.product'])
                ->findOrFail($sale);
        }
        
        // 從第一筆商品取得倉庫 ID
        $firstItem = $this->sale->items->first();
        $this->warehouse_id = $firstItem ? $firstItem->warehouse_id : null;
        
        // 預設帶入一筆費用供填寫
        // $this->addFee();
    }
	
	/**
     * 將商品加入退貨清單
     */
	public function addItemToReturn($saleItemId)
	{
		// 找出原單中的該筆項目
		$saleItem = SaleItem::with('product')->find($saleItemId);

		if (!$saleItem) return;

		// 檢查是否重複加入
		foreach ($this->return_items as $item) {
			if ($item['sale_item_id'] == $saleItemId) {
				$this->warning('該商品已在退貨清單中');
				return;
			}
		}
		
		// 準備數值 (嚴謹遵循系統 DECIMAL 16,4 規範)
		$unitPrice = (string)$saleItem->price; // 原銷售單價
		$qty = '1'; // 預設退貨數量
		$subtotal = bcmul($unitPrice, $qty, 4);

		// 手動建立一個純陣列結構，確保 Livewire 序列化不會遺失資料
		$this->return_items[] = [
			'sale_item_id' => $saleItem->id,
			'product_id'   => $saleItem->product_id,
			'name'         => $saleItem->product?->full_display_name ?? '未命名商品',
			'barcode'      => $saleItem->product->barcode,
			'unit_price'   => $saleItem->price,
			'quantity'     => 1,
			'max_qty'      => $saleItem->quantity,
			'subtotal'     => $subtotal,
		];
	}
    
    /**
     * 移除退貨清單中的項目
     */
    public function removeReturnItem($index)
    {
        if (isset($this->return_items[$index])) {
            $itemName = $this->return_items[$index]['name'];
            unset($this->return_items[$index]);
            $this->return_items = array_values($this->return_items);
            $this->success("已移除 {$itemName}");
        }
    }

    /**
     * 新增一筆空費用
     */
    public function addFee(): void
    {
        // 【修正】使用 array_key_first 取得關聯陣列的第一個 Key (例如 'shipping_fee')
		$firstKey = array_key_first($this->feeTypes);
		$defaultFeeType = $firstKey ?? 'shipping_fee';
		
		$this->fees[] = [
			'fee_type' => $defaultFeeType,
			'amount'   => '0.0000',
			'note'     => $this->feeTypes[$defaultFeeType]['name'] ?? ''
		];
    }

    /**
     * 移除指定索引的費用
     */
    public function removeFee(int $index): void
    {
        unset($this->fees[$index]);
        $this->fees = array_values($this->fees);
    }

    /**
	 * 商品小計 (即時計算)
	 */
	#[Computed]
	public function itemsTotal(): string
	{
		$total = '0.0000';
		foreach ($this->return_items as $item) {
			// 修正：使用正確的鍵名 unit_price 與 quantity
			$subtotal = bcmul((string)($item['unit_price'] ?? 0), (string)($item['quantity'] ?? 0), 4);
			$total = bcadd($total, $subtotal, 4);
		}
		return $total;
	}

	/**
	 * 費用小計 (即時計算)
	 */
	#[Computed]
	public function feesTotal(): string
	{
		$total = '0.0000';
		foreach ($this->fees as $fee) {
			$total = bcadd($total, (string)($fee['amount'] ?? 0), 4);
		}
		return $total;
	}

	/**
	 * 最終應退金額
	 */
	#[Computed]
	public function netRefundTotal(): string
	{
		return bcsub($this->itemsTotal, $this->feesTotal, 4);
	}

	/**
     * 儲存邏輯
     */
    public function save()
    {
        $this->validate([
			'fees.*.amount' => 'required|numeric',
		]);
        
        return DB::transaction(function () {			
			$prefix = Setting::get('sr_prefix', 'SR-');
			
            // 1. 建立退回單主表
            $returnData = [
				'shop_id'             => auth()->user()->shop_id ?? 1,
				'sale_id'             => $this->sale->id,
				'warehouse_id'        => $this->warehouse_id,
				'return_no'           => $prefix . now()->format('YmdHis'),
				
				// 【修正】：將計算屬性的值對應到正確的 SQL 欄位
				'items_total_amount'  => $this->itemsTotal, 
				'fees_total_amount'   => $this->feesTotal,
				'total_refund_amount' => $this->netRefundTotal, // SQL 欄位是 total_refund_amount
				
				'return_reason'              => $this->return_reason,
				'created_by'          => auth()->id(),
				'status'              => 'pending',
			];

			$return = SalesReturn::create($returnData);

			// 寫入費用明細
			foreach ($this->fees as $fee) {
				// 使用 bcmath 檢查金額是否大於 0
				if (bccomp((string)$fee['amount'], '0', 4) !== 0) {
					$return->fees()->create([
						'shop_id'  => auth()->user()->shop_id ?? 1,
						'fee_type' => $fee['fee_type'],
						'amount'   => $fee['amount'],
						'note'     => $fee['note'],
					]);
				}
			}

            // 3. 寫入退貨商品 (SalesReturnItem)
            foreach ($this->return_items as $item) {
                $return->items()->create([
                    'shop_id'      => auth()->user()->shop_id ?? 1,
                    'product_id'   => $item['product_id'],
                    'sale_item_id' => $item['sale_item_id'],
                    'quantity'     => $item['quantity'],
                    'unit_price'        => $item['unit_price'],
                    'subtotal'     => bcmul((string)$item['unit_price'], (string)$item['quantity'], 4),
                ]);
            }

            // 4. 強制執行 BCMath 匯總更新
            $return->updateTotals();

            $this->success('銷貨退回單已建立，請等待審核。');
            return redirect()->route('sales.returns.index');
        });
    }

    public function render()
    {
        return view('livewire.sales.returns.return-create', [
            'warehouses' => Warehouse::all()
        ]);
    }
}