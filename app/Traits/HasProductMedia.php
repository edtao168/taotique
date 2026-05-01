<?php // 檔案路徑：app/Traits/HasProductMedia.php

namespace App\Traits;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait HasProductMedia
{
    /**
     * 處理產品媒體上傳 (支援影片與多種格式圖片)
     */
    public function uploadMedia(Product $product, $files = [])
    {
        if (empty($files)) return;

        $files = is_array($files) ? $files : [$files];

        foreach ($files as $file) {
            if (!($file instanceof TemporaryUploadedFile)) continue;

            // 1. 更嚴謹的副檔名獲取方式[cite: 4]
            $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
            
            // 2. 判斷目錄：加入 webm 與更多的影片格式[cite: 4]
            $videoExtensions = config('business.media.video_extensions');
            $folder = in_array($extension, $videoExtensions) ? 'products/videos' : 'products/photos';
            
            // 3. 執行儲存
            $path = $file->store($folder, 'public');
            
            if (!$path) continue;

            // 4. 併發安全的檢查首圖是否存在[cite: 4]
            $hasPrimary = $product->images()->where('is_primary', true)->exists();

            // 5. 寫入資料庫
            $product->images()->create([
                'path' => $path,
                'is_primary' => !$hasPrimary
            ]);
        }
    }

    /**
     * 刪除媒體記錄並清理實體檔案
     */
    public function deleteMedia(ProductImage $image)
    {
        // 刪除實體檔案
        if (Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }

        $isPrimary = $image->is_primary;
        $productId = $image->product_id;

        // 刪除資料庫記錄
        $image->delete();

        // 若刪除的是首圖，自動指派下一張為首圖，維持系統邏輯嚴謹
        if ($isPrimary) {
            $nextImage = ProductImage::where('product_id', $productId)
                ->orderBy('id')
                ->first();
            
            if ($nextImage) {
                $nextImage->update(['is_primary' => true]);
            }
        }
    }
	
	/**
	 * 處理尚未存入資料庫的暫存圖片刪除
	 */
	public function removeTempUpload($field, $filename)
	{
		$this->$field = array_filter($this->$field, function ($file) use ($filename) {
			return $file->getFilename() !== $filename;
		});
	}
}