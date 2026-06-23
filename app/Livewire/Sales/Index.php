<?php
// 檔案路徑：app/Livewire/Sales/Index.php

namespace App\Livewire\Sales;

use App\Enums\WorkflowStatus;
use App\Models\Inventory;
use App\Models\Sale;
use App\Models\Setting;
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
    public $dateRange = 'month';

    public function mount()
    {
        $this->warehouses = Warehouse::where('is_active', true)
            ->orderBy('id', 'asc')
            ->get();

        $this->selectedWarehouse = $this->selectedWarehouse ?? null;

        if (empty($this->dateRange)) {
            $this->dateRange = 'month';
        }
    }

    public function updatedSelectedWarehouse($value)
    {
        $this->resetPage();
    }

    public function showDetail($id)
    {
        $this->selectedSale = Sale::with(['customer', 'items.product', 'user', 'shop', 'warehouse', 'fees'])->find($id);
        $this->drawer = true;
    }

    public function submitStockOut(int $saleId)
    {
        try {
            $sale = Sale::find($saleId);
            if (!$sale) {
                throw new \Exception("找不到該銷售單據。");
            }

            $allowNegative = (bool) Setting::get('allow_negative_inventory', false);
            $sale->processStockOut($allowNegative);

            $this->drawer = false;
            $this->selectedSale = null;

            $this->success("銷售單 {$sale->invoice_number} 已成功完成出庫、三份財務傳票自動驗證過帳！");
        } catch (\Exception $e) {
            $this->error('自動結轉失敗阻斷：' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $sale = Sale::with(['items', 'fees'])->lockForUpdate()->find($id);

                if (!$sale) {
                    throw new \Exception('找不到該單據，可能已被其他操作員刪除。');
                }

                if ($sale->hasReturnRecords()) {
                    throw new \Exception('此銷售單已有衍生退貨紀錄，禁止刪除。');
                }

                // ✅ 如果已出庫，禁止刪除
				if ($sale->stocked_out_at) {
					throw new \Exception('已出庫的銷售單禁止刪除。');
				}

				// ✅ 草稿或已審核但未出庫都可以刪除
				if (!in_array($sale->status, [WorkflowStatus::DRAFT, WorkflowStatus::APPROVED])) {
					throw new \Exception('此狀態無法刪除。');
				}

                $sale->fees()->delete();
                $sale->items()->delete();
                $sale->delete();
            });

            $this->selectedSale = null;
            $this->drawer = false;
            $this->success('銷售單已安全刪除，相關商品庫存已全數平滑回滾。');
        } catch (\Exception $e) {
            $this->error('刪除失敗：' . $e->getMessage());
        }
    }

    // =========================================================================
    // SECTION: 🆕 結算相關方法
    // =========================================================================

    /**
     * 銷售結算（approved → settled）
     */
    public function settleSale(int $saleId)
    {
        try {
            $sale = Sale::findOrFail($saleId);

            // ✅ 使用 Enum 的 canSettle() 方法
            if (!$sale->status->canSettle()) {
                throw new \Exception(
                    "目前狀態為「{$sale->status->label()}」，無法結算。僅「已審核」或「待結算」狀態可結算。"
                );
            }

            if ($sale->hasReturnRecords()) {
                throw new \Exception('此訂單已有退貨紀錄，無法結算。');
            }

            if (!$sale->stocked_out_at) {
                throw new \Exception('訂單尚未出貨，無法結算。');
            }

            if (!$sale->needsSettlement()) {
				throw new \Exception(
					"付款方式為「{$sale->payment_method_name}」，不需要結算。"
				);
			}
		
			$sale->settle(
                actor: auth()->user(),
                data: [
                    'amount' => $sale->customer_total,
                    'payment_method' => $sale->payment_method,
                    'settled_at' => now(),
                    'fee' => 0,
                ]
            );

            $this->selectedSale = $sale->fresh(['customer', 'items.product', 'user', 'shop', 'warehouse', 'fees']);
            $this->success("✅ 銷售單 {$sale->invoice_number} 已結算完成！");
        } catch (\Exception $e) {
            $this->error('結算失敗：' . $e->getMessage());
        }
    }

    // =========================================================================
    // SECTION: 🆕 計算屬性（供 Blade 判斷按鈕顯示）
    // =========================================================================

    public function getCanSettleProperty(): bool
    {
        if (!$this->selectedSale) {
            return false;
        }

        return $this->selectedSale->status->canSettle()
            && !$this->selectedSale->hasReturnRecords()
            && $this->selectedSale->stocked_out_at
			&& $this->selectedSale->needsSettlement();
    }

    public function getIsFinalizedProperty(): bool
    {
        if (!$this->selectedSale) {
            return true;
        }

        return $this->selectedSale->status->isFinalized()
            || $this->selectedSale->hasReturnRecords();
    }

    // =========================================================================
    // SECTION: render()
    // =========================================================================

    public function render()
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

        $monthSales = Sale::whereBetween('sold_at', [$startOfMonth, $endOfMonth])->sum('subtotal');
        $lastMonthSales = Sale::whereBetween('sold_at', [$startOfLastMonth, $endOfLastMonth])->sum('subtotal');
        $salesGrowth = $lastMonthSales > 0 ? (($monthSales - $lastMonthSales) / $lastMonthSales) * 100 : ($monthSales > 0 ? 100 : 0);
        $yearSales = Sale::whereYear('sold_at', date('Y'))->sum('subtotal');
        $monthProfit = Sale::whereBetween('sold_at', [$startOfMonth, $endOfMonth])->sum('final_net_amount');

        $sales = Sale::with(['customer', 'user', 'shop', 'channel', 'warehouse', 'fees'])
            ->when($this->search, function ($query) {
                $query->where('invoice_number', 'like', "%{$this->search}%")
                    ->orWhereHas('customer', fn($q) => $q->where('name', 'like', "%{$this->search}%"));
            })
            ->orderBy('sold_at', 'desc')
            ->paginate(10);

        $headers = [
            ['key' => 'invoice_number', 'label' => '銷售單號', 'class' => 'font-mono'],
            ['key' => 'status', 'label' => '狀態', 'class' => 'w-32'],
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
            'headers' => $headers,
            'canSettle' => $this->canSettle,
            'isFinalized' => $this->isFinalized,
        ]);
    }
}