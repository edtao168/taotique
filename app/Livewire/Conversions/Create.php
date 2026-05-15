<?php // app/Livewire/Conversions/Create.php

namespace App\Livewire\Conversions;

use App\Models\Conversion;
use App\Models\Product;
use App\Models\Warehouse;
use App\Traits\HasProductSearch;
use App\Traits\HasShop;
use Livewire\Component;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;

class Create extends Component
{
    use Toast, HasShop, HasProductSearch;

    public ?Conversion $conversion = null;
    public bool $isEdit = false;
	public $conversion_no;
	public $process_date;
    public $warehouse_id; // Header 預設倉庫
    public $remark;
    public array $items = [];
    public array $productOptions = []; 

    // mount 增加參數注入
    public function mount(?Conversion $conversion = null)
    {
        if ($conversion && $conversion->exists) {
            $this->conversion = $conversion;
            $this->isEdit = true;
            $this->fillForm(); // 填充現有資料
        } else {
            $this->initNewRecord(); // 初始化新單據
        }
        
        $this->search();
    }

    // 初始化新單據邏輯
    protected function initNewRecord()
    {
        $prefix = config('business.ic_prefix', 'IC-');
        $datePart = now()->format('Ymd');
        $count = Conversion::whereDate('created_at', now())->count() + 1;
        $this->conversion_no = $prefix . $datePart . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        $this->process_date = now()->format('Y-m-d');
        $this->warehouse_id = Warehouse::first()?->id;
        
        $this->addItem(1); 
        $this->addItem(2);
    }

    // 填充現有資料邏輯 (修改模式)
    protected function fillForm()
    {
        $this->conversion_no = $this->conversion->conversion_no;
        $this->process_date = $this->conversion->process_date->format('Y-m-d');
        $this->warehouse_id = $this->conversion->warehouse_id;
        $this->remark = $this->conversion->remark;
        $this->shop_id = $this->conversion->shop_id;

        // 將明細轉為陣列供前端呈現
        $this->items = $this->conversion->items->map(function ($item) {
            return [
                'id' => $item->id, // 修改時需保留 ID 以便更新
                'type' => $item->type,
                'product_id' => $item->product_id,
                'warehouse_id' => $item->warehouse_id,
                'quantity' => $item->quantity,
                'cost_snapshot' => $item->cost_snapshot,
            ];
        })->toArray();
    }

    public function addItem(int $type)
    {
        $this->items[] = [
            'type' => $type,
            'product_id' => null,
            'warehouse_id' => $this->warehouse_id, // 預設跟隨 Header
            'quantity' => '1.0000',
            'cost_snapshot' => '0.0000',
        ];
    }

    public function save()
    {
        $this->validate([
            'warehouse_id' => 'required',
            'process_date' => 'required|date',
            'items.*.product_id' => 'required',
            'items.*.warehouse_id' => 'required',
            'items.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        DB::transaction(function () {
            if ($this->isEdit) {
                // 修改邏輯
                $this->conversion->update([
                    'process_date' => $this->process_date,
                    'remark' => $this->remark,
                    'warehouse_id' => $this->warehouse_id,
                ]);
                // 簡易處理：刪除舊明細重新建立 (或根據 ID 更新)
                $this->conversion->items()->delete();
                foreach ($this->items as $item) {
                    $this->conversion->items()->create($item);
                }
            } else {
                // 原有的新增邏輯
                $conversion = Conversion::create([
                    'shop_id' => $this->shop_id,
                    'warehouse_id' => $this->warehouse_id,
                    'conversion_no' => $this->conversion_no,
                    'process_date' => $this->process_date,
                    'user_id' => auth()->id(),
                    'remark' => $this->remark,
                ]);
                foreach ($this->items as $item) {
                    $conversion->items()->create($item);
                }
                $conversion->post(); 
            }
        });

        $this->success($this->isEdit ? '作業已修改' : '拆裝作業已完成');
        return redirect()->route('inventories.conversions.index');
    }

    public function render()
    {
        return view('livewire.conversions.create', [
            'warehouses' => Warehouse::all(),
        ]);
    }
}