<?php // app/Livewire/Inventories/Stocktakes.php

namespace App\Livewire\Inventories;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class Stocktakes extends Component
{
    use Toast;

    // 狀態變數
    public ?int $stocktake_id = null; // 當前進行中的盤點單 ID
    public ?int $warehouse_id = null;
    public bool $confirmModal = false;
    public int $missing_count = 0;

    // 輸入變數
    public ?int $product_id = null;
    public $current_quantity = 0; // 系統顯示數量 (快照)
    public $actual_quantity = 0;  // 實際清點數量
    public string $remark = '';
    public array $products = [];

    /**
     * 初始化：檢查是否有尚未完成的盤點任務
     */
    public function mount()
    {
        $active = Stocktake::where('status', 'pending')->first();
        if ($active) {
            $this->stocktake_id = $active->id;
            $this->warehouse_id = $active->warehouse_id;
        }
        $this->search();
    }

    /**
     * 渲染頁面
     */
    public function render()
    {
        return view('livewire.inventories.stocktakes', [
            'warehouses' => Warehouse::with('shop')->get(),
            // 抓取目前盤點單的明細，按最後更新時間排序，方便員工看到剛才點了什麼
            'items' => $this->stocktake_id 
                ? StocktakeItem::where('stocktake_id', $this->stocktake_id)
                    ->with('product')
                    ->orderBy('updated_at', 'desc')
                    ->get()
                : []
        ]);
    }

    /**
     * 商品搜尋 (Mary UI x-choices 調用)
     */
    public function search(string $value = '')
    {
        $this->products = Product::query()
            ->where('sku', 'like', "{$value}%")
            ->orWhere('name', 'like', "%{$value}%")
            ->take(10)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'display_name' => "{$p->sku} - {$p->name}"
            ])->toArray();
    }

    /**
     * 啟動盤點任務：捕捉當下庫存快照
     */
    public function createStocktake()
    {
        $this->validate(['warehouse_id' => 'required']);

        DB::transaction(function () {
            // 1. 建立盤點主表
            $stocktake = Stocktake::create([
                'store_id' => 1, // 初期預設
                'warehouse_id' => $this->warehouse_id,
                'user_id' => auth()->id(),
                'status' => 'pending',
            ]);

            // 2. 捕捉快照：將該倉庫目前「所有產品」載入明細表
            // 這樣即便現場沒點到，結案時也能偵測出「漏盤」
            $inventories = Inventory::where('warehouse_id', $this->warehouse_id)->get();
            
            foreach ($inventories as $inv) {
                StocktakeItem::create([
                    'stocktake_id' => $stocktake->id,
                    'product_id' => $inv->product_id,
                    'system_quantity' => $inv->quantity,
                    'actual_quantity' => null, // 初始為 null，代表尚未點到
                    'cost_price' => $inv->cost_price ?? 0,
                ]);
            }

            $this->stocktake_id = $stocktake->id;
        });

        $this->success("盤點任務已啟動，已鎖定庫存快照。");
    }

    /**
     * 當選擇商品時，自動帶入該商品在盤點單中的快照資訊
     */
    public function updatedProductId($value)
    {
        if ($value && $this->stocktake_id) {
            $item = StocktakeItem::where('stocktake_id', $this->stocktake_id)
                ->where('product_id', $value)
                ->first();
            
            if ($item) {
                $this->current_quantity = $item->system_quantity;
                $this->actual_quantity = $item->actual_quantity ?? $item->system_quantity;
            }
        }
    }

    /**
     * 更新清點結果 (取代舊的 submit)
     */
    public function updateItem()
    {
        $this->validate([
            'product_id' => 'required',
            'actual_quantity' => 'required|numeric|min:0',
        ]);

        $item = StocktakeItem::where('stocktake_id', $this->stocktake_id)
            ->where('product_id', $this->product_id)
            ->first();

        if ($item) {
            $item->update([
                'actual_quantity' => (string)$this->actual_quantity,
            ]);
            
            $this->success("已更新清點數：{$item->product->name}");
            $this->reset(['product_id', 'actual_quantity', 'current_quantity']);
        } else {
            $this->error("此商品不在本次盤點範圍內。");
        }
    }

    /**
     * 結案流程：處理漏盤與正式過帳
     */
    public function showFinalizeConfirmation()
    {
        // 找出沒被點到的品項數量
        $this->missing_count = StocktakeItem::where('stocktake_id', $this->stocktake_id)
            ->whereNull('actual_quantity')
            ->count();
            
        $this->confirmModal = true;
    }

    public function finalize()
    {
        DB::transaction(function () {
            $stocktake = Stocktake::with('items')->lockForUpdate()->findOrFail($this->stocktake_id);

            foreach ($stocktake->items as $item) {
                // 處理漏盤：若為 null 代表沒點到，視為 0
                $isMissing = is_null($item->actual_quantity);
                $finalQty = $isMissing ? '0.0000' : (string)$item->actual_quantity;
                
                $diff = bcsub($finalQty, (string)$item->system_quantity, 4);

                if ($diff != 0) {
                    // 1. 更新實體庫存
                    Inventory::updateOrCreate(
                        ['warehouse_id' => $stocktake->warehouse_id, 'product_id' => $item->product_id],
                        ['quantity' => $finalQty, 'store_id' => $stocktake->store_id]
                    );

                    // 2. 紀錄異動流水
                    InventoryMovement::create([
                        'store_id' => $stocktake->store_id,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $stocktake->warehouse_id,
                        'quantity' => $diff,
                        'type' => $isMissing ? 'stocktake_loss' : 'stocktake_adj',
                        'user_id' => auth()->id(),
                        'remark' => $isMissing ? "漏盤自動歸零" : "盤點校正",
                    ]);
                }

                if ($isMissing) $item->update(['actual_quantity' => 0]);
            }

            $stocktake->update(['status' => 'completed', 'completed_at' => now()]);
        });

        $this->reset(['stocktake_id', 'warehouse_id', 'confirmModal']);
        $this->success("結案成功，庫存已同步。");
    }

    /**
     * 放棄盤點
     */
    public function cancelStocktake()
    {
        if ($this->stocktake_id) {
            Stocktake::find($this->stocktake_id)->delete();
            $this->reset(['stocktake_id', 'warehouse_id']);
            $this->warning("盤點任務已取消，未對庫存產生影響。");
        }
    }
}