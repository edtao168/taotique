<?php // app/Livewire/Inventories/Transfers.php

namespace App\Livewire\Inventories;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class Transfers extends Component
{
    use Toast;

    public ?int $from_warehouse_id = null;
    public ?int $to_warehouse_id = null;
    public ?int $product_id = null;
    public string $quantity = '1.0000'; // 符合 DECIMAL(16,4) 規格
    public string $remark = '';
    public array $products = []; // 儲存格式化後的商品選單

    public function mount()
    {
        $this->search(); // 初始載入預設商品
    }

    /**
     * 統一搜尋邏輯：與 Stocktakes 保持一致
     */
    public function search(string $value = '')
    {
        $this->products = Product::query()
            ->where('sku', 'like', "%{$value}%")
            ->orWhere('name', 'like', "%{$value}%")
            ->take(10)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'display_name' => "{$p->sku} - {$p->name}",
                'sku' => $p->sku
            ])
            ->toArray();
    }

    public function transfer()
    {
        $this->validate([
            'from_warehouse_id' => 'required|different:to_warehouse_id',
            'to_warehouse_id' => 'required',
            'product_id' => 'required',
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        try {
            DB::transaction(function () {
                // 1. 鎖定來源庫存並確保資料一致性
                $sourceInv = Inventory::where('warehouse_id', $this->from_warehouse_id)
                    ->where('product_id', $this->product_id)
                    ->where('store_id', 1) 
                    ->lockForUpdate()
                    ->first();

                if (!$sourceInv || bccomp($sourceInv->quantity, $this->quantity, 4) === -1) {
                    throw new \Exception("來源倉庫存不足！(目前: " . number_format($sourceInv->quantity ?? 0, 2) . ")");
                }

                // 2. 數值嚴謹運算：使用 bcsub
                $sourceInv->quantity = bcsub($sourceInv->quantity, $this->quantity, 4);
                $sourceInv->save();

                // 3. 增加目標庫存：使用 bcadd
                $targetInv = Inventory::firstOrCreate(
                    ['warehouse_id' => $this->to_warehouse_id, 'product_id' => $this->product_id, 'store_id' => 1],
                    ['quantity' => '0.0000']
                );
                $targetInv->quantity = bcadd($targetInv->quantity, $this->quantity, 4);
                $targetInv->save();

                // 4. 紀錄異動流水
                $logBase = [
                    'product_id' => $this->product_id,
                    'user_id' => auth()->id(),
                    'store_id' => 1,
                    'type' => 'transfer',
                ];

                InventoryMovement::create(array_merge($logBase, [
                    'warehouse_id' => $this->from_warehouse_id,
                    'quantity' => bcmul($this->quantity, '-1', 4),
                    'remark' => "撥至倉庫 ID: {$this->to_warehouse_id}. {$this->remark}",
                ]));

                InventoryMovement::create(array_merge($logBase, [
                    'warehouse_id' => $this->to_warehouse_id,
                    'quantity' => $this->quantity,
                    'remark' => "從倉庫 ID: {$this->from_warehouse_id} 撥入. {$this->remark}",
                ]));
            });

            $this->success("調撥完成");
            $this->reset(['product_id', 'quantity', 'remark']);
            $this->search(); // 重置商品清單

        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.inventories.transfers', [
            'warehouses' => Warehouse::all(),
        ]);
    }
}