<?php
// [路徑]: app/Livewire/Inventories/AdjustStock.php

namespace App\Livewire\Inventories;

use App\Models\InventoryAdjustment;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Warehouse;
use App\Traits\HasSingleProductPicker;
use Livewire\Component;
use Mary\Traits\Toast;

class AdjustStock extends Component
{
    use HasSingleProductPicker, Toast;

    public $shop_id = 1;
    public $warehouse_id;
    public $type = 'initial';
    public $product_id = null;
    public $product_name = '';
    public $quantity = 1;
    public $remark;

    public function mount()
    {
        $this->warehouse_id = Warehouse::value('id');
        $this->refreshProductOptions();   // 初始載入 15 筆
    }

    public function updatedProductId($value)
    {
        if ($value) {
            $product = Product::find($value);
            $this->product_name = $product ? $product->full_display_name : '';
        } else {
            $this->product_name = '';
        }
        // 選定後清空搜尋關鍵字，避免下拉殘留
        $this->productSearch = '';
    }

    protected function rules(): array
    {
        return [
            'warehouse_id' => 'required|exists:warehouses,id',
            'product_id'   => 'required|exists:products,id',
            'type'         => 'required|in:' . implode(',', array_keys(InventoryMovement::getManualAdjustTypes())),
            'quantity'     => 'required|numeric|gt:0',
            'remark'       => 'nullable|string|max:255',
        ];
    }

    public function save()
    {
        $this->validate();

        $outboundTypes = ['gift', 'scrap', 'sample', 'miscellaneous_out'];
        $finalQuantity = in_array($this->type, $outboundTypes) 
            ? (string) (-abs((float) $this->quantity)) 
            : (string) abs((float) $this->quantity);

        InventoryAdjustment::createWithAccounting([
            'shop_id'      => $this->shop_id,
            'warehouse_id' => $this->warehouse_id,
            'type'         => $this->type,
            'remark'       => $this->remark,
            'items'        => [
                [
                    'product_id' => $this->product_id,
                    'quantity'   => $finalQuantity,
                ],
            ],
        ]);

        $this->reset(['product_id', 'product_name', 'quantity', 'remark']);
        $this->type = 'initial';
        $this->productSearch = '';
        $this->refreshProductOptions();

        $this->success('庫存調整單已建立，實體庫存與會計日記帳已同步完成！');
    }

    public function render()
    {
        $typeOptions = collect(InventoryMovement::getManualAdjustTypes())->map(function ($name, $id) {
            return ['id' => $id, 'name' => $name];
        })->values()->all();

        return view('livewire.inventories.adjust-stock', [
            'typeOptions' => $typeOptions,
            'warehouses'  => Warehouse::select('id', 'name')->get(),
        ]);
    }
}