<?php
// 檔案路徑：app/Livewire/Sales/Index.php

namespace App\Livewire\Sales;

use App\Models\AccountingRule;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Services\AccountingService;
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
		$this->selectedWarehouse = $this->selectedWarehouse ?? null;

		// 3. 初始化日期篩選範圍
		if (empty($this->dateRange)) {
			$this->dateRange = 'month'; 
		}

		// 4. 如果是從特定銷售單跳轉過來（選填）
		// 確保 selectedSale 結構完整以供 Drawer 渲染
		/* if ($this->selectedSale) {
			$this->selectedWarehouse = $this->selectedSale->warehouse_id;
		} */
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
        $this->selectedSale = Sale::with(['customer', 'items.product', 'user', 'shop', 'warehouse', 'fees'])->find($id);
        $this->drawer = true;
    }
	
	/**
     * 執行出庫扣減庫存，同步產生會計日記帳與主營成本結轉
     */
    public function submitStockOut(int $saleId)
    {        
        try {
            $sale = Sale::find($saleId);
            if (!$sale) {
                throw new \Exception("找不到該銷售單據");
            }

            // 1. 從系統設定讀取是否允許負庫存開關
            $allowNegative = (bool) Setting::get('allow_negative_inventory', false);
            
            // 2. 呼叫 Model 層的厚邏輯（列表頁直接出庫，代表非修改單據狀態，舊明細傳空陣列）
            $sale->processStockOut($allowNegative, []);
            
            // 3. 重置前端控制狀態並重新整理
            $this->drawer = false;
            $this->selectedSale = null;
            
            $this->success("銷售單 {$sale->invoice_number} 已成功完成出庫、日記帳自動過帳與成本結轉！");
        } catch (\Exception $e) {
            $this->error('出庫失敗：' . $e->getMessage());
        }
    }

    /**
	 * 刪除訂單（含庫存回滾與關聯清理）
	 */
	public function delete($id)
    {
        try {
            DB::transaction(function () use ($id) {
                // 💡 1. 行級鎖定當前欲刪除的單據
                $sale = Sale::with(['items', 'fees'])->lockForUpdate()->find($id);
                
                if (!$sale) {
                    throw new \Exception('找不到該單據，可能已被其他操作員刪除。');
                }
                
                if ($sale->hasReturnRecords()) {
                    throw new \Exception('此銷售單已有衍生退貨紀錄，禁止刪除。');
                }

                // 💡 2. 狀態機核心校驗：原本的狀態如果已經是 completed（已完成出庫/已記帳），絕對不允許直接刪除單據
                if ($sale->status === 'completed') {
                    throw new \Exception('該銷售單已出庫結案並生成財務傳票，若需調整請走「銷售退貨」流程，禁止直接刪除！');
                }

                // 💡 3. 回滾庫存（針對 pending 或 processing 狀態可能產生的預扣或佔用進行回滾）
                foreach ($sale->items as $item) {
                    $inventory = Inventory::where('product_id', $item->product_id)
                        ->where('warehouse_id', $sale->warehouse_id)
                        ->lockForUpdate()
                        ->first();

                    if ($inventory) {
                        // 嚴謹使用 bcadd 還原庫存
                        $newQty = bcadd($inventory->quantity, $item->quantity, 4);
                        $inventory->update(['quantity' => $newQty]);
                    }
                }

                // 💡 4. 清理級聯關聯數據，防範外鍵約束衝突
                $sale->fees()->delete();
                $sale->items()->delete();

                // 💡 5. 實體刪除（或軟刪除，依您的 Model 規劃而定）
                $sale->delete();
            });

            $this->selectedSale = null;
            $this->drawer = false;
            $this->success('銷售單已安全刪除，相關商品庫存已全數平滑回滾。');
            
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
        $sales = Sale::with(['customer', 'user', 'shop', 'channel','warehouse', 'fees'])
            ->when($this->search, function ($query) {
                $query->where('invoice_number', 'like', "%{$this->search}%")
                      ->orWhereHas('customer', fn($q) => $q->where('name', 'like', "%{$this->search}%"));
            })
            ->orderBy('sold_at', 'desc')
            ->paginate(10);

        $headers = [
            ['key' => 'invoice_number', 'label' => '銷售單號', 'class' => 'font-mono'],
            ['key' => 'shop.name', 'label' => '分店', 'class' => 'w-40'],
			['key' => 'channel.name', 'label' => '銷售通路'],
            ['key' => 'customer.name', 'label' => '客戶'],
            ['key' => 'customer_total', 'label' => '買家實付', 'textAlign' => 'text-right'],
			['key' => 'final_net_amount', 'label' => '最終進帳', 'textAlign' => 'text-right'],
            ['key' => 'sold_at', 'label' => '銷售日期', 'class' => 'w-32'],
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