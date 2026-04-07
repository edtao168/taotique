{{-- 檔案路徑：resources/views/livewire/purchases/returns/return-create.blade.php --}}

<div class="pb-4 space-y-4">
    {{-- 1. 原單資訊摘要 --}}
    <x-card shadow class="bg-base-200/50">
        <div class="flex justify-between items-center">
            <div>
                <div class="text-sm opacity-70">原採購單號</div>
                <div class="font-bold">{{ $purchase->purchase_number }}</div>
                <div class="text-xs mt-1">
                    <x-badge :value="'退貨倉庫：' . ($warehouses->find($warehouse_id)?->name ?? '未指定')" class="badge-neutral" />
                </div>
                
            </div>
            <div class="text-right">
                
				<div class="text-sm opacity-70">供應商：{{ $purchase->supplier->name ?? '未指定' }}</div>
				<div class="text-sm opacity-70">幣別 / 匯率快照：{{ $purchase->currency }} ({{ number_format($purchase->exchange_rate, 4) }})</div>
			</div>
        </div>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- 左側：可退回商品清單 --}}
        <div class="lg:col-span-2 space-y-4">
            <x-card title="可退回商品清單" separator shadow progress-indicator="addItemToReturn">
                
                {{-- PC 端表格 (md:block) --}}
                <div class="hidden md:block">
                    <x-table :headers="[
                        ['key' => 'product_name', 'label' => '商品名稱'],
                        ['key' => 'purchase_qty', 'label' => '原採購數量', 'class' => 'text-right'],
                        ['key' => 'unit_price', 'label' => '採購單價', 'class' => 'text-right'],
                        ['key' => 'action', 'label' => '加入 ', 'class' => 'text-center']
                    ]" :rows="$purchase->items">
                        @scope('cell_product_name', $item)
                            <div>
                                <div class="font-medium">{{ $item->product->name }}</div>
                                <div class="text-[10px] opacity-50 font-mono italic">{{ $item->product->sku }}</div>
                            </div>
                        @endscope
                        
                        @scope('cell_purchase_qty', $item)
                            <span class="font-mono">{{ number_format($item->quantity, 2) }}</span>
                        @endscope

                        @scope('cell_unit_price', $item)
                            <span class="font-mono">{{ number_format($item->unit_price, 2) }}</span>
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

                {{-- 手機端卡片 (md:hidden) --}}
                <div class="md:hidden space-y-2">
                    @foreach($purchase->items as $item)
                        <div class="p-4 border rounded-xl bg-base-100 flex justify-between items-center active:scale-95 transition-transform" 
                             wire:click="addItemToReturn({{ $item->product_id }})">
                            <div class="flex-1">
                                <div class="font-bold text-sm">{{ $item->product->name }}</div>
                                <div class="text-xs opacity-60 font-mono">
                                    {{ number_format($item->unit_price, 2) }} x {{ number_format($item->quantity, 0) }}
                                </div>
                            </div>
                            <x-icon name="o-plus-circle" class="w-6 h-6 text-primary" />
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>

        {{-- 右側：退回明細 --}}
<div class="lg:col-span-1">
    <x-card title="退回明細" separator shadow class="sticky top-4">
        @if(empty($return_items))
            <div class="py-12 text-center">
                <x-icon name="o-archive-box-x-mark" class="w-12 h-12 mx-auto opacity-20" />
                <p class="text-sm opacity-50 mt-2">尚未選擇退回商品</p>
            </div>
        @else
            <div class="space-y-3 mb-6 max-h-[50vh] overflow-y-auto pr-2">
                @foreach($return_items as $index => $item)
                    <div class="group flex justify-between items-start border-b border-base-200 pb-3">
                        <div class="flex-1">
                            <div class="text-sm font-bold truncate">{{ $item['product_name'] }}</div>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs font-mono bg-base-200 px-1.5 py-0.5 rounded">
                                    {{ number_format($item['quantity'], 2) }}
                                </span>
                                <span class="text-[10px] opacity-40">x</span>
                                <span class="text-xs font-mono opacity-70">{{ number_format($item['unit_price'], 2) }}</span>
                            </div>
                        </div>
                        <div class="text-right ml-2">
                            <div class="text-sm font-mono font-black text-error">
                                {{ number_format($item['subtotal'], 2) }}
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

        {{-- 總額和按鈕區域 - 移到 x-card 內部但不在 x-slot:actions 中 --}}
        <div class="w-full space-y-4 mt-4 pt-4 border-t">
            <div class="flex justify-between items-end">
                <span class="text-xs opacity-60 uppercase tracking-widest">預計退款總額</span>
                <div class="text-right">
                    <div class="text-[10px] font-mono opacity-40">{{ $purchase->currency }}</div>
                    <div class="text-2xl font-black font-mono text-primary leading-none">
                        {{ number_format($this->totalAmount, 2) }}
                    </div>
                </div>
            </div>

            <x-button 
                label="提交採購退回單" 
                icon="o-check" 
                class="btn-primary w-full shadow-lg" 
                wire:click="save" 
                spinner 
                :disabled="empty($return_items)"
            />
        </div>
    </x-card>
</div>
    </div>
</div>