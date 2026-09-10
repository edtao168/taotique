{{-- resources/views/components/product-picker.blade.php --}}
@props([
    'name',                   // wire:model 綁定的屬性名稱，如 "product_id"
    'options' => [],          // 商品選項陣列
    'selectedName' => null,   // 已選商品的顯示名稱 (SKU + 名稱)
    'label' => '選擇商品',
    'placeholder' => '輸入 SKU 或名稱搜尋...',
    'searchProperty' => 'productSearch',  // 綁定搜尋關鍵字的屬性名稱
])

<div class="w-full">
    @if($label)
        <label class="label font-semibold text-xs text-base-content/80 p-0 mb-2.5">{{ $label }}</label>
    @endif

    @if($selectedName)
        {{-- 已選擇狀態：顯示 SKU + 名稱，並附重置按鈕 --}}
        <div class="flex items-center justify-between p-2 border border-base-300 rounded-lg bg-base-100 shadow-sm h-[38px]">
            <span class="font-bold text-sm truncate">{{ $selectedName }}</span>
            <x-button 
                icon="o-backspace" 
                class="btn-ghost btn-xs text-primary" 
                wire:click="$set('{{ $name }}', null)" 
            />
        </div>
    @else
        {{-- 未選擇狀態：輸入框 + 下拉清單 --}}
        <div class="relative" x-data="{ open: false }">
            <x-input
                wire:model.live.debounce.300ms="{{ $searchProperty }}"
                placeholder="{{ $placeholder }}"
                icon="o-magnifying-glass"
                clearable
                @focus="open = true"
                @click.outside="open = false"
            />

            @if(!empty($options))
                <div 
                    x-show="open" 
                    x-cloak
                    class="absolute z-50 w-full mt-1 bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                >
                    @foreach($options as $option)
                        <div 
                            wire:key="product-option-{{ $option['id'] }}"
                            wire:click="$set('{{ $name }}', {{ $option['id'] }})"
                            @click="open = false"
                            class="px-3 py-2 hover:bg-base-200 cursor-pointer text-sm border-b border-base-200 last:border-b-0"
                        >
                            {{ $option['name'] }}
                        </div>
                    @endforeach
                </div>
            @endif

            @if(empty($options) && strlen($searchProperty ?? '') > 0)
                <div class="absolute z-50 w-full mt-1 bg-base-100 border border-base-300 rounded-lg shadow-lg p-3 text-sm text-base-content/60">
                    找不到符合的商品
                </div>
            @endif
        </div>
    @endif
</div>