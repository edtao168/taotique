<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Models\ProductImage;
use App\Traits\HasProductMedia;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\Storage;

class Edit extends Component
{
    use Toast, WithFileUploads, HasProductMedia;

    public Product $product;
    public $name;
    public $price;
    public $unit;
    public $min_stock;
    public $remark;
    public $is_active;
    public $new_photos = [];      // 累積的所有檔案
    public $temp_photos = [];     // 臨時接收新上傳
    public $video;

    // 關鍵：當 temp_photos 更新時，合併到 new_photos
    public function updatedTempPhotos()
    {
        if (!empty($this->temp_photos)) {
            foreach ($this->temp_photos as $photo) {
                $this->new_photos[] = $photo;
            }
            $this->temp_photos = []; // 清空，準備下一次
			$this->dispatch('temp-photos-merged'); // 可選：通知前端
        }
    }

    public function render()
    {
        $this->product->load('images');
        return view('livewire.products.edit');
    }
    
    public function mount()
    {
        $this->name = $this->product->name;
        $this->price = $this->product->price;
        $this->unit = $this->product->unit;
        $this->min_stock = $this->product->min_stock;
        $this->remark = $this->product->remark;
        $this->is_active = $this->product->is_active;
    }

    protected function rules()
    {
        return [
            'name' => 'required|min:2',
            'price' => 'required|numeric|min:0',
            'unit' => 'required',
            'min_stock' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'remark' => 'nullable|string',
        ];
    }

    public function deleteImage(ProductImage $image)
    {
        $this->deleteMedia($image);
        $this->product->load('images');
        $this->success("媒體已刪除");
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|min:2',
            'price' => 'required|numeric',
            'new_photos.*' => 'nullable|image|max:2048', 
            'video' => 'nullable|mimetypes:video/mp4,video/quicktime|max:20480',
        ]);

        $this->product->update([
            'name' => $this->name,
            'price' => $this->price,
            'unit' => $this->unit,
            'min_stock' => $this->min_stock,
            'remark' => $this->remark,
            'is_active' => $this->is_active,
        ]);

        $this->uploadMedia($this->product, $this->new_photos, $this->video);
        
        $this->reset(['new_photos', 'temp_photos', 'video']);
        $this->success('商品資訊與媒體更新成功！', redirectTo: route('products.index'));
    }
}