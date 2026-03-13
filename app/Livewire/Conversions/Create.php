<?php

namespace App\Livewire\Conversions;

use App\Models\Conversion;
use App\Models\Product;
use App\Models\Warehouse;
use Livewire\Component;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;

class Create extends Component
{
    use Toast;

    // 單頭資料
    public $process_date;
    public $remark;
    public $store_id = 1;

    // 明細資料：1 為投入 (Input), 2 為產出 (Output)
    public array $items = [];

    public function mount()
    {
        $this->process_date = now()->format('Y-m-d');
        // 初始化各一行預留空間
        $this->addItem(1); 
        $this->addItem(2);
    }

    public function addItem(int $type)
    {
        $this->items[] = [
            'type' => $type,
            'product_id' => null,
            'warehouse_id' => 1,
            'quantity' => 1,
            'cost_snapshot' => 0,
        ];
    }

    public function removeItem(int $index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save()
    {
        $this->validate([
            'process_date' => 'required|date',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        DB::transaction(function () {
            $conversion = Conversion::create([
                'store_id' => $this->store_id,
                'process_date' => $this->process_date,
                'user_id' => auth()->id(),
                'remark' => $this->remark,
            ]);

            foreach ($this->items as $item) {
                $conversion->items()->create($item);
            }

            // 執行厚 Model 封裝的過帳邏輯
            $conversion->post();
        });

        $this->success('拆裝作業已完成並更新庫存');
        return redirect()->to('/conversions');
    }

    public function render()
    {
        return view('livewire.conversions.create', [
            'products' => Product::all(),
            'warehouses' => Warehouse::all(),
        ]);
    }
}