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
    public int $quantity = 1;
    public string $remark = '';
	public string $search = '';	

    /**
     * 執行調撥邏輯
     */
    public function transfer()
    {
        $this->validate([
            'from_warehouse_id' => 'required|different:to_warehouse_id',
            'to_warehouse_id' => 'required',
            'product_id' => 'required',
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () {
                // 1. 鎖定來源庫存 (inventories 才有 store_id)
                $sourceInv = Inventory::where('warehouse_id', $this->from_warehouse_id)
                    ->where('product_id', $this->product_id)
                    ->where('store_id', 1) // 多店預留於庫存層
                    ->lockForUpdate()
                    ->first();

                if (!$sourceInv || $sourceInv->quantity < $this->quantity) {
                    throw new \Exception("來源倉庫存不足！(目前: " . ($sourceInv->quantity ?? 0) . ")");
                }

                // 2. 數值嚴謹運算 (bc函式)
                $sourceInv->quantity = bcsub($sourceInv->quantity, $this->quantity, 4);
                $sourceInv->save();

                // 3. 增加目標庫存
                $targetInv = Inventory::firstOrCreate(
                    ['warehouse_id' => $this->to_warehouse_id, 'product_id' => $this->product_id, 'store_id' => 1],
                    ['quantity' => 0]
                );
                $targetInv->quantity = bcadd($targetInv->quantity, $this->quantity, 4);
                $targetInv->save();

                // 4. 紀錄異動 (Movement)
                $logBase = [
                    'product_id' => $this->product_id,
                    'user_id' => auth()->id(),
                    'store_id' => 1,
                    'type' => 'transfer',
                ];

                InventoryMovement::create(array_merge($logBase, [
                    'warehouse_id' => $this->from_warehouse_id,
                    'quantity' => -$this->quantity,
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

        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public function searchProducts(string $value = '')
    {
        // 更新搜尋文字屬性
        $this->search = $value;

        // 直接 return 給 Mary UI，這是最穩定的做法
        return Product::query()
            ->where(function($q) use ($value) {
                $q->where('sku', 'like', "%{$value}%")
                  ->orWhere('name', 'like', "%{$value}%");
            })
            ->take(10)
            ->get();
    }
	
	public function render()
    {
        $products = $this->search 
            ? Product::where('sku', 'like', "%{$this->search}%")
                     ->orWhere('name', 'like', "%{$this->search}%")
                     ->take(10)->get()
            : Product::take(5)->get();

        return view('livewire.inventories.transfers', [
            'warehouses' => Warehouse::all(),
            'products' => $products,
        ]);
    }	
}