{{-- 檔案路徑：resources/views/livewire/purchases/create.blade.php --}}
<div x-data="{ 
        atBottom: false,
        checkScroll() {
            // 判斷是否滾動到接近底部 (留 150px 緩衝)
            this.atBottom = (window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 100);
        }
     }" 
     x-init="checkScroll()"
     @scroll.window="checkScroll()">
	 
    <x-header :title="$isEdit ? '修改採購單 - ' . $purchase->purchase_number : '新增採購單 - ' . $purchase_number" separator progress-indicator>        
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
        <div class="lg:col-span-1 space-y-4">
			<x-card title="採購設定" shadow separator>
				<div class="space-y-4">
					<x-select label="供應商" icon="o-user" :options="$suppliers" wire:model="supplier_id" placeholder="請選擇" />
					
					<div class="grid grid-cols-2 gap-4">
						<x-datetime label="採購日期" wire:model="purchased_at" icon="o-calendar" />
						<x-select label="幣別" :options="[['id'=>'CNY', 'name'=>'人民幣'], ['id'=>'TWD', 'name'=>'台幣']]" wire:model.live="currency" />
					</div>
				</div>
			</x-card>

			<x-card title="費用與合計" shadow separator class="bg-white border-2 border-primary/5">
				<div class="space-y-4">
					{{-- 動態顯示幣別的 Prefix --}}
					<x-input label="運費" wire:model.live="shipping_fee" prefix="{{ $currency }}" class="text-right font-mono" />
					<x-input label="折扣" wire:model.live="discount" prefix="{{ $currency }}" class="text-right font-mono text-error" />

					<div class="divider my-1 text-xs opacity-50 uppercase tracking-widest">結算資訊</div>

					{{-- 匯率：若為 TWD 則鎖定為 1 --}}
					<x-input 
						label="當前匯率 (對 TWD)" 
						wire:model.live="exchange_rate" 
						icon="o-arrows-right-left" 
						class="text-right font-mono {{ $currency === 'TWD' ? 'bg-gray-100' : '' }}" 
						:readonly="$currency === 'TWD'"
					/>

					{{-- 最終統計區塊：使用淺橘色背景，深色文字確保可讀性 --}}
					<div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl shadow-sm flex items-center justify-between mt-4">
						<div>
							<p class="text-[10px] text-amber-700 font-black uppercase tracking-tighter">預估本幣支出 (TWD)</p>
							<p class="text-2xl font-black text-slate-800 font-mono">
								NT$ {{ number_format($this->totalTwd, 0) }}
							</p>
						</div>
						<div class="bg-amber-100 p-2 rounded-full">
							<x-icon name="o-currency-dollar" class="w-8 h-8 text-amber-600" />
						</div>
					</div>
				</div>
			</x-card>

			<div id="remark-section">
				<x-card title="備註" shadow>
					<x-textarea wire:model.live="remark" rows="3" placeholder="輸入採購備註..." />
				</x-card>
			</div>
		</div>	

		<div x-show="!atBottom" 
			 x-transition:enter="transition ease-out duration-300"
			 x-transition:enter-start="opacity-0 transform translate-y-4"
			 x-transition:leave="transition ease-in duration-300"
			 x-transition:leave-end="opacity-0 transform translate-y-4"
			 class="fixed bottom-6 right-6 z-50 pointer-events-none">
			
			<div class="flex flex-col items-center">
				<span class="text-xs font-bold text-orange-600 bg-orange-50 px-2 py-1 rounded-full shadow-sm mb-1">下面還有</span>
				<div class="bg-orange-500 text-white p-3 rounded-full shadow-lg animate-bounce">
					<x-icon name="o-chevron-double-down" class="w-6 h-6" />
				</div>
			</div>
		</div>
	
        {{-- 右側：採購明細 --}}
        <div class="lg:col-span-3 pb-24 lg:pb-0">
            <x-card title="商品明細" shadow separator>
				<x-slot:title>
					<div class="flex justify-between items-center w-full">
						<span class="font-bold">商品明細</span>
						<div class="flex items-center gap-2">
							<span class="text-xs opacity-50">連續掃描模式</span>
							<x-scanner.button mode="continuous" class="btn-xs btn-outline flex flex-row items-center gap-1" />
						</div>
					</div>
				</x-slot:title>
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
                                    </x-choices>
                                    
                                    @if($items[$index]['product_id'])
                                        @php
                                            $selectedProduct = collect($productOptions)->firstWhere('id', $items[$index]['product_id']);
                                        @endphp
                                        
                                    @endif
                                </div>

                                <div class="lg:col-span-2">
                                    <x-input type="number" wire:model.live="items.{{ $index }}.quantity" class="text-right" />
                                </div>

                                <div class="lg:col-span-2">
                                    <x-input wire:model.live="items.{{ $index }}.foreign_price" class="text-right" />
                                </div>

                                <div class="lg:col-span-2">
									<x-input 
										type="text"
										wire:model.live="items.{{ $index }}.foreign_price" 
										class="text-right h-10"
									/>
								</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                
				
				<x-slot:actions>
                    <div class="flex flex-col w-full gap-2">                        
                        <x-slot:actions>
							<x-button label="手動新增一行商品" icon="o-plus-circle" class="btn-ghost btn-sm w-full border-dashed border-2 hover:border-primary hover:text-primary" wire:click="addRow" />
						</x-slot:actions>
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