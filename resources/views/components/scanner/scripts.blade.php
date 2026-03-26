{{-- 
    掃描器 JavaScript（只需在頁面底部引入一次）
    用法：<x-scanner.scripts />
--}}

<script src="https://unpkg.com/@zxing/library@0.20.0/umd/index.min.js"></script>
<script>
    // 掃描器全域狀態
    window.BarcodeScanner = {
        codeReader: null,
        isScanning: false,
        continuousMode: false,
        lastScanTime: 0,
        scanCooldown: 1500, // 連續掃描冷卻時間（毫秒）
        
        // 啟動相機
        async start(options = {}) {
            const { continuous = false, onScan } = options;
            this.continuousMode = continuous;
            
            const video = document.getElementById('camera-video');
            const status = document.getElementById('scan-status');
            
            if (!video) {
                console.error('找不到 video 元素');
                return;
            }
            
            try {
                this.codeReader = new ZXing.BrowserMultiFormatReader();
                
                // 取得相機設備
                const devices = await this.codeReader.listVideoInputDevices();
                const backCamera = devices.find(d => 
                    d.label.toLowerCase().includes('back') || 
                    d.label.toLowerCase().includes('environment') ||
                    d.label.toLowerCase().includes('後')
                );
                const deviceId = backCamera ? backCamera.deviceId : devices[0]?.deviceId;
                
                if (!deviceId) {
                    throw new Error('找不到相機設備');
                }
                
                status.textContent = continuous ? '連續掃描中...' : '正在掃描...';
                this.isScanning = true;
                
                await this.codeReader.decodeFromVideoDevice(deviceId, video, (result, err) => {
                    if (!this.isScanning) return;
                    
                    if (result) {
                        const barcode = result.text;
                        const now = Date.now();
                        
                        // 連續模式冷卻檢查（避免重複掃描同一條碼）
                        if (this.continuousMode && (now - this.lastScanTime < this.scanCooldown)) {
                            return;
                        }
                        
                        this.lastScanTime = now;
                        status.textContent = '掃描成功: ' + barcode;
                        
                        // 震動反饋
                        if (navigator.vibrate) {
                            navigator.vibrate(200);
                        }
                        
                        // 呼叫回調或發送 Livewire 事件
                        if (onScan) {
                            onScan(barcode);
                        } else {
                            Livewire.dispatch('camera-scan-result', { barcode: barcode });
                        }
                        
                        // 單次模式停止掃描
                        if (!this.continuousMode) {
                            this.stop();
                        }
                    }
                    
                    if (err && !(err instanceof ZXing.NotFoundException)) {
                        console.error(err);
                        status.textContent = '掃描錯誤: ' + err.message;
                    }
                });
                
            } catch (error) {
                console.error('相機啟動失敗:', error);
                if (status) status.textContent = '相機啟動失敗: ' + error.message;
                Livewire.dispatch('camera-failed');
            }
        },
        
        // 停止相機
        stop() {
            this.isScanning = false;
            if (this.codeReader) {
                this.codeReader.reset();
                this.codeReader = null;
            }
            const video = document.getElementById('camera-video');
            if (video && video.srcObject) {
                video.srcObject.getTracks().forEach(track => track.stop());
                video.srcObject = null;
            }
        }
    };

    // Livewire 事件監聽
    document.addEventListener('livewire:initialized', () => {
        // 啟動相機掃描
        Livewire.on('start-camera-scan', () => {
            setTimeout(() => {
                const component = Livewire.find(document.querySelector('[wire\\:id]')?.getAttribute('wire:id'));
                const isContinuous = component?.get('scanMode') === 'continuous';
                BarcodeScanner.start({ continuous: isContinuous });
            }, 300);
        });
        
        // 停止相機掃描
        Livewire.on('stop-camera-scan', () => {
            BarcodeScanner.stop();
        });
        
        // 聚焦手動輸入框
        Livewire.on('focus-manual-input', () => {
            setTimeout(() => {
                const input = document.getElementById('manual-barcode-input');
                if (input) {
                    input.focus();
                    input.select();
                }
            }, 300);
        });
        
        // 相機失敗
        Livewire.on('camera-failed', () => {
            const component = Livewire.find(document.querySelector('[wire\\:id]')?.getAttribute('wire:id'));
            if (component) {
                component.set('showCameraScanner', false);
                component.set('showManualInput', true);
            }
            Livewire.dispatch('focus-manual-input');
        });
    });

    // 頁面離開時清理
    window.addEventListener('beforeunload', () => {
        BarcodeScanner.stop();
    });
    
    // Livewire 導航時清理
    document.addEventListener('livewire:navigating', () => {
        BarcodeScanner.stop();
    });
</script>