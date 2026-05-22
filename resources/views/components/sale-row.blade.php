{{-- resources/views/components/sale-row.blade.php --}}
@props(['index', 'item', 'warehouses', 'productOptions', 'mode' => 'pc'])

<div wire:key="sale-row-{{ $mode }}-{{ $index }}" class="{{ $mode === 'mobile' ? 'lg:hidden' : 'hidden lg:grid grid-cols-12' }} gap-4 p-3 border-b">
    {{-- 商品選擇區 --}}
    <div class="{{ $mode === 'pc' ? 'col-span-5' : '' }}">
        @if(($item['product_id'] ?? null))
            <div class="flex justify-between border rounded p-2 bg-base-200/50">
                <span class="font-bold">{{ $item['name'] }}</span>
                <x-button icon="o-pencil" class="btn-ghost btn-xs"
                    wire:click="$set('items.{{ $index }}.product_id', null)" />
            </div>
        @else
            <x-choices
				id="{{ $mode }}-product-{{ $index }}"
                name="items[{{ $index }}][product_id]"
                wire:model.live="items.{{ $index }}.product_id"
                :options="$productOptions"
                search-function="search"
                searchable single />
        @endif
    </div>

    {{-- 數值輸入區 --}}
    @if($mode === 'pc')
		<div class="col-span-2">
            <x-select 
                id="pc-wh-{{ $index }}"
                name="items[{{ $index }}][warehouse_id]"
                wire:model.live="items.{{ $index }}.warehouse_id" 
                :options="$warehouses" 
            />
        </div>
        <div class="col-span-2">
            <x-input 
                id="pc-price-{{ $index }}"
                name="items[{{ $index }}][price]"
                wire:model.live.debounce.500ms="items.{{ $index }}.price" 
                class="text-right font-mono" 
            />
        </div>        
        <div class="col-span-1">
            <x-input 
                id="pc-qty-{{ $index }}"
                name="items[{{ $index }}][quantity]"
                type="number" 
                step="0.0001" 
                wire:model.live.debounce.500ms="items.{{ $index }}.quantity" 
                class="text-right font-mono" 
            />
        </div>
        <div class="col-span-2 text-right font-bold text-primary self-center">
            {{ number_format($item['subtotal'] ?? 0, 2) }}
            <x-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:click="removeRow({{ $index }})" />
        </div>
    @else
        {{-- 手機版佈局 --}}
        <div class="mt-3 flex flex-col gap-3">           
            <div class="w-full">
                <x-select 
                    id="mobile-wh-{{ $index }}"
                    label="實際出貨倉庫"
                    name="items[{{ $index }}][warehouse_id]"
                    wire:model.live="items.{{ $index }}.warehouse_id" 
                    :options="$warehouses"
					class="font-mono"
                />
            </div>

            <div class="grid grid-cols-2 gap-2">
                <x-input 
                    id="mobile-price-{{ $index }}"
                    name="items[{{ $index }}][price]"
                    label="單價" 
                    wire:model.live.debounce.500ms="items.{{ $index }}.price" 
                    class="text-right font-mono" 
                />
                <x-input 
                    id="mobile-qty-{{ $index }}"
                    name="items[{{ $index }}][quantity]"
                    label="數量" 
                    type="number" 
                    step="0.0001" 
                    wire:model.live.debounce.500ms="items.{{ $index }}.quantity" 
                    class="text-right font-mono" 
                />
            </div>
        </div>
        <div class="flex justify-between items-center mt-2">            
            <div class="text-primary font-black">小計: {{ number_format($item['subtotal'] ?? 0, 2) }}</div>
            <x-button icon="o-trash" class="btn-error btn-xs" wire:click="removeRow({{ $index }})" />
        </div>
    @endif
</div>