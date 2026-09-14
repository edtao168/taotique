{{-- resources/views/components/purchase-row.blade.php --}}
@props(['index', 'item', 'warehouses', 'productOptions', 'currency' => 'CNY'])

<div
    wire:key="purchase-row-{{ $index }}-{{ $item['product_id'] ?? 'new' }}"
    class="grid grid-cols-1 lg:grid-cols-12 gap-2 lg:gap-4 
           p-3 lg:p-0 
           bg-base-100 lg:bg-transparent 
           rounded-lg lg:rounded-none 
           border border-base-200 lg:border-0 
           lg:border-b lg:border-base-200
           items-start lg:items-center"
>
    {{-- 商品欄位：手機全寬，桌面 4 欄 --}}
    <div class="col-span-1 lg:col-span-4">
        <label class="label lg:hidden text-xs font-bold opacity-60 p-0 mb-1">商品</label>
        <x-product-picker
            :index="$index"
            :options="$productOptions"
            :selected-name="$item['name'] ?? null"
            label=""
            placeholder="輸入 SKU 或名稱搜尋..."
        />
    </div>

    {{-- 收貨倉庫：手機全寬，桌面 2 欄 --}}
    <div class="col-span-1 lg:col-span-2">
        <label class="label lg:hidden text-xs font-bold opacity-60 p-0 mb-1">收貨倉庫</label>
        <x-select
            id="purchase-wh-{{ $index }}"
            name="items[{{ $index }}][warehouse_id]"
            wire:model.live="items.{{ $index }}.warehouse_id"
            :options="$warehouses"
            placeholder="請選擇"
            class="select-sm"
        />
    </div>

    {{-- 單價：手機 1/2 寬，桌面 2 欄 --}}
    <div class="col-span-1 lg:col-span-2">
        <label class="label lg:hidden text-xs font-bold opacity-60 p-0 mb-1">單價 ({{ $currency }})</label>
        <x-input
            id="purchase-price-{{ $index }}"
            name="items[{{ $index }}][foreign_price]"
            wire:model.live.debounce.500ms="items.{{ $index }}.foreign_price"
            type="number"
            step="0.0001"
            inputmode="decimal"
            class="text-right font-mono input-sm"
        />
    </div>

    {{-- 數量：手機 1/2 寬，桌面 1 欄 --}}
    <div class="col-span-1 lg:col-span-1">
        <label class="label lg:hidden text-xs font-bold opacity-60 p-0 mb-1">數量</label>
        <x-input
            id="purchase-qty-{{ $index }}"
            name="items[{{ $index }}][quantity]"
            wire:model.live.debounce.500ms="items.{{ $index }}.quantity"
            type="number"
            step="1"
            inputmode="numeric"
            class="text-center font-mono input-sm"
        />
    </div>

    {{-- 小計 + 刪除：手機全寬，桌面 3 欄 --}}
    <div class="col-span-1 lg:col-span-3 flex items-center justify-between lg:justify-end gap-2">
        <label class="label lg:hidden text-xs font-bold opacity-60 p-0 mb-1">小計</label>
        <div class="flex items-center gap-2">
            <span class="font-mono font-bold text-primary">
                {{ number_format(bcmul($item['quantity'] ?? 0, $item['foreign_price'] ?? 0, 4), 2) }}
            </span>
            <x-button
                icon="o-trash"
                class="btn-ghost btn-xs text-error"
                wire:click="removeRow({{ $index }})"
            />
        </div>
    </div>
</div>