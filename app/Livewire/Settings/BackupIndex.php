<?php // [本地操作] app/Livewire/Settings/BackupIndex.php

namespace App\Livewire\Settings;


use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Mary\Traits\Toast;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupIndex extends Component
{
    use Toast;
	
	public string $storagePath;
    public string $disk;

    public function mount()
    {
        // 統一由 config 讀取真相來源
        $this->disk = config('business.backup.disk', 'local');
        $this->storagePath = rtrim(config('business.backup.path'), '/') . '/';
    }

	// 如果備份資料夾需要根據店鋪隔離，手動獲取 shop_id
    public function getShopId()
    {
        return auth()->user()->shop_id ?? 1;
    }

    
	/**
     * 執行備份指令
     */
    public function runBackup()
    {        
		try {
            Artisan::call('backup:run');
            // 強制 Livewire 重新計算 backups 屬性
            unset($this->backups);
            $this->success('備份完成', '新的備份檔已產生。');
        } catch (\Exception $e) {
            $this->error('備份失敗', $e->getMessage());
        }
    }
	
    /**
     * 獲取備份檔案列表
     */
    public function getBackupsProperty(): array
    {        
        if (!Storage::disk($this->disk)->exists($this->storagePath)) {
            return [];
        }

        // 使用 files() 獲取檔案
        $files = Storage::disk($this->disk)->files($this->storagePath);
        
        return collect($files)
            ->filter(fn($path) => str_ends_with($path, '.zip')) // 只顯示壓縮檔
            ->map(fn($path) => [
                'name' => basename($path),
                'size' => round(Storage::disk($this->disk)->size($path) / 1024 / 1024, 2) . ' MB',
                'last_modified' => date('Y-m-d H:i:s', Storage::disk($this->disk)->lastModified($path)),
            ])
            ->sortByDesc('last_modified')
            ->toArray();
    }

    public function download($fileName): StreamedResponse
    {
        $path = $this->storagePath . $fileName;

        if (!Storage::disk($this->disk)->exists($path)) {
            $this->error('下載失敗', '找不到檔案：' . $path);
            abort(404);
        }

        return Storage::disk($this->disk)->download($path, $fileName);
    }

    public function render()
    {
        return view('livewire.settings.backup-index', [
            'backups' => $this->backups
        ]);
    }
}