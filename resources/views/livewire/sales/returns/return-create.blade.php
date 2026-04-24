{{-- 檔案路徑：resources/views/livewire/sales/returns/return-create.blade.php --}}

<div class="pb-4 space-y-4">
    {{-- 1. 原銷售單資訊摘要 --}}
    <x-card shadow class="bg-base-200/50">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-2">
            <div>
                <div class="text-sm opacity-70">原銷售單號</div>
                <div class="font-bold text-lg font-mono">{{ $sale->invoice_number }}</div>
                <div class="text-xs mt-1">
                    <x-badge :value="'退回入庫：' . ($warehouses->find($warehouse_id)?->name ?? '未指定倉庫')" class="badge-neutral" />
                </div>
            </div>
            <div class="text-left md:text-right">
                <div class="text-sm opacity-70">客戶：{{ $sale->customer->name ?? '零售客戶' }}</div>
                <div class="text-xs opacity-50">銷售日期：{{ $sale->created_at->format('Y-m-d') }}</div>
            </div>
        </div>
    </x-card>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
        {{-- 左側：可退回商品選擇區 --}}		
        <div class="lg:col-span-2 space-y-4">
            <x-card title="退貨原因 / 內部備註" shadow class="bg-base-100 border-t-4 border-primary">
                <x-textarea					
                    wire:model="return_reason" 
                    placeholder="請輸入退貨原因..." 
                    rows="2" 
                    shadow
                />
            </x-card>

            <x-card title="選擇退回商品" progress-indicator="addItemToReturn" shadow class="bg-base-100 border-t-4 border-primary">
                {{-- PC 端表格 --}}
                <div class="hidden lg:block space-y-2">
                    @foreach($sale->items as $saleItem)
                        <div class="flex items-center justify-between p-3 border rounded-xl hover:bg-base-50 transition-colors group">
                            <div class="flex-1">            
                                <div class="font-bold text-sm">
                                    {{ $saleItem->product?->full_display_name ?? $saleItem->product?->name ?? '未命名商品' }}
                                </div>
                                <div class="text-xs opacity-50 font-mono">
                                    可退數量: {{ number_format($saleItem->quantity, 0) }} | 
                                    單價: NT$ {{ number_format($saleItem->price, 2) }}
                                </div>
                            </div>
                            <x-button 
                                icon="o-plus" 
                                class="btn-circle btn-sm btn-ghost group-hover:btn-primary" 
                                wire:click="addItemToReturn({{ $saleItem->id }})"
                                :disabled="collect($return_items)->contains('sale_item_id', $saleItem->id)"
                            />
                        </div>
                    @endforeach
                </div>

                {{-- 手機端卡片 --}}
                <div class="lg:hidden grid grid-cols-1 gap-3">
                    @foreach($sale->items as $saleItem)
                        <div class="p-4 border rounded-2xl bg-base-50 flex flex-col gap-3 shadow-sm">
                            <div class="flex justify-between items-start">
                                <div class="font-bold text-base leading-tight">{{ $saleItem->product?->name }}</div>
                                <x-badge :value="'NT$ ' . number_format($saleItem->price, 2)" class="badge-outline font-mono text-xs" />
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-xs opacity-60">可退回剩餘：{{ number_format($saleItem->quantity, 0) }}</span>
                                <x-button 
                                    label="加入" 
                                    icon="o-plus" 
                                    class="btn-primary btn-sm rounded-full" 
                                    wire:click="addItemToReturn({{ $saleItem->id }})"
                                    :disabled="collect($return_items)->contains('sale_item_id', $saleItem->id)"
                                />
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>

        {{-- 右側：退貨單明細與結算 --}}
        <div class="lg:col-span-2 space-y-4">
            <x-card title="退貨明細與調整" shadow class="bg-base-100 border-t-4 border-primary">
                {{-- 退貨商品列表 (整合 PC/Mobile) --}}
                <div class="space-y-3 mb-6">
                    @forelse($return_items as $index => $item)
                        <div class="p-3 bg-base-100 border rounded-xl shadow-sm space-y-3">
                            <div class="flex justify-between">
                                <div class="text-sm font-bold truncate pr-4">{{ $item['name'] }}</div>
                                <x-button 
                                    icon="o-trash" 
                                    class="btn-ghost btn-xs text-error p-0" 
                                    wire:click="removeReturnItem({{ $index }})" 
                                />
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <x-input 
                                        wire:model.live.debounce.500ms="return_items.{{ $index }}.quantity" 
                                        type="number" 
                                        class="input-sm w-20 font-mono text-center" 
                                        min="0.01" 
                                        step="0.01"
                                    />
                                    <span class="text-xs opacity-40">× NT$ {{ number_format($item['unit_price'], 2) }}</span>
                                </div>
                                <div class="text-sm font-bold font-mono text-primary">
                                    NT$ {{ number_format($item['subtotal'] ?? 0, 2) }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 border-2 border-dashed rounded-xl opacity-40">
                            尚未選擇退回商品
                        </div>
                    @endforelse
                </div>

                {{-- 調整項目與總結區域保持一致 --}}
                <div class="bg-base-50 p-4 rounded-2xl space-y-3">
                    <div class="text-xs font-bold text-primary uppercase tracking-widest flex items-center gap-2">
                        <x-icon name="o-adjustments-vertical" class="w-4 h-4" />
                        退款調整項目
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($fees as $key => $fee)
                            @php $config = config('business.return_fee_types.' . $fee['fee_type']); @endphp
                            <x-input 
                                label="{{ $config['name'] ?? $fee['fee_type'] }}"
                                wire:model.live.debounce.500ms="fees.{{ $key }}.amount"
                                type="number"
                                prefix="{{ ($config['operator'] ?? '') === 'sub' ? '-' : '+' }}"
                                class="input-sm font-mono {{ ($config['operator'] ?? '') === 'sub' ? 'text-error' : 'text-success' }}"
                            />
                        @endforeach
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-dashed space-y-2">
                    <div class="flex justify-between text-sm opacity-60">
                        <span>商品退款小計</span>
                        <span class="font-mono">NT$ {{ number_format($this->itemsTotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between items-end">
                        <span class="font-bold text-primary">預計應退總額</span>
                        <div class="text-right">
                            <div class="text-2xl md:text-3xl font-black font-mono text-primary leading-none">
                                NT$ {{ number_format($this->netRefundTotal, 2) }}
                            </div>
                        </div>
                    </div>
                </div>

                <x-slot:actions>
                    <x-button 
                        label="確認執行銷售退貨" 
                        icon="o-check" 
                        class="btn-primary w-full shadow-lg" 
                        wire:click="save" 
                        spinner 
                        :disabled="empty($return_items)"
                    />
                </x-slot:actions>
            </x-card>            
        </div>
    </div>
</div>