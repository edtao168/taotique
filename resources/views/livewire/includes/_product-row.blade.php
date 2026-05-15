{{-- views\livewire\includes\_product-row.blade.php--}}
{{-- 這裡封裝了「搜尋選取」+「手動輸入」的邏輯 --}}
<div class="flex flex-col gap-1">
    {{-- 搜尋選取器 --}}
    <x-choices
        wire:model.live="{{ $target }}.{{ $index }}.product_id"
        :options="$products"
        placeholder="搜尋現有商品..."
        search-function="search"
        no-result-text="找不到商品"
        debounce="300ms"
        single
        searchable
    />
    
    {{-- 手動輸入框 (當沒有 product_id 時，這裡就是唯一的名稱來源) --}}
    <x-input 
        wire:model.live="{{ $target }}.{{ $index }}.name" 
        placeholder="或人工輸入商品名稱" 
        class="input-sm text-xs" 
    />
</div>