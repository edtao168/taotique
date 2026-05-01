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
    public ?string $category_id = null;
    public ?string $material_id = null;
    public $sku;
    public $name;
    public $price = 0;
    public $unit = 'ea';
    public $remark = '';
    public $min_stock = 0;
    public $new_media = [];
    public $temp_media = [];

    public function render()
    {
        return view('livewire.products.create', [
            'categories' => CategoryDefinition::all(),
            'materials' => MaterialDefinition::orderBy('bb_code')->get(),
        ]);
    }

    public function updated($propertyName)
    {
        // ✅ 使用通用 updated 方法，確保 temp_media 更新時觸發
        if ($propertyName === 'temp_media') {
            $this->updatedTempMedia();
        }

        if (in_array($propertyName, ['category_id', 'material_id'])) {
            $this->generateSku();
        }
    }

    public function generateSku()
    {
        if (!$this->category_id || !$this->material_id) return;

        $cat = CategoryDefinition::where('code', $this->category_id)->first();
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
            $this->name = $mat->name . $cat->name;
        }
    }

    public function save()
    {
        $this->validate([
            'category_id' => 'required',
            'material_id' => 'required',
            'sku' => 'required|unique:products,sku',
            'name' => 'required|min:2',
            'price' => 'required|numeric',
            'unit' => 'required',
            'min_stock' => 'required|integer|min:0',
            'new_media.*' => [
                'nullable',
                'mimetypes:' . config('business.media.media_mimetypes'),
                'max:' . config('business.media.media_max_kb'),
            ],
        ]);

        $cat = CategoryDefinition::where('code', $this->category_id)->first();
        $mat = MaterialDefinition::find($this->material_id);

        if (!$cat || !$mat) {
            $this->error('無效的分類或材質');
            return;
        }

        $product = Product::create([
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

        $this->uploadMedia($product, $this->new_media);

        $this->reset(['new_media', 'temp_media']);
        $this->success('商品基本資料建檔成功！', redirectTo: '/products');
    }

    /**
     * 刪除暫存媒體
     */
    public function deleteTempMedia($index)
    {
        if (isset($this->new_media[$index])) {
            unset($this->new_media[$index]);
            $this->new_media = array_values($this->new_media);
        }
    }

    /**
     * 設定暫存媒體為首圖
     */
    public function setTempPrimary($index)
    {
        if (isset($this->new_media[$index])) {
            $target = $this->new_media[$index];
            unset($this->new_media[$index]);
            array_unshift($this->new_media, $target);
            $this->new_media = array_values($this->new_media);
        }
    }
	
	public function updatedTempMedia()
    {
        if (!empty($this->temp_media)) {
			//$this->isUploading = true;
			
            foreach ($this->temp_media as $file) {
                if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                    $this->new_media[] = $file;
                }
            }
            $this->temp_media = [];
            //$this->dispatch('temp-media-merged');
			
			//$this->isUploading = false;
        }
    }
}