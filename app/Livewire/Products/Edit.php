<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Models\ProductImage;
use App\Traits\HasProductMedia;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
    public $new_photos = [];      // 累積的所有檔案
    public $temp_photos = [];     // 臨時接收新上傳
    public $video;

    // 關鍵：當 temp_photos 更新時，合併到 new_photos
    public function updatedTempPhotos()
    {
        if (!empty($this->temp_photos)) {
            foreach ($this->temp_photos as $photo) {
                if ($photo instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
					$this->new_photos[] = $photo;
				}
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
        $this->is_unique = $this->product->is_unique;
		$this->name = $this->product->name;
        $this->price = $this->product->price;
		$this->cost = $this->product->cost;
        $this->unit = $this->product->unit;
        $this->min_stock = $this->product->min_stock;
        $this->remark = $this->product->remark;
        $this->is_active = $this->product->is_active;
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
        $this->deleteMedia($image);
        $this->product->load('images');
        $this->success("媒體已刪除");
    }

	// 刪除尚未存檔的臨時圖片
	public function deleteTempPhoto($index)
	{
		if (isset($this->new_photos[$index])) {
			unset($this->new_photos[$index]);
			$this->new_photos = array_values($this->new_photos); // 重置索引
			$this->success("臨時圖片已移除");
		}
	}

    public function save()
    {
        // 整合驗證規則
        $rules = [
            'name' => 'required|min:2',
            'price' => 'required|numeric|min:0',
            'unit' => 'required',
            'min_stock' => 'required|integer|min:0',
            'new_photos.*' => 'nullable|mimes:jpg,jpeg,png,webp,avif|max:2048', 
            'video' => 'nullable|mimetypes:video/mp4,video/quicktime|max:20480',
        ];

        // 只有 Owner 需要驗證成本
        if (auth()->user()->role === 'owner') {
            $rules['cost'] = 'required|numeric|min:0';
        }

        $this->validate($rules);

        // 準備資料陣列
        $updateData = [
            'is_unique' => $this->is_unique,
			'name' => $this->name,
            'price' => $this->price,
            'unit' => $this->unit,
            'min_stock' => $this->min_stock,
            'remark' => $this->remark,
            'is_active' => $this->is_active,
        ];

        // 嚴格權限檢查：非 Owner 不會更新到 cost 欄位
        if (auth()->user()->role === 'owner') {
            $updateData['cost'] = $this->cost;
        }

        $this->product->update($updateData);

        $this->uploadMedia($this->product, $this->new_photos, $this->video);
        
        $this->reset(['new_photos', 'temp_photos', 'video']);
        $this->success('商品資訊與媒體更新成功！', redirectTo: route('products.index'));
    }
	
	/**
	 * 設定現有圖片為首圖
	 */
	public function setPrimary($imageId)
	{
		DB::transaction(function () use ($imageId) {
			// 將該商品所有圖片設為非首圖
			ProductImage::where('product_id', $this->product->id)->update(['is_primary' => false]);
			
			// 設定選定圖片為首圖
			ProductImage::where('id', $imageId)->update(['is_primary' => true]);
		});

		$this->product->load('images');
		$this->success("首圖已更新");
	}

	/**
	 * 設定新上傳的暫存圖片為首圖 (前端邏輯)
	 */
	public function setTempPrimary($index)
	{
		// 在 new_photos 陣列中，我們定義 index 0 為首圖
		if (isset($this->new_photos[$index])) {
			$target = $this->new_photos[$index];
			unset($this->new_photos[$index]);
			
			// 將選中的檔案推到陣列最前面
			array_unshift($this->new_photos, $target);
			
			$this->success("已預設該圖片為首圖");
		}
	}
}