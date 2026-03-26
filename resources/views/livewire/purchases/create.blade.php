{{-- 檔案路徑：resources/views/livewire/purchases/create.blade.php --}}
<div>
    <x-header title="新增採購單" separator progress-indicator>
        <x-slot:actions>
            <x-button label="返回列表" icon="o-arrow-left" link="/purchases" />
            <x-button label="儲存入庫" icon="o-check" class="btn-primary" wire:click="save" spinner />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {{-- 左側：主表資訊 --}}
        <div class="lg:col-span-1">
            <x-card title="基本資訊" shadow separator>
                <x-select label="供應商" icon="o-user" :options="$suppliers" wire:model="supplier_id" placeholder="請選擇" />
                <x-datetime label="採購日期" wire:model="purchased_at" icon="o-calendar" class="mt-4" />
                <x-input label="匯率 (TWD)" wire:model.live="exchange_rate" icon="o-currency-dollar" class="mt-4" />
                <x-input label="備註" wire:model.live="remark" icon="o-pencil-square" class="mt-4" />
            </x-card>
        </div>

        {{-- 右側：採購明細 --}}
        <div class="lg:col-span-3">
            <x-card title="商品明細" shadow separator>
                <div class="hidden lg:grid grid-cols-12 gap-4 mb-2 px-4 text-sm font-bold opacity-60">
                    <div class="col-span-6">商品選擇與確認</div>
                    <div class="col-span-2">數量</div>
                    <div class="col-span-2 text-right">外幣單價</div>
                    <div class="col-span-2 text-right">TWD 預估</div>
                </div>

                <div class="space-y-3">
                    @foreach($items as $index => $item)
                        <div wire:key="purchase-row-{{ $index }}" class="p-4 border rounded-xl bg-base-50 relative">
                            {{-- 刪除按鈕 --}}
                            <x-button icon="o-trash" class="btn-error btn-xs absolute -top-2 -right-2 rounded-full" 
                                wire:click="removeRow({{ $index }})" />

                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-center">
                                {{-- 1. 商品搜尋 (佔 6/12) --}}
                                <div class="lg:col-span-6">
                                    <x-choices 
                                        wire:model.live="items.{{ $index }}.product_id" 
                                        :options="$productOptions"
                                        search-function="search"
                                        option-label="name"
                                        option-sub-label="sku"
                                        searchable
                                        single
                                        debounce="300ms"
                                        placeholder="輸入 SKU 或 商品名稱 搜尋..."
                                    >
                                        {{-- 🔧 掃描功能下拉選單 --}}
                                        <x-slot:append>
                                            <div class="dropdown dropdown-end">
                                                <label tabindex="0" class="btn btn-ghost btn-sm btn-circle">
                                                    <x-icon name="o-qr-code" class="w-5 h-5" />
                                                </label>
                                                <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                                    <li>
                                                        <a wire:click="openCameraScanner({{ $index }})">
                                                            <x-icon name="o-camera" class="w-4 h-4" />
                                                            相機掃描
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a wire:click="openManualInput({{ $index }})">
                                                            <x-icon name="o-keyboard" class="w-4 h-4" />
                                                            掃碼槍 / 手動輸入
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </x-slot:append>
                                    </x-choices>
                                    
                                    @if($items[$index]['product_id'])
                                        @php
                                            $selectedProduct = collect($productOptions)->firstWhere('id', $items[$index]['product_id']);
                                        @endphp
                                        @if($selectedProduct)
                                            <div class="text-xs text-gray-500 mt-1">
                                                SKU: {{ $selectedProduct['sku'] ?? 'N/A' }}
                                            </div>
                                        @endif
                                    @endif
                                </div>

                                {{-- 2. 數量 (佔 2/12) --}}
                                <div class="lg:col-span-2">
                                    <x-input type="number" wire:model.live="items.{{ $index }}.quantity" class="text-center" />
                                </div>

                                {{-- 3. 外幣單價 (佔 2/12) --}}
                                <div class="lg:col-span-2">
                                    <x-input wire:model.live="items.{{ $index }}.foreign_price" class="text-right" />
                                </div>

                                {{-- 4. TWD 預估 (佔 2/12) --}}
                                <div class="lg:col-span-2 text-right">
                                    <span class="font-mono font-bold text-blue-600">
                                        {{ number_format(bcmul($items[$index]['foreign_price'] ?? 0, $exchange_rate ?? 0, 4), 2) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <x-slot:actions>
                    <x-button label="追加商品列" icon="o-plus" class="btn-outline btn-sm w-full" wire:click="addRow" />
                </x-slot:actions>
            </x-card>
        </div>
    </div>

    {{-- 🔧 相機掃描 Modal --}}
    <x-modal wire:model="showCameraScanner" title="相機掃描條碼" separator class="max-w-lg">
        <div class="space-y-4">
            <div class="text-sm text-gray-600 text-center">
                請將條碼對準相機鏡頭
            </div>
            
            {{-- 相機預覽區域 --}}
            <div class="relative bg-black rounded-lg overflow-hidden" style="min-height: 300px;">
                <video id="camera-video" class="w-full h-auto" playsinline></video>
                <canvas id="camera-canvas" class="hidden"></canvas>
                
                {{-- 掃描框 --}}
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div class="w-64 h-48 border-2 border-green-400 rounded-lg relative">
                        <div class="absolute top-0 left-0 w-4 h-4 border-t-4 border-l-4 border-green-500 -mt-1 -ml-1"></div>
                        <div class="absolute top-0 right-0 w-4 h-4 border-t-4 border-r-4 border-green-500 -mt-1 -mr-1"></div>
                        <div class="absolute bottom-0 left-0 w-4 h-4 border-b-4 border-l-4 border-green-500 -mb-1 -ml-1"></div>
                        <div class="absolute bottom-0 right-0 w-4 h-4 border-b-4 border-r-4 border-green-500 -mb-1 -mr-1"></div>
                        {{-- 掃描線動畫 --}}
                        <div class="absolute top-0 left-0 right-0 h-0.5 bg-green-400 animate-scan"></div>
                    </div>
                </div>
                
                {{-- 狀態提示 --}}
                <div id="scan-status" class="absolute bottom-4 left-0 right-0 text-center text-white text-sm bg-black/50 py-2">
                    正在啟動相機...
                </div>
            </div>
            
            {{-- 手動輸入備用選項 --}}
            <div class="text-center">
                <button type="button" class="text-sm text-blue-600 underline" 
                    wire:click="$set('showCameraScanner', false); $set('showManualInput', true); $dispatch('focus-manual-input')">
                    相機無法使用？點此手動輸入
                </button>
            </div>
        </div>
        
        <x-slot:actions>
            <x-button label="關閉" @click="$wire.showCameraScanner = false; stopCamera()" />
        </x-slot:actions>
    </x-modal>

    {{-- 🔧 手動輸入 Modal（掃碼槍/鍵盤） --}}
    <x-modal wire:model="showManualInput" title="手動輸入條碼" separator>
        <div class="space-y-4">
            <div class="text-sm text-gray-600">
                請使用掃描槍掃描，或手動輸入 SKU
            </div>
            
            <x-input 
                id="manual-barcode-input"
                placeholder="請掃描或輸入條碼..." 
                wire:model="scannedBarcode"
                wire:keydown.enter="handleManualInput"
                class="font-mono text-lg"
            />
            
            <x-button 
                label="確認" 
                icon="o-check" 
                class="btn-primary w-full" 
                wire:click="handleManualInput"
                :disabled="empty($scannedBarcode)"
            />
        </div>
        
        <x-slot:actions>
            <x-button label="關閉" @click="$wire.showManualInput = false; $wire.scannedBarcode = ''" />
        </x-slot:actions>
    </x-modal>

    {{-- 🔧 相機掃描 JavaScript --}}
    <script src="https://unpkg.com/@zxing/library@0.20.0/umd/index.min.js"></script>
    <script>
        let codeReader = null;
        let isScanning = false;

        // 啟動相機掃描
        async function startCamera() {
            const video = document.getElementById('camera-video');
            const status = document.getElementById('scan-status');
            
            try {
                codeReader = new ZXing.BrowserMultiFormatReader();
                
                // 嘗試使用後置相機（手機）
                const devices = await codeReader.listVideoInputDevices();
                const backCamera = devices.find(d => d.label.toLowerCase().includes('back') || d.label.toLowerCase().includes('後'));
                const deviceId = backCamera ? backCamera.deviceId : devices[0]?.deviceId;
                
                if (!deviceId) {
                    throw new Error('找不到相機設備');
                }
                
                status.textContent = '正在掃描...';
                isScanning = true;
                
                await codeReader.decodeFromVideoDevice(deviceId, video, (result, err) => {
                    if (result && isScanning) {
                        isScanning = false;
                        const barcode = result.text;
                        status.textContent = '掃描成功: ' + barcode;
                        
                        // 震動反饋（手機）
                        if (navigator.vibrate) {
                            navigator.vibrate(200);
                        }
                        
                        // 傳回 Livewire
                        Livewire.dispatch('barcode-scanned', { barcode: barcode });
                        
                        // 停止相機
                        stopCamera();
                    }
                    if (err && !(err instanceof ZXing.NotFoundException)) {
                        console.error(err);
                        status.textContent = '掃描錯誤: ' + err.message;
                    }
                });
                
            } catch (error) {
                console.error('相機啟動失敗:', error);
                status.textContent = '相機啟動失敗: ' + error.message;
                
                // 自動切換到手動輸入
                setTimeout(() => {
                    Livewire.dispatch('camera-failed');
                }, 2000);
            }
        }

        // 停止相機
        function stopCamera() {
            isScanning = false;
            if (codeReader) {
                codeReader.reset();
                codeReader = null;
            }
            const video = document.getElementById('camera-video');
            if (video && video.srcObject) {
                video.srcObject.getTracks().forEach(track => track.stop());
                video.srcObject = null;
            }
        }

        // Livewire 事件監聽
        document.addEventListener('livewire:initialized', () => {
            // 啟動相機掃描
            Livewire.on('start-camera-scan', () => {
                setTimeout(startCamera, 300); // 等待 Modal 開啟
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
            
            // 相機失敗，切換到手動輸入
            Livewire.on('camera-failed', () => {
                @this.showCameraScanner = false;
                @this.showManualInput = true;
                Livewire.dispatch('focus-manual-input');
            });
            
            // 條碼掃描成功，傳給後端處理
            Livewire.on('barcode-scanned', ({ barcode }) => {
                @this.handleCameraScan(barcode);
            });
        });

        // 頁面離開時停止相機
        window.addEventListener('beforeunload', stopCamera);
    </script>

    {{-- 🔧 掃描線動畫樣式 --}}
    <style>
        @keyframes scan {
            0% { transform: translateY(0); opacity: 1; }
            50% { transform: translateY(192px); opacity: 1; }
            100% { transform: translateY(0); opacity: 1; }
        }
        .animate-scan {
            animation: scan 2s linear infinite;
        }
    </style>
</div>