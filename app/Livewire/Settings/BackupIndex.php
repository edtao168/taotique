<?php // [本地操作] app/Livewire/Settings/BackupIndex.php

namespace App\Livewire\Settings;

use Livewire\Component;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
// 移除 use App\Traits\HasShop; <-- 不要在此處使用 Model 專用的 Trait

class BackupIndex extends Component
{
    // 如果備份資料夾需要根據店鋪隔離，手動獲取 shop_id
    public function getShopId()
    {
        return auth()->user()->shop_id ?? 1;
    }

    public string $storagePath = "陶老闆IMS/陶老闆IMS/";

    /**
     * 獲取備份檔案列表
     */
    public function getBackupsProperty(): array
    {
        // 假設未來備份路徑會依 shop_id 分隔，可改為：
        // $path = $this->storagePath . $this->getShopId() . "/";
        $path = $this->storagePath;

        if (!Storage::disk('local')->exists($path)) {
            return [];
        }

        $files = Storage::disk('local')->files($path);
        
        return collect($files)
            ->map(fn($path) => [
                'name' => basename($path),
                'size' => round(Storage::disk('local')->size($path) / 1024 / 1024, 2) . ' MB',
                'last_modified' => date('Y-m-d H:i:s', Storage::disk('local')->lastModified($path)),
            ])
            ->sortByDesc('last_modified')
            ->toArray();
    }

    public function download($fileName): StreamedResponse
    {
        $path = $this->storagePath . $fileName;

        if (!Storage::disk('local')->exists($path)) {
            abort(404, "檔案不存在");
        }

        return Storage::disk('local')->download($path);
    }

    public function render()
    {
        return view('livewire.settings.backup-index', [
            'backups' => $this->backups
        ]);
    }
}