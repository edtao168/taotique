<?php // app/Livewire/Inventories/Stocktakes.php

namespace App\Livewire\Inventories;

use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Models\Warehouse;
use App\Traits\HasBarcodeScanner;
use App\Traits\HasSingleProductPicker;
use App\Traits\HasShop;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class Stocktakes extends Component
{
    use HasBarcodeScanner, HasSingleProductPicker, HasShop, Toast;

    public array $headers = [
        ['key' => 'product.sku', 'label' => 'SKU'],
        ['key' => 'product.name', 'label' => '品名'],
        ['key' => 'system_quantity', 'label' => '帳面數量'],
        ['key' => 'actual_quantity', 'label' => '實點數量'],
    ];

    public ?int $stocktake_id = null;
    public ?int $warehouse_id = null;
    public bool $confirmModal = false;
    public int $missing_count = 0;
    public ?int $product_id = null;
    public ?string $product_name = null;
    public $current_quantity = 0;
    public $actual_quantity = 0;
    public string $remark = '';
    public bool $showScanner = false;
    public ?Stocktake $currentStocktake = null;

    public function mount()
    {
        $this->currentStocktake = Stocktake::with('warehouse')
            ->where('shop_id', $this->shopId)
            ->where('status', 'pending')
            ->first();

        if ($this->currentStocktake) {
            $this->stocktake_id = $this->currentStocktake->id;
            $this->warehouse_id = $this->currentStocktake->warehouse_id;
        }
    }

    public function onBarcodeScanned(string $barcode, ?int $index = null): void
    {
        $product = Product::where('sku', $barcode)->first();
        if (!$product) {
            $this->error("找不到條碼: {$barcode}");
            return;
        }

        if (!$this->stocktake_id) {
            $this->error("請先啟動盤點任務");
            return;
        }

        $this->product_id = $product->id;
        $this->updatedProductId($product->id);
        $this->success("已掃描: {$product->name}");
    }

    public function createStocktake()
    {
        $this->validate(['warehouse_id' => 'required']);

        DB::transaction(function () {
            $stocktake = Stocktake::create([
                'shop_id'      => $this->shopId,
                'warehouse_id' => $this->warehouse_id,
                'user_id'      => auth()->id(),
                'status'       => 'pending',
            ]);

            $inventories = Inventory::where('warehouse_id', $this->warehouse_id)
                ->lockForUpdate()
                ->get();

            foreach ($inventories as $inv) {
                StocktakeItem::create([
                    'stocktake_id'    => $stocktake->id,
                    'product_id'      => $inv->product_id,
                    'system_quantity' => $inv->quantity,
                    'actual_quantity' => null,
                    'cost_price'      => (string) ($inv->cost ?? '0.0000'),
                ]);
            }

            $this->stocktake_id = $stocktake->id;
        });

        $this->success("盤點任務已啟動，已鎖定庫存快照。");
    }

    public function updatedProductId($value)
    {
        if ($value && $this->stocktake_id) {
            $item = StocktakeItem::where('stocktake_id', $this->stocktake_id)
                ->where('product_id', $value)
                ->with('product')
                ->first();

            if ($item) {
                if (!$item->product) {
                    $this->warning("此商品資料已不存在");
                    $this->product_id = null;
                    $this->product_name = null;
                    return;
                }
                $this->product_name = $item->product->full_display_name;
                $this->actual_quantity = $item->actual_quantity ?? $item->system_quantity;
            } else {
                $this->warning("此商品不在該倉庫的盤點範圍內");
                $this->product_id = null;
                $this->product_name = null;
            }
        }
    }

    public function selectProduct(int $productId): void
    {
        $this->product_id = $productId;
        $this->updatedProductId($productId);
    }

    public function updateItem()
    {
        $this->validate([
            'product_id'      => 'required',
            'actual_quantity' => 'required|numeric|min:0',
        ]);

        $item = StocktakeItem::where('stocktake_id', $this->stocktake_id)
            ->where('product_id', $this->product_id)
            ->first();

        if ($item) {
            $item->update(['actual_quantity' => (string) $this->actual_quantity]);
            $this->success("已更新清點數：" . ($item->product?->name ?? '未知商品'));
            $this->reset(['product_id', 'product_name', 'actual_quantity', 'current_quantity']);
        }
    }

    public function showFinalizeConfirmation()
    {
        $this->missing_count = StocktakeItem::where('stocktake_id', $this->stocktake_id)
            ->whereNull('actual_quantity')
            ->count();

        $this->confirmModal = true;
    }

    /**
     * 結案：盤盈虧統一透過 InventoryAdjustment::createWithAccounting 過帳
     *
     * 🎯 設計要點：
     *   1. 一張調整單混合正負差異，postAccountingJournal 自動分流盈虧
     *   2. unit_cost 使用 StocktakeItem.cost_price 快照，非即時 product->cost
     *   3. 漏盤項目 actual_quantity 設 0，差異為負，走盤虧規則
     *   4. 庫存更新、流水帳、會計分錄全由 InventoryAdjustment 一次完成
     */
    public function finalize()
    {
        DB::transaction(function () {
            $stocktake = Stocktake::with('items')
                ->lockForUpdate()
                ->findOrFail($this->stocktake_id);

            $adjustmentItems = [];

            foreach ($stocktake->items as $item) {
                $isMissing = is_null($item->actual_quantity);
                $finalQty  = $isMissing ? '0.0000' : (string) $item->actual_quantity;
                $diff      = bcsub($finalQty, (string) $item->system_quantity, 4);

                if (bccomp($diff, '0.0000', 4) === 0) {
                    if ($isMissing) $item->update(['actual_quantity' => 0]);
                    continue;
                }

                $adjustmentItems[] = [
                    'product_id' => $item->product_id,
                    'quantity'   => $diff,
                ];

                if ($isMissing) $item->update(['actual_quantity' => 0]);
            }

            // 建一張調整單，盈虧自動分流過帳
            if (!empty($adjustmentItems)) {
                InventoryAdjustment::createWithAccounting([
                    'shop_id'      => $stocktake->shop_id,
                    'warehouse_id' => $stocktake->warehouse_id,
                    'type'         => 'stocktake_adj',
                    'remark'       => "盤點單 #{$stocktake->id} 自動調整",
                    'items'        => $adjustmentItems,
                ]);
            }

            $stocktake->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);
        });

        $this->reset([
            'stocktake_id', 'warehouse_id', 'confirmModal',
            'product_id', 'product_name', 'actual_quantity',
            'current_quantity',
        ]);
        $this->currentStocktake = null;
        $this->success("結案成功，庫存與會計日記帳已同步。");
    }

    public function cancelStocktake()
    {
        if ($this->stocktake_id) {
            DB::transaction(function () {
                $stocktake = Stocktake::find($this->stocktake_id);
                if ($stocktake) {
                    $stocktake->delete();
                }
            });
            $this->reset(['stocktake_id', 'warehouse_id', 'product_id', 'product_name', 'actual_quantity']);
            $this->currentStocktake = null;
            $this->warning("盤點任務已取消，未對庫存產生影響。");
        }
    }

    public function render()
    {
        return view('livewire.inventories.stocktakes', [
            'warehouses' => Warehouse::all(),
            'items' => $this->stocktake_id
                ? StocktakeItem::where('stocktake_id', $this->stocktake_id)
                    ->with('product')
                    ->orderBy('updated_at', 'desc')
                    ->get()
                : []
        ]);
    }
}