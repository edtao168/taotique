{{-- 
    掃描觸發按鈕元件
    用法：<x-scanner.button :index="$index" mode="single" />
--}}

@props(['index' => null, 'mode' => 'single'])

{{-- 🔧 修正：使用 x-data 和 x-on:click.stop 阻止事件冒泡 --}}
<div class="flex items-center gap-1" 
     x-data 
     x-on:click.stop 
     x-on:mousedown.stop 
     x-on:touchstart.stop>
    
    {{-- 相機掃描按鈕 --}}
    <button type="button" 
            wire:click="openCameraScanner({{ $index ?? 'null' }}, '{{ $mode }}')"
            class="btn btn-ghost btn-sm btn-circle"
            title="相機掃描">
        <x-icon name="o-camera" class="w-5 h-5 text-blue-600" />
    </button>
    
    {{-- 手動輸入按鈕 --}}
    <button type="button" 
            wire:click="openManualInput({{ $index ?? 'null' }}, '{{ $mode }}')"
            class="btn btn-ghost btn-sm btn-circle"
            title="掃碼槍 / 手動輸入">
        <x-icon name="o-pencil" class="w-4 h-4 text-gray-600" />
    </button>
</div>