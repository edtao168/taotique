<?php
// app/Livewire/Conversions/Create.php

namespace App\Livewire\Conversions;

use App\Models\Conversion;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Setting;
use App\Models\Shop;
//use App\Traits\HasProductSearch;
use App\Traits\HasShop;
use Livewire\Component;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;

class Create extends Component
{
    use Toast, HasShop;

    public ?Conversion $conversion = null;
    public bool $isEdit = false;
    
    // ✅ 對齊 Sale：獨立屬性存放單號
    public string $conversion_no = '';
    
    // 強型別結構宣告（對齊 Sale）
    public array $form = [
        'shop_id'            => 1,
        'warehouse_id'       => null,
        'process_date'       => null,
        'remark'             => '',
        'variance_treatment' => 'expense',
    ];
    
    public array $items = [];
    public array $inputProductOptions = [];
	public array $outputProductOptions = [];
    
    // 成本差異預覽相關屬性
    public bool $showVariancePreview = false;
    public string $previewInputTotal = '0.0000';
    public string $previewOutputTotal = '0.0000';
    public string $previewVariance = '0.0000';
    public string $previewVarianceType = '平衡';

    public function mount(?Conversion $conversion = null)
    {
        if ($conversion && $conversion->exists) {
            // ✅ 編輯模式（對齊 Sale）
            $this->isEdit = true;
            $this->conversion = $conversion;
            
            // ✅ 載入單號到獨立屬性
            $this->conversion_no = $conversion->conversion_no;
            
            $this->form = [
                'shop_id' => (int) ($conversion->shop_id ?? 1),
                'warehouse_id' => (int) $conversion->warehouse_id,
                'process_date' => $conversion->process_date->format('Y-m-d\TH:i'),
                'remark' => $conversion->remark ?? '',
                'variance_treatment' => 'capitalize',
            ];
            
            $this->items = $conversion->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'type' => (int) $item->type,
                    'product_id' => (int) $item->product_id,
                    'warehouse_id' => (int) $item->warehouse_id,
                    'quantity' => (string) $item->quantity,
                    'cost_snapshot' => (string) $item->cost_snapshot,
                    'name' => $item->product?->full_display_name ?? '',
                ];
            })->toArray();
            
            $this->calculateAll();
            
        } else {
            // ✅ 新增模式（對齊 Sale：手動產生單號）
            $this->conversion_no = Conversion::generateConversionNo();
            
            $this->form = [
                'shop_id' => (int) (auth()->user()->shop_id ?? 1),
                'warehouse_id' => (int) (Warehouse::first()?->id ?? 1),
                'process_date' => now()->format('Y-m-d'),
                'remark' => '',
                'variance_treatment' => 'capitalize',
            ];
            
            $this->items = [];
            $this->addItem(1);
            $this->addItem(2);
        }
    }

    /**
     * 驗證規則（對齊 Sale）
     */
    protected function rules(): array
    {
        $conversionId = $this->conversion?->id ?? 'NULL';
        
        return [
            'form.shop_id'          => 'required|integer|exists:shops,id',
            'form.warehouse_id'     => 'required|integer|exists:warehouses,id',
            'form.process_date'     => 'required|date',
            'conversion_no'         => 'required|string|unique:conversions,conversion_no,' . $conversionId . ',id',
            'form.remark'           => 'nullable|string',
            'form.variance_treatment' => 'required|in:expense,capitalize,inventory',
            'items'                 => 'required|array|min:2',
            'items.*.type'          => 'required|in:1,2',
            'items.*.product_id'    => 'required|integer|exists:products,id',
            'items.*.warehouse_id'  => 'required|integer|exists:warehouses,id',
            'items.*.quantity'      => 'required|numeric|min:0.0001',
            'items.*.cost_snapshot' => 'required|numeric|min:0',
        ];
    }

    /**
     * 自訂屬性名稱（對齊 Sale）
     */
    public function validationAttributes(): array
    {
        return [
            'form.shop_id'              => '分店',
            'form.warehouse_id'         => '倉庫',
            'form.process_date'         => '作業日期',
            'conversion_no'             => '單號',
            'form.remark'               => '備註',
            'form.variance_treatment'   => '成本差異處理',
            'items'                     => '明細項目',
            'items.*.type'              => '項目類型',
            'items.*.product_id'        => '商品',
            'items.*.warehouse_id'      => '倉庫',
            'items.*.quantity'          => '數量',
            'items.*.cost_snapshot'     => '單位成本',
        ];
    }

    /**
     * 即時驗證（對齊 Sale）
     */
    public function updated($propertyName): void
    {
        $this->validateOnly($propertyName);
    }

    /**
     * 當倉庫變動時，同步更新明細中未指定的倉庫（對齊 Sale）
     */
    public function updatedForm($value, $key): void
    {
        if ($key === 'warehouse_id' && !empty($value)) {
            foreach ($this->items as $index => $item) {
                if (empty($item['warehouse_id'])) {
                    $this->items[$index]['warehouse_id'] = (int) $value;
                }
            }
        }
        
        $this->calculateAll();
    }

    /**
     * 當商品選擇變更時，自動帶入成本（對齊 Sale 的 updatedItems）
     */
	public function updatedItems($value, $key): void
	{
		$parts = explode('.', $key);
		$index = $parts[0];
		$field = $parts[1] ?? null;
		
		if ($field === 'product_id') {
			$product = Product::find($this->items[$index]['product_id']);
			if ($product) {
				// 只更新名稱
				$this->items[$index]['name'] = $product->full_display_name;
				
				// 只有投入項目才自動帶入成本
				if ($this->items[$index]['type'] == 1) {
					$inventory = Inventory::where('product_id', $product->id)
						->where('warehouse_id', $this->items[$index]['warehouse_id'])
						->first();
					$cost = $inventory->cost ?? $product->cost ?? '0.0000';
					
					// 只有當成本還是 0 時才自動帶入
					if (bccomp($this->items[$index]['cost_snapshot'] ?? '0', '0', 4) == 0) {
						$this->items[$index]['cost_snapshot'] = (string) $cost;
					}
				}
				// ✅ 產出項目：完全不動 cost_snapshot，保留使用者輸入
				
				$this->calculateAll();
			}
		}
		
		if (in_array($field, ['quantity', 'cost_snapshot'])) {
			$this->calculateAll();
		}
	}

    /**
     * 新增明細項目（對齊 Sale 的 addRow）
     */
    public function addItem(int $type): void
    {
        $this->items[] = [
            'type' => $type,
            'product_id' => null,
            'warehouse_id' => (int) ($this->form['warehouse_id'] ?? 1),
            'quantity' => '1.0000',
            'cost_snapshot' => '0.0000',
            'name' => '',
        ];
        
        if ($type === 1) {
			$this->searchInputProducts();
		} else {
			$this->searchOutputProducts();
		}
    }
	
	/**
	 * 原料搜尋（獨立）
	 */
	public function searchInputProducts(string $value = ''): array
	{
		$this->inputProductOptions = $this->searchProductQuery($value);
		return $this->inputProductOptions;
	}

	/**
	 * 成品搜尋（獨立）
	 */
	public function searchOutputProducts(string $value = ''): array
	{
		$this->outputProductOptions = $this->searchProductQuery($value);
		return $this->outputProductOptions;
	}

	/**
	 * 共用查詢邏輯
	 */
	private function searchProductQuery(string $value = ''): array
	{
		$query = Product::where('is_active', true);

		if (strlen($value) >= 2) {
			$query->where(function($q) use ($value) {
				$q->where('sku', 'like', "%{$value}%")
				  ->orWhere('name', 'like', "%{$value}%");
			});
		}

		return $query->limit(20)
			->get()
			->map(fn($p) => [
				'id' => $p->id,
				'name' => $p->full_display_name,
			])
			->toArray();
	}
    
    /**
     * 移除明細項目（對齊 Sale 的 removeRow）
     */
    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->calculateAll();
    }

    /**
     * 計算成本差異預覽（對齊 Sale 的 calculateAll）
     */
    protected function calculateAll(): void
    {
        $inputTotal = '0.0000';
        $outputTotal = '0.0000';
        
        foreach ($this->items as $item) {
            $itemCost = bcmul($item['cost_snapshot'] ?? '0', $item['quantity'] ?? '0', 4);
            if (($item['type'] ?? 0) == 1) {
                $inputTotal = bcadd($inputTotal, $itemCost, 4);
            } else {
                $outputTotal = bcadd($outputTotal, $itemCost, 4);
            }
        }
        
        $variance = bcsub($inputTotal, $outputTotal, 4);
        
        $this->previewInputTotal = $inputTotal;
        $this->previewOutputTotal = $outputTotal;
        $this->previewVariance = $variance;
        $this->previewVarianceType = bccomp($variance, '0', 4) > 0 ? '耗損' : (bccomp($variance, '0', 4) < 0 ? '盤盈' : '平衡');
    }

    /**
     * 預覽成本差異
     */
    public function previewVariance(): void
    {
        $this->calculateAll();
        $this->showVariancePreview = true;
    }

    /**
     * 驗證投入和產出至少各有一項
     */
    protected function validateItemsTypes(): bool
    {
        $hasInput = collect($this->items)->contains(fn($item) => ($item['type'] ?? 0) == 1);
        $hasOutput = collect($this->items)->contains(fn($item) => ($item['type'] ?? 0) == 2);
        
        if (!$hasInput || !$hasOutput) {
            $this->addError('items', '拆裝作業必須同時包含「領料投入」和「成品產出」');
            return false;
        }
        
        return true;
    }

    /**
     * 核心儲存邏輯
     */
    public function save(): void
    {
		$this->validate();
		
		if (!$this->validateItemsTypes()) {
			return;
		}

		try {
			DB::transaction(function () {
				if ($this->isEdit) {
					$this->warning('拆裝單過帳後不可修改，請使用調整單處理');
					return;
				}
				
				// 新增模式
				$conversion = Conversion::create([
					'shop_id' => $this->form['shop_id'],
					'warehouse_id' => $this->form['warehouse_id'],
					'conversion_no' => $this->conversion_no,
					'process_date' => $this->form['process_date'],
					'user_id' => auth()->id(),
					'remark' => $this->form['remark'],
				]);
				
				foreach ($this->items as $item) {
					$conversion->items()->create([
						'type' => $item['type'],
						'product_id' => $item['product_id'],
						'warehouse_id' => $item['warehouse_id'],
						'quantity' => $item['quantity'],
						'cost_snapshot' => $item['cost_snapshot'],
					]);
				}

				//$conversion->post();
				
				$this->success('拆裝作業已完成', redirectTo: route('inventories.conversions.index'));
			});

		} catch (\Exception $e) {
			$this->error('儲存失敗：' . $e->getMessage());
		}
    }

    public function render()
    {
        return view('livewire.conversions.create', [
            'shops' => Shop::all()->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
            ]),
            'warehouses' => Warehouse::where('is_active', true)->get(),
            'varianceOptions' => [
                ['id' => 'capitalize', 'name' => '耗損資本化（併入在製品成本）'],
				['id' => 'expense', 'name' => '耗損進費用（製造費用）'],                
                ['id' => 'inventory', 'name' => '耗損作為庫存調整'],
            ],
        ]);
    }
}