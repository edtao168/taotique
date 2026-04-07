{{-- 檔案路徑：resources/views/livewire/sales/returns/return-create.blade.php --}}

<div class="pb-4 space-y-4">
    {{-- 1. 原單資訊摘要 --}}
    <x-card shadow class="bg-base-200/50">
        <div class="flex justify-between items-center">
            <div>
                <div class="text-sm opacity-70">原銷售單號</div>
                <div class="font-bold">{{ $sale->invoice_number }}</div>
                <div class="text-xs mt-1">
                    <x-badge :value="'退貨倉庫：' . ($warehouses->find($warehouse_id)?->name ?? '未指定')" class="badge-neutral" />
                </div>
            </div>
            <div class="text-right">
                <div class="text-sm opacity-70">客戶</div>
                <div class="font-bold">{{ $sale->customer->name ?? '零售客戶' }}</div>
            </div>
        </div>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- 左側：可退回商品清單 (佔 2/3) --}}
        <div class="lg:col-span-2 space-y-4">
            <x-card title="可退回商品清單" separator shadow progress-indicator="addItemToReturn">
                @if($sale->items->isEmpty())
                    <div class="py-10 text-center text-gray-400">
                        <x-icon name="o-shopping-cart" class="w-10 h-10 mb-2 opacity-20" />
                        <p>此訂單無商品</p>
                    </div>
                @else
                    {{-- PC 端表格 --}}
                    <div class="hidden md:block">
                        <x-table :headers="[
                            ['key' => 'product_name', 'label' => '商品名稱'],
                            ['key' => 'quantity', 'label' => '原購買數量', 'class' => 'text-right'],
                            ['key' => 'unit_price', 'label' => '單價', 'class' => 'text-right'],
                            ['key' => 'action', 'label' => '加入', 'class' => 'text-center']
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
                            @scope('cell_action', $item)
                                <x-button 
                                    icon="o-plus" 
                                    wire:click="addItemToReturn({{ $item->product_id }})"
                                    class="btn-sm btn-primary btn-outline" 
                                    tooltip="加入退回明細"
                                />
                            @endscope
                        </x-table>
                    </div>
                    
                    {{-- 手機端卡片 --}}
                    <div class="md:hidden space-y-2">
                        @foreach($sale->items as $item)
                            @php
                                $price = $item->unit_price ?? $item->price ?? 0;
                            @endphp
                            <div class="p-4 border rounded-xl bg-base-100 flex justify-between items-center active:scale-95 transition-transform" 
                                 wire:click="addItemToReturn({{ $item->product_id }})">
                                <div class="flex-1">
                                    <div class="font-bold text-sm">{{ $item->product->name ?? '未知商品' }}</div>
                                    <div class="text-xs opacity-60 font-mono">
                                        NT$ {{ number_format($price, 2) }} x {{ number_format($item->quantity, 0) }}
                                    </div>
                                </div>
                                <x-icon name="o-plus-circle" class="w-6 h-6 text-primary" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>

        {{-- 右側：退回明細 + 費用 (佔 1/3) --}}
        <div class="lg:col-span-1 space-y-4">
            {{-- 退回明細卡片 --}}
            <x-card title="退回明細" separator shadow class="sticky top-4">
                @if(empty($return_items))
                    <div class="py-12 text-center">
                        <x-icon name="o-archive-box-x-mark" class="w-12 h-12 mx-auto opacity-20" />
                        <p class="text-sm opacity-50 mt-2">尚未選擇退回商品</p>
                    </div>
                @else
                    <div class="space-y-3 mb-6 max-h-[40vh] overflow-y-auto pr-2">
                        @foreach($return_items as $index => $item)
                            <div class="group flex justify-between items-start border-b border-base-200 pb-3">
                                <div class="flex-1">
                                    <div class="text-sm font-bold truncate">{{ $item['product_name'] }}</div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs font-mono bg-base-200 px-1.5 py-0.5 rounded">
                                            {{ number_format($item['quantity'], 2) }}
                                        </span>
                                        <span class="text-[10px] opacity-40">x</span>
                                        <span class="text-xs font-mono opacity-70">NT$ {{ number_format($item['unit_price'], 2) }}</span>
                                    </div>
                                </div>
                                <div class="text-right ml-2">
                                    <div class="text-sm font-mono font-black text-error">
                                        NT$ {{ number_format($item['subtotal'], 2) }}
                                    </div>
                                    <x-button 
                                        icon="o-trash" 
                                        class="btn-ghost btn-xs text-error opacity-0 group-hover:opacity-100 transition-opacity" 
                                        wire:click="removeReturnItem({{ $index }})" 
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- 費用區塊 --}}
                <div class="border-t pt-4 mt-2">
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-sm font-bold">費用與扣除額</span>
                        <x-button label="新增費用" icon="o-plus" wire:click="addFee" class="btn-xs btn-outline" />
                    </div>
                    
                    @if(empty($fees))
                        <div class="py-4 text-center text-xs opacity-50">無額外扣除費用</div>
                    @else
                        <div class="space-y-3">
                            @foreach($fees as $index => $fee)
                                <div class="p-2 border rounded-lg text-sm">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <x-select 
                                                :options="$feeTypes" 
                                                wire:model.live="fees.{{ $index }}.fee_type"
                                                class="select-xs select-bordered"
                                            />
                                            <x-input 
                                                type="number" 
                                                step="0.0001" 
                                                prefix="TWD" 
                                                wire:model.live.debounce.500ms="fees.{{ $index }}.amount"
                                                class="input-xs mt-1"
                                                placeholder="金額"
                                            />
                                            <x-input 
                                                wire:model="fees.{{ $index }}.note" 
                                                placeholder="備註"
                                                class="input-xs mt-1"
                                            />
                                        </div>
                                        <x-button 
                                            icon="o-trash" 
                                            class="btn-ghost btn-xs text-error ml-2" 
                                            wire:click="removeFee({{ $index }})" 
                                        />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <x-slot:actions>
                    <div class="w-full space-y-4">
                        <div class="flex justify-between items-end border-t pt-4">
                            <span class="text-xs opacity-60 uppercase tracking-widest">預計退款總額</span>
                            <div class="text-right">
                                <div class="text-2xl font-black font-mono text-primary leading-none">
                                    NT$ {{ number_format($this->netRefundTotal, 2) }}
                                </div>
                                <div class="text-[10px] text-gray-400">
                                    商品小計: {{ number_format($this->itemsTotal, 2) }} 
                                    費用: -{{ number_format($this->feesTotal, 2) }}
                                </div>
                            </div>
                        </div>

                        <x-button 
                            label="提交銷貨退回單" 
                            icon="o-check" 
                            class="btn-primary w-full shadow-lg" 
                            wire:click="save" 
                            spinner 
                            :disabled="empty($return_items)"
                        />
                    </div>
                </x-slot:actions>
            </x-card>
        </div>
    </div>
</div>