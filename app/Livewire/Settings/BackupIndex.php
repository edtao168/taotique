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
        $this->storagePath = config('business.backup.path', 'taotique-backup/taotique-backup/');
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
        $this->disk = config('business.backup.disk');
		$this->storagePath = config('business.backup.path');
		
		try {
            // 執行指令，這會觸發 spatie/laravel-backup (或其他備份套件)
            // --only-db 參數通常用於只備份資料庫，視你的需求調整
            Artisan::call('backup:run');

            // 清除 computed property 快取，讓列表即時更新
            unset($this->backups);

            $this->success('備份完成', '新的備份檔已成功產生並儲存。');
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

        $files = Storage::disk($this->disk)->files($this->storagePath);
        
        return collect($files)
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
            abort(404, "檔案不存在");
        }

        return Storage::disk($this->disk)->download($path);
    }

    public function render()
    {
        return view('livewire.settings.backup-index', [
            'backups' => $this->backups
        ]);
    }
}