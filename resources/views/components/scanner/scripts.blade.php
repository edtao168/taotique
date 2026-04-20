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
			const loading = document.getElementById('camera-loading');
			
			if (!video) {
				console.error('找不到 video 元素');
				return;
			}
			
			try {
				// 檢查基本支援
				if (!navigator.mediaDevices?.getUserMedia) {
					throw new Error('瀏覽器不支援相機 API');
				}
				
				// 檢查 HTTPS（生產環境必需）
				if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
					throw new Error('相機功能需要 HTTPS 安全連線');
				}
				
				this.codeReader = new ZXing.BrowserMultiFormatReader();
				
				// 嘗試取得相機列表
				let devices;
				try {
					// 優先嘗試使用 facingMode: environment (這是標準的後置鏡頭約束)
    let constraints = { 
        video: { 
            facingMode: "environment" 
        } 
    };

				
				// 使用 getUserMedia 直接取得串流（更可靠）
				const stream = await navigator.mediaDevices.getUserMedia(constraints);
				video.srcObject = stream;
				await video.play();
				
				if (loading) loading.classList.add('hidden');
				if (status) status.textContent = continuous ? '連續掃描中...' : '請對準條碼...';
				
				this.isScanning = true;
				
				// 使用 ZXing 解碼視訊串流
				await this.codeReader.decodeFromVideoElement(video, (result, err) => {
					if (!this.isScanning) return;
					
					if (result) {
						const barcode = result.text;
						const now = Date.now();
						
						if (this.continuousMode && (now - this.lastScanTime < this.scanCooldown)) {
							return;
						}
						
						this.lastScanTime = now;
						if (status) status.textContent = '掃描成功: ' + barcode;
						
						if (navigator.vibrate) navigator.vibrate(200);
						
						if (onScan) {
							onScan(barcode);
						} else {
							Livewire.dispatch('camera-scan-result', { barcode: barcode });
						}
						
						if (!this.continuousMode) this.stop();
					}
				});
				
			} catch (error) {
				console.error('相機啟動失敗:', error);
				this.handleCameraError(error, status);
			}
		}

		handleCameraError(error, statusElement) {
			let errorMsg = '相機啟動失敗';
			let autoSwitchToManual = true;
			
			if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
				errorMsg = '相機權限被拒絕，請在瀏覽器設定中允許相機存取';
			} else if (error.name === 'NotFoundError') {
				errorMsg = '找不到相機設備';
			} else if (error.name === 'NotReadableError') {
				errorMsg = '相機被其他應用程式占用';
			} else if (error.message?.includes('HTTPS')) {
				errorMsg = '需要 HTTPS 安全連線';
			} else if (error.name === 'AbortError') {
				errorMsg = '相機啟動被中止';
				autoSwitchToManual = false;
			}
			
			if (statusElement) statusElement.textContent = errorMsg;
			
			// 顯示錯誤提示給使用者
			if (typeof Livewire !== 'undefined') {
				Livewire.dispatch('notify', { 
					type: 'error', 
					message: errorMsg 
				});
			}
			
			if (autoSwitchToManual) {
				setTimeout(() => {
					Livewire.dispatch('camera-failed');
				}, 2000);
			}
		}
        
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