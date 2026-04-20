<?php
// 檔案路徑：app/Livewire/Sales/Index.php

namespace App\Livewire\Sales;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use WithPagination, Toast;

    public string $search = '';
    public bool $drawer = false;
    public ?Sale $selectedSale = null;
	public $selectedWarehouse = null;
    public $warehouses = [];

    // 篩選條件
    public $dateRange = 'month'; 

    /**
	 * 元件初始化
	 */
	public function mount()
	{
		// 1. 載入所有啟用的倉庫供篩選器或彈窗使用
		$this->warehouses = Warehouse::where('is_active', true)
			->orderBy('id', 'asc')
			->get();

		// 2. 預設篩選邏輯：若無特定設定，預設選取第一個倉庫（或不限）
		// 根據您的業務需求，初期預設 store_id 為 1，此處亦可預設 warehouse_id
		$this->selectedWarehouse = $this->selectedWarehouse ?? null;

		// 3. 初始化日期篩選範圍
		if (empty($this->dateRange)) {
			$this->dateRange = 'month'; 
		}

		// 4. 如果是從特定銷售單跳轉過來（選填）
		// 確保 selectedSale 結構完整以供 Drawer 渲染
		if ($this->selectedSale) {
			$this->selectedWarehouse = $this->selectedSale->warehouse_id;
		}
	}
	
	/**
	 * 處理倉庫篩選異動
	 */
	public function updatedSelectedWarehouse($value)
	{
		$this->resetPage(); // 切換倉庫時重置分頁
	}
	
	/**
     * 觸發詳情抽屜
     */
    public function showDetail($id)
    {
        $this->selectedSale = Sale::with(['customer', 'items.product', 'user', 'shop', 'warehouse'])->find($id);
        $this->drawer = true;
    }

    /**
	 * 刪除訂單（含庫存回滾與關聯清理）
	 */
	public function delete($id)
	{
		$sale = Sale::with(['items', 'fees'])->find($id);
		
		if (!$sale) {
			$this->error('找不到該單據，可能已被刪除。');
			return;
		}
		
		// 1. 檢查是否已有退貨紀錄
		if ($sale->hasReturnRecords()) {
			$this->error('此銷售單已有退貨紀錄，禁止刪除。');
			return;
		}

		// 2. 檢查是否已結案
		if ($sale->status === 'completed') {
			$this->error('已結案單據不可刪除。');
			return;
		}

		try {
			DB::transaction(function () use ($sale) {
				// 3. 回滾庫存
				foreach ($sale->items as $item) {
					$inventory = Inventory::where('product_id', $item->product_id)
						->where('warehouse_id', $item->warehouse_id)
						->lockForUpdate()
						->first();

					if ($inventory) {
						$newQty = bcadd($inventory->quantity, $item->quantity, 4);
						$inventory->update(['quantity' => $newQty]);
					}
				}

				// 4. 【關鍵】先刪除關聯的費用明細
				$sale->fees()->delete();
				
				// 5. 刪除商品明細（如果沒有設定 cascade）
				$sale->items()->delete();

				// 6. 最後刪除主單
				$sale->delete();
			});

			$this->selectedSale = null;
			$this->drawer = false;
			$this->success('銷售單已刪除，庫存已回滾');
			
		} catch (\Exception $e) {
			$this->error('刪除失敗：' . $e->getMessage());
		}
	}
		
    public function render()
    {
        // --- 1. 統計數據邏輯 ---
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth   = Carbon::now()->endOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth   = Carbon::now()->subMonth()->endOfMonth();

        $monthSales = Sale::whereBetween('sold_at', [$startOfMonth, $endOfMonth])->sum('subtotal');
        $lastMonthSales = Sale::whereBetween('sold_at', [$startOfLastMonth, $endOfLastMonth])->sum('subtotal');
        $salesGrowth = $lastMonthSales > 0 ? (($monthSales - $lastMonthSales) / $lastMonthSales) * 100 : ($monthSales > 0 ? 100 : 0);
        $yearSales = Sale::whereYear('sold_at', date('Y'))->sum('subtotal');
        $monthProfit = Sale::whereBetween('sold_at', [$startOfMonth, $endOfMonth])->sum('final_net_amount');

        // --- 2. 銷售清單查詢 (合併原 SalesIndex 邏輯) ---
        $sales = Sale::with(['customer', 'user', 'shop', 'warehouse', 'fees'])
            ->when($this->search, function ($query) {
                $query->where('invoice_number', 'like', "%{$this->search}%")
                      ->orWhereHas('customer', fn($q) => $q->where('name', 'like', "%{$this->search}%"));
            })
            ->orderBy('sold_at', 'desc')
            ->paginate(10);

        $headers = [
            ['key' => 'invoice_number', 'label' => '訂單單號', 'class' => 'font-mono'],
            ['key' => 'shop.name', 'label' => '通路', 'class' => 'w-40'],
            ['key' => 'customer.name', 'label' => '客戶'],
            ['key' => 'customer_total', 'label' => '應收總額', 'textAlign' => 'text-right'],
			['key' => 'final_net_amount', 'label' => '最終進帳', 'textAlign' => 'text-right'],
            ['key' => 'sold_at', 'label' => '日期', 'class' => 'w-32'],
        ];

        return view('livewire.sales.index', [
            'monthSales' => $monthSales,
            'salesGrowth' => $salesGrowth,
            'yearSales' => $yearSales,
            'monthProfit' => $monthProfit,
            'sales' => $sales,
            'headers' => $headers
        ]);
    }
}