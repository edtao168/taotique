{{-- resources/views/components/product-picker.blade.php --}}
@props([
    'name',                   // wire:model 綁定的屬性名稱
    'options' => [],          // 商品選項陣列
    'selectedName' => null,   // 已選商品的顯示名稱
    'label' => '選擇商品',
    'placeholder' => '輸入 SKU 或名稱搜尋...',
    'searchProperty' => 'productSearch',
    'selectMethod' => 'selectProduct',   // 選擇商品時呼叫的方法
    'clearMethod' => 'clearProduct',     // 清除商品時呼叫的方法
])

@php
    $searchValue = $this->{$searchProperty} ?? '';
@endphp

<div class="w-full">
    @if($label)
        <label class="label font-semibold text-xs text-base-content/80 p-0 mb-2.5">{{ $label }}</label>
    @endif

    <div class="relative" x-data="{ open: false }">
        @if($selectedName)
            {{-- 已選擇狀態：樣式完全維持初始 <x-input> 外觀 --}}
            <x-input
                value="{{ $selectedName }}"
                readonly
                icon="o-magnifying-glass"
                class="cursor-default"
            >
                <x-slot:append>
                    <x-button 
                        icon="o-x-mark" 
                        class="btn-ghost btn-xs text-base-content/60 hover:text-error my-auto mr-1" 
                        wire:click="{{ $clearMethod }}"
                    />
                </x-slot:append>
            </x-input>
        @else
            {{-- 未選擇狀態：輸入與搜尋 --}}
            <x-input
                wire:model.live.debounce.300ms="{{ $searchProperty }}"
                placeholder="{{ $placeholder }}"
                icon="o-magnifying-glass"
                clearable
                @focus="open = true"
                @click.outside="open = false"
            >
                {{-- 右側輸入框載入指示器 --}}
                <x-slot:append>
                    <div wire:loading wire:target="{{ $searchProperty }}" class="flex items-center pr-3">
                        <span class="loading loading-spinner loading-xs text-primary"></span>
                    </div>
                </x-slot:append>
            </x-input>

            {{-- 下拉選單區域 --}}
            @if(!empty($options))
                <div 
                    x-show="open" 
                    x-cloak
                    class="absolute z-50 w-full mt-1 bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                >
                    @foreach($options as $option)
                        <div 
                            wire:key="product-option-{{ $option['id'] }}"
                            wire:click="{{ $selectMethod }}({{ $option['id'] }})"
                            @click="open = false"
                            class="px-3 py-2 hover:bg-base-200 cursor-pointer text-sm border-b border-base-200 last:border-b-0 flex items-center justify-between"
                        >
                            <span class="truncate">{{ $option['name'] }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                {{-- 下拉選單空狀態 --}}
                <div x-show="open" x-cloak class="absolute z-50 w-full mt-1 bg-base-100 border border-base-300 rounded-lg shadow-lg p-3 text-sm text-base-content/60">
                    <div wire:loading wire:target="{{ $searchProperty }}" class="flex items-center gap-2">
                        <span class="loading loading-spinner loading-xs text-primary"></span>
                        <span>搜尋中...</span>
                    </div>
                    <div wire:loading.remove wire:target="{{ $searchProperty }}">
                        @if(strlen($searchValue) > 0)
                            找不到符合的商品
                        @else
                            請輸入 SKU 或品名搜尋
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>