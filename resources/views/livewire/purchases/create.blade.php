{{-- 檔案路徑：resources/views/livewire/purchases/create.blade.php --}}
<div>
    <x-header :title="$isEdit ? '修改採購單 - ' . $purchase->purchase_number : '新增採購單'" separator progress-indicator>
        <x-slot:actions>
            <x-button label="返回列表" icon="o-arrow-left" :link="route('purchases.index')" />
            {{-- 上方也加入動作按鈕 --}}
            @if($isEdit)
                <x-button label="退貨申請" icon="o-arrow-path" :link="route('purchases.returns.create', $purchase->id)" class="btn-outline btn-sm" />
            @endif
            <x-button label="儲存入庫" icon="o-check" class="btn-primary" wire:click="save" spinner />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {{-- 左側：主表資訊 --}}
        <div class="lg:col-span-1">
            <x-card title="基本資訊" shadow separator>
                <x-select label="供應商" icon="o-user" :options="$suppliers" wire:model="supplier_id" placeholder="請選擇" />
                <x-datetime label="採購日期" wire:model="purchased_at" icon="o-calendar" class="mt-4" />
                <x-input label="匯率 (TWD)" wire:model.live="exchange_rate" icon="o-currency-dollar" class="mt-4" />
                <x-input label="備註" wire:model.live="remark" icon="o-pencil-square" class="mt-4" />
            </x-card>
        </div>

        {{-- 右側：採購明細 --}}
        <div class="lg:col-span-3 pb-24 lg:pb-0">
            <x-card title="商品明細" shadow separator>
                <div class="hidden lg:grid grid-cols-12 gap-4 mb-2 px-4 text-sm font-bold opacity-60">
                    <div class="col-span-6">商品選擇與確認</div>
                    <div class="col-span-2">數量</div>
                    <div class="col-span-2 text-right">外幣單價</div>
                    <div class="col-span-2 text-right">TWD 預估</div>
                </div>

                <div class="space-y-3">
                    @foreach($items as $index => $item)
                        <div wire:key="purchase-row-{{ $index }}" class="p-4 border rounded-xl bg-base-50 relative">
                            <x-button icon="o-trash" class="btn-error btn-xs absolute -top-2 -right-2 rounded-full" 
                                wire:click="removeRow({{ $index }})" />

                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-center">
                                {{-- 商品搜尋 --}}
                                <div class="lg:col-span-6">
                                    <x-choices 
                                        wire:model.live="items.{{ $index }}.product_id" 
                                        :options="$productOptions"
                                        search-function="search"
                                        option-label="name"
                                        option-sub-label="sku"
                                        searchable
                                        single
                                        debounce="300ms"
                                        placeholder="輸入 SKU 或 商品名稱 搜尋..."
                                    >
                                        <x-slot:append>
                                            {{-- 🔧 使用共用元件 --}}
                                            <x-scanner.button :index="$index" mode="single" />
                                        </x-slot:append>
                                    </x-choices>
                                    
                                    @if($items[$index]['product_id'])
                                        @php
                                            $selectedProduct = collect($productOptions)->firstWhere('id', $items[$index]['product_id']);
                                        @endphp
                                        
                                    @endif
                                </div>

                                <div class="lg:col-span-2">
                                    <x-input type="number" wire:model.live="items.{{ $index }}.quantity" class="text-center" />
                                </div>

                                <div class="lg:col-span-2">
                                    <x-input wire:model.live="items.{{ $index }}.foreign_price" class="text-right" />
                                </div>

                                <div class="lg:col-span-2 text-right">
                                    <span class="font-mono font-bold text-blue-600">
                                        {{ number_format(bcmul($items[$index]['foreign_price'] ?? 0, $exchange_rate ?? 0, 4), 2) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <x-slot:actions>
                    <x-button label="追加商品列" icon="o-plus" class="btn-outline btn-sm w-full" wire:click="addRow" />
                </x-slot:actions>
				
				<x-slot:actions>
                    <div class="flex flex-col w-full gap-2">
                        <x-button label="追加商品列" icon="o-plus" class="btn-outline btn-sm w-full" wire:click="addRow" />
                        
                        {{-- 底部動作列 (手機端特別有用) --}}
                        @if($isEdit)
                        <div class="grid grid-cols-2 gap-2 mt-4 lg:hidden">
                             <x-button label="退貨" icon="o-arrow-path" :link="route('purchases.returns.create', $purchase->id)" class="btn-outline" />
                             <x-button label="儲存修改" icon="o-check" class="btn-primary" wire:click="save" />
                        </div>
                        @endif
                    </div>
                </x-slot:actions>
            </x-card>
        </div>
    </div>

    {{-- 🔧 使用共用 Modal 元件 --}}
    <x-scanner.modal />
    
    {{-- 🔧 引入共用 JavaScript（只需一次） --}}
    <x-scanner.scripts />
</div>