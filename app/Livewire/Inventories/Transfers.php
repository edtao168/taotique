<?php // app/Livewire/Inventories/Transfers.php

namespace App\Livewire\Inventories;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Traits\HasSingleProductPicker;
use App\Traits\HasShop;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class Transfers extends Component
{
    use HasSingleProductPicker, HasShop, Toast;

    public ?int $from_warehouse_id = null;
    public ?int $to_warehouse_id = null;
    public ?int $product_id = null;
    public string $product_name = ''; // 1. 補上顯示名稱，供 <x-product-picker> 顯示
    public string $quantity = '1.0000'; 
    public string $remark = '';

    // 注意：$productSearch 與 $productOptions 已由 Trait 提供，此處不需重複宣告

    public function mount()
    {
        // 初始載入 15 筆預設商品選單
        $this->refreshProductOptions(); 
    }

    /**
     * 當 product_id 變動時（例如由 fillProduct 觸發），同步更新顯示名稱
     */
    public function updatedProductId($value)
    {
        if ($value) {
            $product = Product::find($value);
            $this->product_name = $product ? $product->full_display_name : '';
        } else {
            $this->product_name = '';
        }
        $this->productSearch = '';
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
                // 1. 鎖定來源庫存並確保資料一致性，使用 shop_id 確保多店隔離
                $sourceInv = Inventory::where('warehouse_id', $this->from_warehouse_id)
                    ->where('product_id', $this->product_id)
                    ->where('shop_id', $this->shop_id) // 使用 HasShop 提供的 shop_id
                    ->lockForUpdate()
                    ->first();

                // 2. 數值嚴謹性判斷：使用 bccomp 比較庫存
                if (!$sourceInv || bccomp($sourceInv->quantity, $this->quantity, 4) === -1) {
                    $currentStock = $sourceInv->quantity ?? '0.0000';
                    throw new \Exception("來源倉庫存不足！(目前: " . number_format((float)$currentStock, 2) . ")");
                }

                // 3. 扣減來源庫存：使用 bcsub
                $sourceInv->quantity = bcsub($sourceInv->quantity, $this->quantity, 4);
                $sourceInv->save();

                // 4. 增加目標庫存：使用 bcadd 與 lockForUpdate
                $targetInv = Inventory::where('warehouse_id', $this->to_warehouse_id)
                    ->where('product_id', $this->product_id)
                    ->where('shop_id', $this->shop_id)
                    ->lockForUpdate()
                    ->first();

                if (!$targetInv) {
                    $targetInv = new Inventory();
                    $targetInv->shop_id = $this->shop_id;
                    $targetInv->warehouse_id = $this->to_warehouse_id;
                    $targetInv->product_id = $this->product_id;
                    $targetInv->quantity = '0.0000';
                }

                $targetInv->quantity = bcadd($targetInv->quantity, $this->quantity, 4);
                $targetInv->save();

                // 5. 紀錄異動流水
                $logBase = [
                    'product_id' => $this->product_id,
                    'user_id' => auth()->id(),
                    'shop_id' => $this->shop_id,
                    'type' => 'transfer',
                ];

                // 來源倉扣除
                InventoryMovement::create(array_merge($logBase, [
                    'warehouse_id' => $this->from_warehouse_id,
                    'quantity' => bcmul($this->quantity, '-1', 4),
                    'remark' => "撥至倉庫 ID: {$this->to_warehouse_id}. {$this->remark}",
                ]));

                // 目標倉撥入
                InventoryMovement::create(array_merge($logBase, [
                    'warehouse_id' => $this->to_warehouse_id,
                    'quantity' => $this->quantity,
                    'remark' => "從倉庫 ID: {$this->from_warehouse_id} 撥入. {$this->remark}",
                ]));
            });

            $this->success("調撥完成");
            
            // 重置表單欄位與商品選擇器
            $this->reset(['quantity', 'remark']);
            $this->resetProduct(); // 自動清空 product_id, product_name 並重新刷新下拉選單

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