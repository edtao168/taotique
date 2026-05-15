<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Models\ProductImage;
use App\Traits\HasProductMedia;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;

class Edit extends Component
{
    use Toast, WithFileUploads, HasProductMedia;

    public Product $product;
    public $is_unique;
    public $name;
    public $price;
    public $cost;
    public $unit;
    public $min_stock;
    public $remark;
    public $is_active;
    public $new_media = [];
    public $temp_media = [];
	public $isUploading = false;
    public $deletingMediaId = null;

    /*
	 * 程式統一用media取代image，image來自product_images
	 */
    public function mount()
    {
        $this->is_unique = $this->product->is_unique;
        $this->name = $this->product->name;
        $this->price = $this->product->price;
        $this->cost = $this->product->cost;
        $this->unit = $this->product->unit;
        $this->min_stock = $this->product->min_stock;
        $this->remark = $this->product->remark;
        $this->is_active = $this->product->is_active;
    }

    public function updatedTempMedia()
    {
        if (!empty($this->temp_media)) {
			$this->isUploading = true;
			
            foreach ($this->temp_media as $file) {
                if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                    $this->new_media[] = $file;
                }
            }
            $this->temp_media = [];
            $this->dispatch('temp-media-merged');
			
			$this->isUploading = false;
        }
    }

    protected function rules()
    {
        return [
            'is_unique' => 'boolean',
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
        $this->deletingMediaId = $image->id;
		
		$this->deleteMedia($image);
        $this->product->load('images');
		$this->deletingMediaId = null;
        $this->success("媒體已刪除");
    }

    public function deleteTempMedia($index)
    {
		$this->deletingMediaId = 'temp_' . $index;
		
        if (isset($this->new_media[$index])) {
            unset($this->new_media[$index]);
            $this->new_media = array_values($this->new_media);
            $this->success("臨時媒體已移除");
        }
		
		$this->deletingMediaId = null;
    }

    public function save()
    {
        $rules = [
            'name' => 'required|min:2',
            'price' => 'required|numeric|min:0',
            'unit' => 'required',
            'min_stock' => 'required|integer|min:0',
            'new_media.*' => [
                'nullable',
                'mimetypes:' . config('business.media.media_mimetypes'),
                'max:' . config('business.media.media_max_kb'),
            ],
        ];

        if (auth()->user()->role === 'owner') {
            $rules['cost'] = 'required|numeric|min:0';
        }

        $this->validate($rules);

        $updateData = [
            'is_unique' => $this->is_unique,
            'name' => $this->name,
            'price' => $this->price,
            'unit' => $this->unit,
            'min_stock' => $this->min_stock,
            'remark' => $this->remark,
            'is_active' => $this->is_active,
        ];

        if (auth()->user()->role === 'owner') {
            $updateData['cost'] = $this->cost;
        }

        $this->product->update($updateData);
        $this->uploadMedia($this->product, $this->new_media);

        $this->reset(['new_media', 'temp_media']);
        $this->success('商品資訊與媒體更新成功！', redirectTo: route('products.index'));
    }

    public function setPrimary($imageId)
    {
        DB::transaction(function () use ($imageId) {
            ProductImage::where('product_id', $this->product->id)->update(['is_primary' => false]);
            ProductImage::where('id', $imageId)->update(['is_primary' => true]);
        });

        $this->product->load('images');
        $this->success("首圖已更新");
    }

    public function setTempPrimary($index)
    {
        if (isset($this->new_media[$index])) {
            $target = $this->new_media[$index];
            unset($this->new_media[$index]);
            array_unshift($this->new_media, $target);
            $this->new_media = array_values($this->new_media);
            $this->success("已預設該媒體為首圖");
        }
    }
	
    public function render()
    {
        $this->product->load('images');
        return view('livewire.products.edit');
    }
}