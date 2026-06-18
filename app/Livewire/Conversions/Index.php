<?php
// app/Livewire/Conversions/Index.php

namespace App\Livewire\Conversions;

use App\Enums\WorkflowStatus;
use App\Models\Conversion;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use WithPagination, Toast;

    public string $search = '';
    public array $sortBy = ['column' => 'process_date', 'direction' => 'desc'];
    
    public bool $showDrawer = false;
    public ?Conversion $selectedConversion = null;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function showDetails(int $id)
    {
        $this->selectedConversion = Conversion::with([
            'items.product', 
            'items.warehouse', 
            'user', 
            'warehouse', 
            'shop'
        ])->find($id);
        $this->showDrawer = true;
    }

    /**
     * 執行過帳（合併：出入庫 + 日記賬 + 狀態變更）
     * 比照採購退貨的設計
     */

	public function submitConversionPost($id)
	{
		try {
			DB::transaction(function () use ($id) {
				$conversion = Conversion::where('id', $id)->lockForUpdate()->firstOrFail();

				if ($conversion->isFinalized()) {
					throw new \Exception('已結案或已取消的單據不可執行過帳。');
				}

				// 直接呼叫 Model 的 post() 方法（參考採購退貨）
				$conversion->post();
			});

			$this->showDrawer = false;
			$this->selectedConversion = null;
			$this->resetPage();
			$this->success('拆裝過帳已完成！庫存與會計分錄已更新！');

		} catch (\Throwable $e) {
			$this->error('處理失敗：' . $e->getMessage());
		}
	}

    /**
     * 庫存異動處理
     */
    private function processInventoryMovement(Conversion $conversion)
    {
        foreach ($conversion->items as $item) {
            $quantity = $item->type == 1 
                ? -abs($item->quantity)  // 投入：減少
                : abs($item->quantity);   // 產出：增加

            $inventory = Inventory::where('product_id', $item->product_id)
                ->where('warehouse_id', $item->warehouse_id)
                ->first();

            if ($inventory) {
                $inventory->quantity += $quantity;
                $inventory->save();
            } elseif ($item->type == 2) {
                Inventory::create([
                    'shop_id' => $conversion->shop_id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'quantity' => $quantity,
                    'cost' => $item->cost_snapshot ?? 0,
                ]);
            }

            InventoryMovement::create([
                'shop_id' => $conversion->shop_id,
                'product_id' => $item->product_id,
                'warehouse_id' => $item->warehouse_id,
                'quantity' => $quantity,
                'cost_snapshot' => $item->cost_snapshot ?? 0,
                'type' => $item->type == 1 ? 'conversion_input' : 'conversion_output',
                'reference' => $conversion->conversion_no,
                'user_id' => auth()->id(),
            ]);
        }
    }

    public function delete(int $id)
    {
        try {
            $conversion = Conversion::findOrFail($id);
            
            if ($conversion->isFinalized()) {
                $this->error('已結案或已取消的單據不可刪除。');
                return;
            }
            
            $conversion->delete();
            $this->showDrawer = false;
            $this->selectedConversion = null;
            $this->resetPage();
            $this->success('紀錄已刪除');
            
        } catch (\Throwable $e) {
            $this->error('刪除失敗：' . $e->getMessage());
        }
    }

    public function render()
    {
        $headers = [
            ['key' => 'conversion_no', 'label' => '拆裝單號', 'class' => 'font-mono'],
            ['key' => 'process_date', 'label' => '作業日期', 'class' => 'w-32'],
            ['key' => 'user.name', 'label' => '操作員'],
            ['key' => 'items_count', 'label' => '品項數', 'class' => 'text-center'],
            ['key' => 'status', 'label' => '狀態', 'class' => 'text-center'],
            ['key' => 'remark', 'label' => '備註', 'sortable' => false],
        ];

        $conversions = Conversion::query()
            ->with(['user', 'warehouse', 'items'])
            ->when($this->search, function ($query) {
                $query->where('conversion_no', 'like', "%{$this->search}%")
                      ->orWhere('remark', 'like', "%{$this->search}%")
                      ->orWhereHas('warehouse', function($q) {
                          $q->where('name', 'like', "%{$this->search}%");
                      });
            })
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate(15);

        return view('livewire.conversions.index', [
            'conversions' => $conversions,
            'headers' => $headers
        ]);
    }
}