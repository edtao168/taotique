<?php // app/Livewire/Inventories/Stocktakes.php

namespace App\Livewire\Inventories;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class Stocktakes extends Component
{
    use Toast;

    public ?int $warehouse_id = null;
    public ?int $product_id = null;
    public int $current_quantity = 0; // 系統顯示數量
    public int $actual_quantity = 0;  // 實際清點數量
    public string $remark = '';
	public array $products = []; 

    public function mount()
    {
        // 初始化時抓取預設商品，避免初次點開是空的
        $this->search();
    }
	// 定義初始載入的商品清單
	public function render()
    {
        return view('livewire.inventories.stocktakes', [
            'warehouses' => Warehouse::with('shop')->get(), 
        ]);
    }
    
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
            ])
			->toArray();
	}
	
    // 當選擇倉庫或商品改變時，自動抓取系統當前庫存
    public function updated($property)
    {
        if ($property === 'warehouse_id' || $property === 'product_id') {
            $this->fetchCurrentStock();
        }
    }

    public function fetchCurrentStock()
    {
        if ($this->warehouse_id && $this->product_id) {
            $inv = Inventory::where('warehouse_id', $this->warehouse_id)
                ->where('product_id', $this->product_id)
                ->first();
            
            $this->current_quantity = $inv ? $inv->quantity : 0;
            $this->actual_quantity = $this->current_quantity; // 預設實際數量等於系統數量，方便微調
        }
    }

    public function submit()
    {
        $this->validate([
            'warehouse_id' => 'required',
            'product_id' => 'required',
            'actual_quantity' => 'required|integer|min:0',
        ]);

        DB::transaction(function () {
			// 計算差額 (例如：系統 12 件，手數 10 件，差額就是 -2)
			$diff = $this->actual_quantity - $this->current_quantity;
            // 1. 強制修正現有庫存 (修改 inventories 表)
            Inventory::updateOrCreate(
                ['warehouse_id' => $this->warehouse_id, 'product_id' => $this->product_id],
                ['quantity' => $this->actual_quantity]
            );

            // 2. 「自動」紀錄流水帳 (寫入 inventory_movements 表)
			if ($diff != 0) {
				$systemLog = "盤點校正：從 {$this->current_quantity} 修正為 {$this->actual_quantity}";
				$finalRemark = $this->remark 
					? "{$systemLog} | 原因：{$this->remark}" 
					: $systemLog;
				
				InventoryMovement::create([
					'product_id' => $this->product_id,
					'warehouse_id' => $this->warehouse_id,
					'quantity' => $diff,
					'type' => 'stocktake',
					'user_id' => auth()->id(),
					'remark' => $finalRemark,
				]);		
			}				
        });

        $this->success("盤點更新成功！項目已同步至最新數量。");
        $this->reset(['product_id', 'current_quantity', 'actual_quantity', 'remark']);
    }
}