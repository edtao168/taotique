{{-- 檔案路徑：resources/views/livewire/sales/return-create.blade.php --}}

<div class="pb-20 space-y-4">
    {{-- 1. 原單資訊摘要 (手機端隱藏部分次要資訊) --}}
    <x-card shadow class="bg-base-200/50">
        <div class="flex justify-between items-center">
            <div>
                <div class="text-sm opacity-70">原銷售單號</div>
                <div class="font-bold">{{ $sale->invoice_number }}</div>
            </div>
            <div class="text-right">
                <div class="text-sm opacity-70">客戶</div>
                <div class="font-bold">{{ $sale->customer->name ?? '零售客戶' }}</div>
            </div>
        </div>
    </x-card>

    {{-- 2. 費用調整區塊 --}}
    <x-card title="費用與扣除額" separator progress-indicator="save">
        <x-slot:menu>
            <x-button label="新增費用" icon="o-plus" wire:click="addFee" class="btn-sm btn-outline" />
        </x-slot:menu>

        @if(empty($fees))
            <div class="py-8 text-center opacity-50">目前無額外扣除費用</div>
        @else
            <div class="space-y-4">
                @foreach($fees as $index => $fee)
                    {{-- 手機端佈局 (md:hidden) --}}
                    <div class="p-4 rounded-lg border md:hidden bg-base-100 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="badge badge-neutral">費用項目 #{{ $index + 1 }}</span>
                            <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeFee({{ $index }})" />
                        </div>
                        
                        <x-select label="項目類型" :options="$feeTypes" wire:model.live="fees.{{ $index }}.fee_type" />
                        <x-input label="金額" type="number" step="0.0001" prefix="TWD" wire:model.live.debounce.500ms="fees.{{ $index }}.amount" />
                        <x-input label="備註" wire:model="fees.{{ $index }}.note" placeholder="例如：蝦皮成交費" />
                    </div>

                    {{-- PC 端佈局 (hidden md:grid) --}}
                    <div class="hidden md:grid md:grid-cols-12 gap-2 items-end">
                        <div class="col-span-3">
                            @if($index === 0) <label class="label text-xs">項目類型</label> @endif
                            <x-select :options="$feeTypes" wire:model.live="fees.{{ $index }}.fee_type" />
                        </div>
                        <div class="col-span-3">
                            @if($index === 0) <label class="label text-xs">金額</label> @endif
                            <x-input type="number" step="0.0001" prefix="TWD" wire:model.live.debounce.500ms="fees.{{ $index }}.amount" />
                        </div>
                        <div class="col-span-5">
                            @if($index === 0) <label class="label text-xs">備註 (選填)</label> @endif
                            <x-input wire:model="fees.{{ $index }}.note" />
                        </div>
                        <div class="col-span-1 text-center">
                            <x-button icon="o-trash" class="btn-ghost text-error" wire:click="removeFee({{ $index }})" />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

	{{-- 「商品退回清單」 --}}
	<x-card title="退回商品明細" separator class="mt-4">
		@if($sale->items->isEmpty())
        <div class="py-10 text-center text-gray-400">
            <x-icon name="o-shopping-cart" class="w-10 h-10 mb-2 opacity-20" />
            <p>此訂單無商品</p>
        </div>
    @else
        <div class="hidden md:block">
            <x-table :headers="[
                ['key' => 'product_name', 'label' => '商品名稱'],
                ['key' => 'quantity', 'label' => '原購買數量', 'class' => 'text-right'],
                ['key' => 'unit_price', 'label' => '單價', 'class' => 'text-right'],
                ['key' => 'subtotal', 'label' => '小計', 'class' => 'text-right'],
                ['key' => 'action', 'label' => '操作', 'class' => 'text-center']
            ]" :rows="$sale->items">
                @scope('cell_product_name', $item)
                    <div>
                        <div class="font-medium">{{ $item->product->name ?? '未知商品' }}</div>
                        <div class="text-xs text-gray-400">SKU: {{ $item->product->sku ?? '' }}</div>
                    </div>
                @endscope
                @scope('cell_quantity', $item)
                    <span class="font-mono">{{ number_format($item->quantity, 2) }}</span>
                @endscope
                @scope('cell_unit_price', $item)
                    @php
                        $price = $item->unit_price ?? $item->price ?? 0;
                    @endphp
                    <span class="font-mono">NT$ {{ number_format($price, 2) }}</span>
                @endscope
                @scope('cell_subtotal', $item)
                    @php
                        $price = $item->unit_price ?? $item->price ?? 0;
                        $subtotal = $item->quantity * $price;
                    @endphp
                    <span class="font-mono font-bold">NT$ {{ number_format($subtotal, 2) }}</span>
                @endscope
                @scope('cell_action', $item)
                    <x-button 
                        label="退貨" 
                        icon="o-arrow-path" 
                        wire:click="addProductToReturn({{ $item->product_id }})"
                        class="btn-xs btn-primary"
                    />
                @endscope
            </x-table>
        </div>
        
        {{-- 手機端顯示 --}}
        <div class="md:hidden space-y-2">
            @foreach($sale->items as $item)
                @php
                    $price = $item->unit_price ?? $item->price ?? 0;
                    $subtotal = $item->quantity * $price;
                @endphp
                <div class="p-3 border rounded-lg bg-base-100" 
                     wire:click="addProductToReturn({{ $item->product_id }})"
                     style="cursor: pointer;">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex-1">
                            <div class="font-bold text-sm">{{ $item->product->name ?? '未知商品' }}</div>
                            <div class="text-xs text-gray-400">SKU: {{ $item->product->sku ?? '' }}</div>
                        </div>
                        <x-badge value="x{{ number_format($item->quantity, 0) }}" class="badge-neutral" />
                    </div>
                    <div class="flex justify-between text-xs">
                        <span>單價: NT$ {{ number_format($price, 2) }}</span>
                        <span class="font-bold">小計: NT$ {{ number_format($subtotal, 2) }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
	</x-card>

    {{-- 3. 底部結算列 (手機端固定在底部) --}}
    <div class="fixed bottom-0 left-0 right-0 z-50 p-4 bg-base-100 border-t md:static md:border-none md:bg-transparent md:p-0">
        <x-card shadow class="md:shadow-none bg-primary/5 border-primary/20 border md:bg-base-200">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="grid grid-cols-2 md:flex gap-4 w-full md:w-auto">
                    <div class="text-center md:text-left">
                        <div class="text-xs opacity-60">商品小計</div>
                        <div class="font-mono font-bold">{{ number_format($this->itemsTotal, 2) }}</div>
                    </div>
                    <div class="text-center md:text-left border-l pl-4">
                        <div class="text-xs opacity-60 text-error">費用扣除</div>
                        <div class="font-mono font-bold text-error">-{{ number_format($this->feesTotal, 2) }}</div>
                    </div>
                </div>

                <div class="flex items-center gap-4 w-full md:w-auto border-t md:border-none pt-2 md:pt-0">
                    <div class="flex-1 text-right">
                        <div class="text-xs opacity-60">預計退款總額</div>
                        <div class="text-xl font-black text-primary font-mono">
                            TWD {{ number_format($this->netRefundTotal, 2) }}
                        </div>
                    </div>
                    <x-button label="建立退單" icon="o-check" class="btn-primary" wire:click="save" spinner />
                </div>
            </div>
        </x-card>
    </div>
</div>