<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\CategoryDefinition;
use App\Models\MaterialDefinition;
use App\Traits\HasProductMedia;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

class Create extends Component
{
    use Toast, WithFileUploads, HasProductMedia;

    // 表單欄位
    public ?string $category_id = null; // 對應選單選中的分類 code
    public ?string $material_id = null; // 對應選單選中的材質 id
    public $sku;
    public $name;
    public $price = 0;
    public $unit = 'ea';
    public $remark = '';
    public $min_stock = 0;
	public $new_photos = [];

    public function render()
    {
        return view('livewire.products.create', [
            'categories' => CategoryDefinition::all(),
            'materials' => MaterialDefinition::orderBy('bb_code')->get(),
        ]);
    }
	
    public function updated($propertyName)
    {
        if (in_array($propertyName, ['category_id', 'material_id'])) {
            $this->generateSku();
        }
    }

	public function generateSku()
    {
        if (!$this->category_id || !$this->material_id) return;

        // 修正：category_definitions 使用 code 查詢而非 id
        $cat = CategoryDefinition::where('code', $this->category_id)->first();
        // material_definitions 若主鍵是 id 則保持 find，若也是 code 則改用 where
        $mat = MaterialDefinition::find($this->material_id);

        if ($cat && $mat) {
            $prefix = $cat->code . $mat->bb_code . $mat->c_code;

            $lastProduct = Product::where('sku', 'like', $prefix . '%')
                ->orderBy('sku', 'desc')
                ->first();

            if ($lastProduct) {
                $lastNumber = intval(substr($lastProduct->sku, -4));
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '0001';
            }

            $this->sku = $prefix . $newNumber;
            $this->name = $mat->name.$cat->name;
        }
    }
    
	/**
     * 刪除已存在的媒體 (圖片或影片)
     */
    public function deleteImage(ProductImage $image)
    {
        // 呼叫 Trait 裡的共用方法
        $this->deleteMedia($image);
        
        // 刷新關聯，讓前端立刻看到檔案消失
        $this->product->load('images');
        
        $this->success("媒體已刪除");
    }
	
    public function save()
    {
        $this->validate([
            'category_id' => 'required',
            'material_id' => 'required',
            'sku' => 'required|unique:products,sku',
            'name' => 'required|min:2',
            'price' => 'required|numeric',
        ]);
		
        $cat = CategoryDefinition::where('code', $this->category_id)->first();
        $mat = MaterialDefinition::find($this->material_id);
		
        if (!$cat || !$mat) {
            $this->error('無效的分類或材質');
            return;
        }

        Product::create([
            'sku' => $this->sku,
            'name' => $this->name,
            'price' => $this->price,
            'category_code' => $cat->code,
            'bb_code' => $mat->bb_code,
            'c_code' => $mat->c_code,
            'unit' => $this->unit,
            'min_stock' => $this->min_stock,
            'remark' => $this->remark,
            'is_active' => true,
        ]);
		
		$this->uploadMedia($this->product, $this->new_photos, $this->video);
        
        $this->reset(['new_photos', 'video']);
        $this->success('商品基本資料建檔成功！', redirectTo: '/products');
    }

}