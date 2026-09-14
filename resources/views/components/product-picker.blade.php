{{-- resources/views/components/product-picker.blade.php --}}
@props([
    // ── 索引模式（新增，銷售/採購多行明細用） ──
    'index' => null,

    // ── 共用 ──
    'options' => [],
    'selectedName' => null,
    'label' => '選擇商品',
    'placeholder' => '輸入 SKU 或名稱搜尋...',

    // ── 單一模式（舊，庫存模塊等單商品場景用） ──
    'searchProperty' => 'productSearch',
    'selectMethod' => 'fillProduct',
    'clearMethod' => 'resetProduct',
])

@php
    $useIndexMode = !is_null($index);

    // 索引模式強制用 productSearch（對接 HasMultiProductPicker 邏輯）
    // 單一模式沿用傳入的 searchProperty（庫存模塊為 productSearch）
    $activeSearchProperty = $useIndexMode ? 'productSearch' : $searchProperty;
    $searchValue = $this->{$activeSearchProperty} ?? '';

    // 索引模式：只有當前聚焦行顯示下拉
    // 單一模式：永遠顯示
    $isActive = $useIndexMode
        ? (($this->activeRowIndex ?? null) === $index)
        : true;
@endphp

<div class="w-full">
    @if($label)
        <label class="label font-semibold text-xs text-base-content/80 p-0 mb-2.5">{{ $label }}</label>
    @endif

    <div class="relative" x-data="{ open: false }">
        @if($selectedName)
            {{-- 已選擇狀態：維持原本的 <x-input> 外觀 --}}
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
                        wire:click="{{ $useIndexMode
                            ? 'resetProductForRow('.$index.')'
                            : $clearMethod }}"
                    />
                </x-slot:append>
            </x-input>
        @else
            {{-- 未選擇狀態：輸入與搜尋 --}}
            <x-input
                wire:model.live.debounce.300ms="{{ $activeSearchProperty }}"
                placeholder="{{ $placeholder }}"
                icon="o-magnifying-glass"
                clearable
                @focus="open = true; {{ $useIndexMode ? '$wire.setActiveRow('.$index.')' : '' }}"
                @click.outside="open = false"
            >
                <x-slot:append>
                    <div wire:loading wire:target="{{ $activeSearchProperty }}" class="flex items-center pr-3">
                        <span class="loading loading-spinner loading-xs text-primary"></span>
                    </div>
                </x-slot:append>
            </x-input>

            {{-- 下拉選單：索引模式需 $isActive 才顯示 --}}
            @if($isActive && !empty($options))
                <div
                    x-show="open"
                    x-cloak
                    class="absolute z-50 w-full mt-1 bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                >
                    @foreach($options as $option)
                        <div
                            wire:key="product-option-{{ $index ?? 'single' }}-{{ $option['id'] }}"
                            wire:click="{{ $useIndexMode
                                ? 'fillProductForRow('.$index.', '.$option['id'].')'
                                : $selectMethod.'('.$option['id'].')' }}"
                            @click="open = false"
                            class="px-3 py-2 hover:bg-base-200 cursor-pointer text-sm border-b border-base-200 last:border-b-0 flex items-center justify-between"
                        >
                            <span class="truncate">{{ $option['name'] }}</span>
                        </div>
                    @endforeach
                </div>
            @elseif($isActive)
                {{-- 下拉選單空狀態 --}}
                <div x-show="open" x-cloak
                     class="absolute z-50 w-full mt-1 bg-base-100 border border-base-300 rounded-lg shadow-lg p-3 text-sm text-base-content/60">
                    <div wire:loading wire:target="{{ $activeSearchProperty }}" class="flex items-center gap-2">
                        <span class="loading loading-spinner loading-xs text-primary"></span>
                        <span>搜尋中...</span>
                    </div>
                    <div wire:loading.remove wire:target="{{ $activeSearchProperty }}">
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