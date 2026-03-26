{{-- 
    掃描器 Modal 元件
    用法：<x-scanner.modal />
    需在 Livewire 元件中使用 HasBarcodeScanner trait
--}}

@php
    // 從 Livewire 元件獲取 scanMode，如果沒有則預設為 'single'
    $scanMode = $scanMode ?? 'single';
@endphp

{{-- 相機掃描 Modal --}}
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
            
            {{-- 連續掃描計數器 --}}
            {{-- 🔧 修正：使用 $scanMode 變數，並提供預設值 --}}
            @if($scanMode === 'continuous')
            <div class="absolute top-4 right-4 bg-green-500 text-white px-3 py-1 rounded-full text-sm font-bold">
                連續掃描模式
            </div>
            @endif
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
        {{-- 🔧 修正：同樣使用 $scanMode 變數 --}}
        @if($scanMode === 'continuous')
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

{{-- 掃描線動畫樣式 --}}
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