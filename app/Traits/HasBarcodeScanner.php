<?php

namespace App\Traits;

use Livewire\Attributes\On;
use App\Models\Product;

trait HasBarcodeScanner
{
    // 掃描相關屬性
    public bool $showCameraScanner = false;
    public bool $showManualInput = false;
    public string $scannedBarcode = '';
    public ?int $currentScanIndex = null;
    public string $scanMode = 'single'; // 'single' | 'continuous' 單次掃描或連續掃描
    
    // 掃描結果回調（由使用此 Trait 的類別實現）
    abstract public function onBarcodeScanned(string $barcode, ?int $index = null): void;
    
    // 驗證條碼是否有效（可覆寫）
    public function validateBarcode(string $barcode): bool
    {
        return strlen($barcode) >= 3;
    }
    
    // 根據條碼查找商品（可覆寫）
    public function findProductByBarcode(string $barcode): ?Product
    {
        return Product::where('sku', $barcode)
            ->where('is_active', true)
            ->first();
    }

    /**
     * 開啟相機掃描
     */
    public function openCameraScanner(?int $index = null, string $mode = 'single'): void
    {
        $this->currentScanIndex = $index;
        $this->scanMode = $mode;
        $this->showCameraScanner = true;
        $this->dispatch('start-camera-scan');
    }

    /**
     * 開啟手動輸入
     */
    public function openManualInput(?int $index = null, string $mode = 'single'): void
    {
        $this->currentScanIndex = $index;
        $this->scanMode = $mode;
        $this->showManualInput = true;
        $this->scannedBarcode = '';
        $this->dispatch('focus-manual-input');
    }

    /**
     * 處理相機掃描結果（由 JS 呼叫）
     */
    #[On('camera-scan-result')]
    public function handleCameraScan(string $barcode): void
    {
        $this->processScannedBarcode($barcode);
        
        if ($this->scanMode === 'single') {
            $this->showCameraScanner = false;
        }
        // 連續模式下保持相機開啟，JS 會繼續掃描
    }

    /**
     * 處理手動輸入
     */
    public function handleManualInput(): void
    {
        $barcode = trim($this->scannedBarcode);
        
        if (empty($barcode)) {
            $this->dispatch('notify', type: 'error', message: '請輸入條碼');
            return;
        }
        
        if (!$this->validateBarcode($barcode)) {
            $this->dispatch('notify', type: 'error', message: '條碼格式無效');
            $this->scannedBarcode = '';
            return;
        }
        
        $this->processScannedBarcode($barcode);
        $this->showManualInput = false;
        $this->scannedBarcode = '';
    }

    /**
     * 統一處理條碼邏輯
     */
    protected function processScannedBarcode(string $barcode): void
    {
        if (!$this->validateBarcode($barcode)) {
            $this->dispatch('notify', type: 'error', message: '條碼格式無效: ' . $barcode);
            return;
        }
        
        // 呼叫回調方法讓具體業務邏輯處理
        $this->onBarcodeScanned($barcode, $this->currentScanIndex);
        
        // 單次掃描模式重置索引
        if ($this->scanMode === 'single') {
            $this->currentScanIndex = null;
        }
    }

    /**
     * 關閉掃描器
     */
    public function closeScanner(): void
    {
        $this->showCameraScanner = false;
        $this->showManualInput = false;
        $this->scannedBarcode = '';
        $this->currentScanIndex = null;
        $this->dispatch('stop-camera-scan');
    }
    
    /**
     * 重置掃描狀態
     */
    public function resetScanner(): void
    {
        $this->scannedBarcode = '';
        $this->currentScanIndex = null;
    }
}