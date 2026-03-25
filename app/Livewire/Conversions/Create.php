<?php // app/Livewire/Conversions/Create.php

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

    public $process_date;
    public $remark;
    public $store_id = 1;
    public array $items = [];
    public string $search = ''; // 追蹤搜尋關鍵字

    public function mount()
    {
        $this->process_date = now()->format('Y-m-d');
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
            'store_id' => $this->store_id,
        ];
    }

    public function removeItem(int $index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    // 搜尋邏輯與 Transfers 一致
    public function searchProducts(string $value = '')
    {
        $this->search = $value;

        return Product::query()
            ->where(function($q) use ($value) {
                $q->where('sku', 'like', "%{$value}%")
                  ->orWhere('name', 'like', "%{$value}%");
            })
            ->take(10)
            ->get();
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
            $conversion->post();
        });

        $this->success('拆裝作業已完成');
        return redirect()->to('/conversions');
    }

    public function render()
    {
        // 初始或搜尋時的商品清單
        $products = $this->search 
            ? Product::where('sku', 'like', "%{$this->search}%")
                     ->orWhere('name', 'like', "%{$this->search}%")
                     ->take(10)->get()
            : Product::take(5)->get();

        return view('livewire.conversions.create', [
            'products' => $products,
            'warehouses' => Warehouse::all(),
        ]);
    }
}