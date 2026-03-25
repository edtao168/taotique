<?php // app/Livewire/Conversions/Create.php
//商品選單比照盤點單，但因為這裏需要兩組商品選單，所以還是有些差異

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
    
    // 將搜尋結果分開
    public array $input_products = [];  // 原料搜尋結果
    public array $output_products = []; // 成品搜尋結果

    public function mount()
    {
        $this->process_date = now()->format('Y-m-d');
        $this->addItem(1); 
        $this->addItem(2);
        
        // 初始載入
        $this->searchInputs();
        $this->searchOutputs();
    }

    public function addItem(int $type)
    {
        $this->items[] = [
            'type' => $type,
            'product_id' => null,
            'warehouse_id' => 1,
            'quantity' => '1.0000',
            'cost_snapshot' => '0.0000',
            'store_id' => $this->store_id,
        ];
    }

    public function removeItem(int $index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /**
     * 搜尋原料 (Type 1)
     */
    public function searchInputs(string $value = '')
    {
        $this->input_products = Product::query()
            ->where(fn($q) => $q->where('sku', 'like', "%{$value}%")->orWhere('name', 'like', "%{$value}%"))
            // 可以在此加入過濾邏輯，例如：->where('category', 'raw_material')
            ->take(10)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'display_name' => "[原料] {$p->sku} - {$p->name}",
            ])
            ->toArray();
    }

    /**
     * 搜尋成品 (Type 2)
     */
    public function searchOutputs(string $value = '')
    {
        $this->output_products = Product::query()
            ->where(fn($q) => $q->where('sku', 'like', "%{$value}%")->orWhere('name', 'like', "%{$value}%"))
            // 可以在此加入過濾邏輯，例如：->where('category', 'finished_good')
            ->take(10)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'display_name' => "[成品] {$p->sku} - {$p->name}",
            ])
            ->toArray();
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
        return view('livewire.conversions.create', [
            'warehouses' => Warehouse::all(),
        ]);
    }
}