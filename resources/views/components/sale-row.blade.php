{{-- resources/views/components/sale-row.blade.php --}}
@props(['index', 'item', 'warehouses', 'productOptions'])

<div
    wire:key="sale-row-{{ $index }}-{{ $item['product_id'] ?? 'new' }}"
    class="grid grid-cols-1 lg:grid-cols-12 gap-2 lg:gap-4 
           p-3 lg:p-0 
           bg-base-100 lg:bg-transparent 
           rounded-lg lg:rounded-none 
           border border-base-200 lg:border-0 
           lg:border-b lg:border-base-200
           items-start lg:items-center"
>
    {{-- 商品欄位：手機全寬，桌面 5 欄 --}}
    <div class="col-span-1 lg:col-span-5">
        <label class="label lg:hidden text-xs font-bold opacity-60 p-0 mb-1">商品</label>
        <x-product-picker
            :index="$index"
            :options="$productOptions"
            :selected-name="$item['name'] ?? null"
            label=""
            placeholder="輸入 SKU 或名稱搜尋..."
        />
    </div>

    {{-- 發貨倉庫：手機全寬，桌面 2 欄 --}}
    <div class="col-span-1 lg:col-span-2">
        <label class="label lg:hidden text-xs font-bold opacity-60 p-0 mb-1">發貨倉庫</label>
        <x-select
            id="wh-{{ $index }}"
            name="items[{{ $index }}][warehouse_id]"
            wire:model.live="items.{{ $index }}.warehouse_id"
            :options="$warehouses"
            placeholder="請選擇倉庫"
            class="select-sm"
        />
    </div>

    {{-- 單價：手機 1/2 寬，桌面 2 欄 --}}
    <div class="col-span-1 lg:col-span-2">
        <label class="label lg:hidden text-xs font-bold opacity-60 p-0 mb-1">單價</label>
        <x-input
            id="price-{{ $index }}"
            name="items[{{ $index }}][price]"
            wire:model.live.debounce.500ms="items.{{ $index }}.price"
            class="text-right font-mono input-sm"
            inputmode="decimal"
            step="0.01"
        />
    </div>

    {{-- 數量：手機 1/2 寬，桌面 1 欄 --}}
    <div class="col-span-1 lg:col-span-1">
        <label class="label lg:hidden text-xs font-bold opacity-60 p-0 mb-1">數量</label>
        <x-input
            id="qty-{{ $index }}"
            name="items[{{ $index }}][quantity]"
            type="number"
            step="0.0001"
            wire:model.live.debounce.500ms="items.{{ $index }}.quantity"
            class="text-right font-mono input-sm"
        />
    </div>

    {{-- 小計 + 刪除：手機全寬，桌面 2 欄 --}}
    <div class="col-span-1 lg:col-span-2 flex items-center justify-between lg:justify-end gap-2">
        <label class="label lg:hidden text-xs font-bold opacity-60 p-0 mb-1">小計</label>
        <div class="flex items-center gap-2">
            <span class="font-mono font-bold text-primary">
                {{ number_format((float) ($item['subtotal'] ?? 0), 2) }}
            </span>
            <x-button
                icon="o-trash"
                class="btn-ghost btn-xs text-error"
                wire:click="removeRow({{ $index }})"
            />
        </div>
    </div>
</div>