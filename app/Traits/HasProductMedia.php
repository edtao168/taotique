<?php // 檔案路徑：app/Traits/HasProductMedia.php

namespace App\Traits;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

trait HasProductMedia
{
    /**
     * 統一處理圖片與影片上傳邏輯
     */
    public function uploadMedia(Product $product, $photos = [])
    {
        if ($photos) {
            foreach ($photos as $photo) {
                $path = $photo->store('products/photos', 'public');
                
                // 檢查是否已有首圖，若無，則將此張設為首圖
                $isFirst = !$product->images()->where('is_primary', true)->exists();

                $product->images()->create([
                    'path' => $path,
                    'is_primary' => $isFirst
                ]);
            }
        }
    }

    /**
     * 統一處理刪除邏輯
     */
    public function deleteMedia(ProductImage $image)
    {
        // 1. 刪除實體檔案
        if (Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }

        // 2. 刪除資料庫記錄
        $image->delete();
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