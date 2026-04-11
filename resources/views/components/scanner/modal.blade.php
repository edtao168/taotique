{{-- 
    掃描器 Modal 元件
    用法：<x-scanner.modal />
--}}

{{-- 相機掃描 Modal --}}
<x-modal wire:model="showCameraScanner" title="相機掃描條碼" separator class="max-w-lg" persistent>
    <div class="space-y-4">
        <div class="text-sm text-gray-600 text-center">
            請將條碼對準相機鏡頭
            {{-- 🔧 新增：環境提示 --}}
            <div class="text-xs text-gray-400 mt-1">
                （需要相機權限，請允許瀏覽器存取）
            </div>
        </div>
        
        {{-- 相機預覽區域 --}}
        <div class="relative bg-black rounded-lg overflow-hidden" style="min-height: 300px;">
            {{-- 🔧 新增：載入中提示 --}}
            <div id="camera-loading" class="absolute inset-0 flex items-center justify-center text-white">
                <span class="loading loading-spinner loading-lg"></span>
                <span class="ml-2">啟動相機中...</span>
            </div>
            
            <div id="interactive" class="viewport" wire:ignore>
				<video id="video" style="width: 100%; height: auto;"></video>
				<canvas id="camera-canvas" class="hidden"></canvas>
			</div>
            
            {{-- 掃描框 --}}
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-20">
                <div class="w-64 h-48 border-2 border-green-400 rounded-lg relative">
                    <div class="absolute top-0 left-0 w-4 h-4 border-t-4 border-l-4 border-green-500 -mt-1 -ml-1"></div>
                    <div class="absolute top-0 right-0 w-4 h-4 border-t-4 border-r-4 border-green-500 -mt-1 -mr-1"></div>
                    <div class="absolute bottom-0 left-0 w-4 h-4 border-b-4 border-l-4 border-green-500 -mb-1 -ml-1"></div>
                    <div class="absolute bottom-0 right-0 w-4 h-4 border-b-4 border-r-4 border-green-500 -mb-1 -mr-1"></div>
                    <div class="absolute top-0 left-0 right-0 h-0.5 bg-green-400 animate-scan"></div>
                </div>
            </div>
            
            {{-- 狀態提示 --}}
            <div id="scan-status" class="absolute bottom-4 left-0 right-0 text-center text-white text-sm bg-black/70 py-2 z-20">
                正在啟動相機...
            </div>
            
            @if($this->scanMode === 'continuous')
            <div class="absolute top-4 right-4 bg-green-500 text-white px-3 py-1 rounded-full text-sm font-bold z-20">
                連續掃描模式
            </div>
            @endif
        </div>
        
        {{-- 手動輸入備用選項 --}}
        <div class="text-center space-y-2">
            <button type="button" class="text-sm text-blue-600 underline" 
                wire:click="$set('showCameraScanner', false); $set('showManualInput', true); $dispatch('focus-manual-input')">
                相機無法使用？點此手動輸入
            </button>
            
            {{-- 🔧 新增：常見問題提示 --}}
            <div class="text-xs text-gray-400">
                提示：請確保使用 HTTPS 連線，並允許相機權限
            </div>
        </div>
    </div>
    
    <x-slot:actions>
        @if($this->scanMode === 'continuous')
            <x-button label="完成掃描" icon="o-check" class="btn-primary" wire:click="closeScanner" />
        @endif
        <x-button label="關閉" @click="$wire.closeScanner(); stopCamera()" />
    </x-slot:actions>
</x-modal>

{{-- 手動輸入 Modal --}}
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
        <x-button label="關閉" @click="$wire.closeScanner()" />
    </x-slot:actions>
</x-modal>

{{-- 樣式 --}}
@if(!app()->bound('scanner-styles-included'))
    @push('styles')
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
    @endpush
    @php(app()->singleton('scanner-styles-included', fn() => true))
@endif