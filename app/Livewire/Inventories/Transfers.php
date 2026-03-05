<?php

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

    public function transfer()
    {
        $this->validate([
            'from_warehouse_id' => 'required|different:to_warehouse_id',
            'to_warehouse_id' => 'required',
            'product_id' => 'required',
            'quantity' => 'required|integer|min:1',
        ], [
            'from_warehouse_id.different' => '來源倉庫與目標倉庫不能相同。',
        ]);

        // 檢查來源倉庫存量
        $sourceInv = Inventory::where('warehouse_id', $this->from_warehouse_id)
            ->where('product_id', $this->product_id)
            ->first();

        if (!$sourceInv || $sourceInv->quantity < $this->quantity) {
            $this->error("來源倉庫存不足！(現有: " . ($sourceInv->quantity ?? 0) . ")");
            return;
        }

        DB::transaction(function () use ($sourceInv) {
            // 1. 扣除來源
            $sourceInv->decrement('quantity', $this->quantity);

            // 2. 增加目標 (如果目標倉庫沒有該商品紀錄則新增)
            $targetInv = Inventory::firstOrCreate(
				['warehouse_id' => $this->to_warehouse_id, 'product_id' => $this->product_id],
				['quantity' => 0]
			);
			$targetInv->increment('quantity', $this->quantity);

            // 3. 未來可以在此處紀錄一筆庫存異動流水 (Movement)
			InventoryMovement::create([
				'product_id' => $this->product_id,
				'warehouse_id' => $this->from_warehouse_id,
				'quantity' => -$this->quantity, // 出庫為負
				'type' => 'transfer',
				'user_id' => auth()->id(),
				'remark' => "撥至目標倉庫 ID: {$this->to_warehouse_id}",
			]);

			InventoryMovement::create([
				'product_id' => $this->product_id,
				'warehouse_id' => $this->to_warehouse_id,
				'quantity' => $this->quantity, // 入庫為正
				'type' => 'transfer',
				'user_id' => auth()->id(),
				'remark' => "從來源倉庫 ID: {$this->from_warehouse_id} 撥入",
			]);
        });

        $this->success("調撥成功！");
        $this->reset(['product_id', 'quantity', 'remark']);
    }

    public function render()
    {
        return view('livewire.inventories.transfers', [
            'warehouses' => Warehouse::with('shop')->get(),
            'products' => Product::orderBy('sku')->get(),
        ]);
    }
}