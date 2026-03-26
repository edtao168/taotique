{{-- 
    掃描按鈕元件
    用法：<x-scanner.button :index="$index" mode="single" />
    參數：
    - index: 行索引（可選）
    - mode: 'single'（單次）或 'continuous'（連續）
--}}

@props(['index' => null, 'mode' => 'single'])

<div class="dropdown dropdown-end">
    <label tabindex="0" class="btn btn-ghost btn-sm btn-circle">
        <x-icon name="o-qr-code" class="w-5 h-5" />
    </label>
    <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
        <li>
            <a wire:click="openCameraScanner({{ $index ? "'$index'" : 'null' }}, '{{ $mode }}')">
                <x-icon name="o-camera" class="w-4 h-4" />
                相機掃描
            </a>
        </li>
        <li>
            <a wire:click="openManualInput({{ $index ? "'$index'" : 'null' }}, '{{ $mode }}')">
                <x-icon name="o-pencil" class="w-4 h-4" />
                掃碼槍 / 手動輸入
            </a>
        </li>
    </ul>
</div>