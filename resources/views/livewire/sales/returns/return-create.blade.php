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

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
        {{-- 左側：可退回商品清單 (佔 1/2) --}}
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

        {{-- 右側：退回明細 + 費用 (佔 1/2) --}}
        <div class="lg:col-span-2 space-y-4">
            {{-- 退回明細卡片 --}}
            <x-card title="退回明細" separator shadow class="sticky top-4">
                @if(empty($return_items))
                    <div class="py-12 text-center">
                        <x-icon name="o-archive-box-x-mark" class="w-12 h-12 mx-auto opacity-20" />
                        <p class="text-sm opacity-50 mt-2">尚未選擇退回商品</p>
                    </div>
                @else					   
					<div class="space-y-3">
						@foreach($return_items as $index => $item)
							<div class="bg-base-100 border border-base-300 rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow">
								<div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
									
									{{-- 左側：商品核心資訊 --}}
									<div class="flex items-center gap-4 flex-1">
										<div class="w-12 h-12 bg-base-200 rounded-lg flex items-center justify-center">
											<x-icon name="o-arrow-right-end-on-rectangle" class="w-6 h-6 text-gray-400" />
										</div>
										<div>        
											<div class="font-bold text-base-content">{{ $item['name'] }}</div>
											<div class="flex items-center gap-2 text-xs font-mono text-gray-500">
												<span class="badge badge-ghost badge-sm uppercase">{{ $item['barcode'] }}</span>
												<span>庫存單位：ea</span>
											</div>
										</div>
									</div>

									{{-- 右側：數值輸入與計算 --}}
									<div class="grid grid-cols-2 md:flex md:items-center gap-4 md:gap-8">
										{{-- 單價顯示 (唯讀) --}}
										<div class="flex flex-col md:items-end">
											<span class="text-xs text-gray-400">原售價</span>
											<span class="font-mono font-medium text-base-content">
												{{ number_format($item['price'], 2) }}
											</span>
										</div>

										{{-- 數量輸入 --}}
										<div class="flex flex-col">
											<span class="text-xs text-gray-400 mb-1">退回數量</span>
											<div class="w-28">
												<x-input 
													type="number" 
													wire:model.live="return_items.{{ $index }}.quantity" 
													class="input-sm text-center font-bold"
												/>
											</div>
										</div>

										{{-- 小計 (自動計算) --}}
										<div class="flex flex-col items-end col-span-2 md:col-span-1">
											<span class="text-xs text-primary font-medium">退款小計</span>
											<span class="font-mono text-lg font-black text-primary">
												{{ number_format(bcmul((string)$item['price'], (string)$item['quantity'], 4), 2) }}
											</span>
										</div>
									</div>
								</div>
							</div>
						@endforeach
					</div>
					 
                @endif

                {{-- 費用區塊 --}}
                <div class="hidden lg:block">
					<x-card shadow separator class="text-sm font-bold">
						<p>退回原因</p>
						<x-textarea wire:model="return_reason" placeholder="請輸入退貨原因或特殊說明..." rows="4" />
										
						@foreach($feeTypes as $key => $config)
						<div class="py-1">
							<div>{{ $config['name'] }}</div>
							<div>
								<x-input type="number" wire:model.live="fees.{{ $key }}.amount" />
							</div>
						</div>
						@endforeach					
					</x-card>
				</div>

				<div class="block lg:hidden">
					<p>退回原因</p>
					<x-textarea wire:model="return_reason" placeholder="請輸入退貨原因或特殊說明..." rows="4" />
					@foreach($feeTypes as $key => $config)
					<div class="mb-4 p-4 border rounded">
						<div class="font-bold">{{ $config['name'] }}</div>
						<x-input type="number" wire:model.live="fees.{{ $key }}.amount" />
					</div>
					@endforeach
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