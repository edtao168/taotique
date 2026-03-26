{{-- 
    掃描器 JavaScript（只需在頁面底部引入一次）
    用法：<x-scanner.scripts />
--}}

<script src="https://unpkg.com/@zxing/library@0.20.0/umd/index.min.js"></script>
<script>
    window.BarcodeScanner = {
        codeReader: null,
        isScanning: false,
        continuousMode: false,
        lastScanTime: 0,
        scanCooldown: 1500,
        
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
                // 🔧 新增：先檢查瀏覽器支援
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('瀏覽器不支援相機功能');
                }
                
                // 🔧 新增：檢查是否為 HTTPS 或 localhost
                if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                    throw new Error('相機功能需要 HTTPS 安全連線');
                }
                
                this.codeReader = new ZXing.BrowserMultiFormatReader();
                
                // 取得相機設備
                const devices = await this.codeReader.listVideoInputDevices();
                console.log('可用相機:', devices); // 除錯用
                
                if (!devices || devices.length === 0) {
                    throw new Error('找不到相機設備');
                }
                
                // 優先選擇後置相機
                const backCamera = devices.find(d => 
                    d.label.toLowerCase().includes('back') || 
                    d.label.toLowerCase().includes('environment') ||
                    d.label.toLowerCase().includes('後') ||
                    d.label.toLowerCase().includes('rear')
                );
                
                const deviceId = backCamera ? backCamera.deviceId : devices[0].deviceId;
                console.log('使用相機:', backCamera?.label || devices[0].label); // 除錯用
                
                status.textContent = continuous ? '連續掃描中...' : '請對準條碼...';
                this.isScanning = true;
                
                await this.codeReader.decodeFromVideoDevice(deviceId, video, (result, err) => {
                    if (!this.isScanning) return;
                    
                    if (result) {
                        const barcode = result.text;
                        const now = Date.now();
                        
                        if (this.continuousMode && (now - this.lastScanTime < this.scanCooldown)) {
                            return;
                        }
                        
                        this.lastScanTime = now;
                        status.textContent = '掃描成功: ' + barcode;
                        
                        if (navigator.vibrate) navigator.vibrate(200);
                        
                        if (onScan) {
                            onScan(barcode);
                        } else {
                            Livewire.dispatch('camera-scan-result', { barcode: barcode });
                        }
                        
                        if (!this.continuousMode) this.stop();
                    }
                    
                    if (err && !(err instanceof ZXing.NotFoundException)) {
                        console.error('掃描錯誤:', err);
                        // 不顯示錯誤，因為 NotFoundException 是正常的（還沒對準條碼）
                    }
                });
                
            } catch (error) {
                console.error('相機啟動失敗:', error);
                
                let errorMsg = '相機啟動失敗';
                
                // 🔧 友善的錯誤訊息
                if (error.message.includes('Permission denied') || error.message.includes('拒絕')) {
                    errorMsg = '相機權限被拒絕，請檢查瀏覽器設定';
                } else if (error.message.includes('HTTPS')) {
                    errorMsg = '相機功能需要 HTTPS 安全連線';
                } else if (error.message.includes('不支援')) {
                    errorMsg = '瀏覽器不支援相機功能';
                } else if (error.message.includes('找不到')) {
                    errorMsg = '找不到相機設備';
                } else {
                    errorMsg = '相機啟動失敗: ' + error.message;
                }
                
                if (status) status.textContent = errorMsg;
                
                // 3 秒後自動切換到手動輸入
                setTimeout(() => {
                    Livewire.dispatch('camera-failed');
                }, 3000);
            }
        },
        
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

    document.addEventListener('livewire:initialized', () => {
        Livewire.on('start-camera-scan', () => {
            setTimeout(() => {
                const component = Livewire.find(document.querySelector('[wire\\:id]')?.getAttribute('wire:id'));
                const isContinuous = component?.get('scanMode') === 'continuous';
                BarcodeScanner.start({ continuous: isContinuous });
            }, 500); // 🔧 延長等待時間，確保 Modal 已開啟
        });
        
        Livewire.on('stop-camera-scan', () => BarcodeScanner.stop());
        
        Livewire.on('focus-manual-input', () => {
            setTimeout(() => {
                const input = document.getElementById('manual-barcode-input');
                if (input) {
                    input.focus();
                    input.select();
                }
            }, 300);
        });
        
        Livewire.on('camera-failed', () => {
            const component = Livewire.find(document.querySelector('[wire\\:id]')?.getAttribute('wire:id'));
            if (component) {
                component.set('showCameraScanner', false);
                component.set('showManualInput', true);
            }
            Livewire.dispatch('focus-manual-input');
        });
    });

    window.addEventListener('beforeunload', () => BarcodeScanner.stop());
    document.addEventListener('livewire:navigating', () => BarcodeScanner.stop());
</script>