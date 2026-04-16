{{-- 檔案路徑：resources/views/livewire/sales/create.blade.php --}}
<div x-data="{ 
        atBottom: false,
        checkScroll() {
            // 判斷是否滾動到接近底部 (留 150px 緩衝)
            this.atBottom = (window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 100);
        }
     }" 
     x-init="checkScroll()"
     @scroll.window="checkScroll()">
	 
    <x-header separator progress-indicator>
		<x-slot:title>
			<div class="flex items-center gap-4">
				<div class="p-3 bg-primary/10 rounded-2xl text-primary">
					<x-icon name="o-cube" class="w-8 h-8" />
				</div>
				<div>
					<h1 class="text-2xl font-bold tracking-tight text-base-content">
						{{ $isEdit ? '修改銷售單' : '銷售出庫作業' }}
					</h1>
					<div class="flex items-center gap-2 mt-1">
						<span class="badge badge-outline badge-sm font-mono opacity-70">{{ $isEdit ? $sale->invoice_number : $invoice_number }}</span>
						<span class="badge badge-ghost badge-sm uppercase tracking-tighter">Inventory Outbound</span>
					</div>
				</div>
			</div>
		</x-slot:title>
		<x-slot:actions>
			<x-button label="返回列表" icon="o-arrow-left" link="/sales" class="btn-ghost" />
			<x-button label="確認過帳" icon="o-check" class="btn-primary shadow-md hover:shadow-lg transition-all px-8" wire:click="save" spinner />
		</x-slot:actions>
	</x-header>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">
        
        {{-- 1. 左側：單據屬性 (1/4) --}}
        <div class="col-span-12 lg:col-span-3 space-y-6">
            <x-card title="單據資訊" shadow class="border-t-4 border-primary">
                <div class="space-y-4">
                    <x-choices label="客戶" wire:model="form.customer_id" :options="$customers" single icon="o-users" />
                    <x-datetime label="銷售日期" wire:model="form.sold_at" icon="o-calendar" />
                    <x-select label="管道" wire:model="form.channel" :options="$shops" option-value="id" option-label="name" icon="o-building-storefront" />
                    <x-select label="付款方式" wire:model="form.payment_method" :options="config('business.payment_methods')" icon="o-banknotes" />
					<x-select label="業務歸屬倉庫" wire:model="form.warehouse_id" :options="$warehouses" placeholder="請選擇倉庫"  icon="o-home-modern" />

                    <x-textarea label="備註" wire:model="form.remark" rows="2" />
                </div>
            </x-card>
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

        {{-- 2. 中間 商品明細 (2/4) --}}
		<div class="lg:col-span-6 pb-24 lg:pb-0">
			<x-card title="商品明細" shadow separator>
				<x-slot:title>
					<div class="flex justify-between items-center w-full">
						<span class="font-bold text-xl text-base-content">商品明細</span>
						<div class="flex items-center gap-2">
							<span class="text-xs opacity-50">連續掃描模式</span>
							<x-scanner.button mode="continuous" class="btn-xs btn-outline flex flex-row items-center gap-1" />
						</div>
					</div>
				</x-slot:title>

				{{-- PC 端標頭：僅在 lg (1024px) 以上顯示，對齊下方的 Grid 比例 --}}
				<div class="hidden lg:grid grid-cols-12 gap-4 mb-2 px-4 text-sm font-bold opacity-60">
					<div class="col-span-5">商品名稱 (搜尋或掃描)</div>
					<div class="col-span-2 text-right">單價</div>
					<div class="col-span-2">發貨倉庫</div>
					<div class="col-span-1 text-right">數量</div>
					<div class="col-span-2 text-right text-primary">小計</div>
				</div>

				<div class="space-y-3">
					@forelse($items as $index => $item)
						<div wire:key="sale-row-v1-{{ $index }}-{{ $item['product_id'] ?? 'new' }}" class="group relative p-4 lg:px-4 lg:py-3 hover:bg-base-200/40 transition-colors">
							
							{{-- 刪除按鈕：手機端常駐顯示，PC端則在懸停時於右側浮現，保持視覺潔淨 --}}
							<div class="absolute right-2 top-2 lg:top-1/2 lg:-translate-y-1/2 z-10">
								<x-button 
									icon="o-trash" 
									class="btn-error btn-xs lg:btn-ghost lg:opacity-0 lg:group-hover:opacity-100 transition-all" 
									wire:click="removeRow({{ $index }})" 
								/>
							</div>

							{{-- 核心 Grid 佈局 --}}
							<div class="grid grid-cols-1 lg:grid-cols-12 gap-3 lg:gap-4 items-center">
								
								{{-- 商品選擇 (占 5 格) --}}
								<div class="col-span-1 lg:col-span-5">
									@if(isset($items[$index]['product_id']) && $items[$index]['product_id'] > 0)
										{{-- 選定後的「靜態顯示」狀態 --}}
										<div class="flex items-center justify-between p-2 border rounded-lg bg-base-200/50">
											<div class="flex flex-col">                
												<span class="font-bold">{{ $item['name'] }}</span>
												{{-- 或者如果你想用 Model 的格式： --}}
												{{-- <span class="font-bold">{{ $item['name'] }}</span> --}}
											</div>
											<x-button icon="o-pencil" class="btn-ghost btn-xs" wire:click="$set('items.{{ $index }}.product_id', null)" />
										</div>
									@else
										{{-- 尚未選擇時的「搜尋」狀態 --}}
										<x-choices 
											wire:model.live="items.{{ $index }}.product_id" 
											:options="$productOptions"
											search-function="search"
											option-label="name"
											searchable 
											single
											debounce="300ms"
										/>
									@endif
								</div>

								{{-- 單價 (占 2 格) --}}
								<div class="col-span-1 lg:col-span-2">
									<div class="lg:hidden text-xs font-bold opacity-50 mb-1">單價</div>
									<x-input 
										wire:model.live.debounce.500ms="items.{{ $index }}.price" 
										class="font-mono text-right focus:bg-primary/5"
										placeholder="0"
									/>
								</div>

								{{-- 發貨倉庫 (占 2 格) --}}
								<div class="col-span-1 lg:col-span-2">
									<div class="lg:hidden text-xs font-bold opacity-50 mb-1">發貨倉庫</div>
									<x-select 
										wire:model.live="items.{{ $index }}.warehouse_id" 
										:options="$warehouses"
										placeholder="請選擇"
									/>
								</div>

								{{-- 數量 (占 1 格) --}}
								<div class="col-span-1 lg:col-span-1">
									<div class="lg:hidden text-xs font-bold opacity-50 mb-1 text-right">數量</div>
									<x-input 
										type="number" 
										wire:model.live.debounce.500ms="items.{{ $index }}.quantity" 
										class="font-mono text-right px-1"
										step="0.0001"
									/>
								</div>

								{{-- 小計 (占 2 格) --}}
								<div class="col-span-1 lg:col-span-2 text-right">
									<div class="lg:hidden text-xs font-bold opacity-50 mb-1">小計</div>
									<div class="flex flex-col">
										<span class="font-mono font-black text-primary text-lg lg:text-base">
											{{ number_format(bcmul($item['price'] ?? 0, $item['quantity'] ?? 0, 4), 0) }}
										</span>
										<span class="text-[10px] opacity-40 lg:hidden">TWD</span>
									</div>
								</div>
							</div>
						</div>
					@empty
						<div class="p-12 text-center bg-base-200/20 rounded-b-lg border-dashed border-2">
							<x-icon name="o-shopping-cart" class="w-12 h-12 mx-auto opacity-20" />
							<p class="mt-2 opacity-50 italic text-sm">尚未加入任何銷售商品，請開始掃描或搜尋</p>
						</div>
					@endforelse
				</div>

				<x-slot:actions>
					<x-button label="手動新增一行商品" icon="o-plus-circle" class="btn-ghost btn-sm w-full border-dashed border-2 hover:border-primary hover:text-primary" wire:click="addRow" />
				</x-slot:actions>
			</x-card>
		</div>

        {{-- 3. 右側：結算結帳 (1/4) --}}
        <div class="lg:col-span-3 space-y-4">
            <x-card title="結算" shadow class="bg-base-100 border-t-4 border-primary">
                <div class="space-y-4">
                    {{-- 第一列：小計 --}}
                    <div class="flex justify-between items-center p-2 bg-base-200/50 rounded-lg">
                        <span class="font-bold opacity-70">小計</span>
                        <span class="font-mono text-right">NT$ {{ number_format($form['subtotal'], 0) }}</span>
                    </div>

                    {{-- 第二列：雙欄對照 --}}                  
					<div class="grid grid-cols-2 gap-4 text-xs">
						{{-- 左側：買家區塊 --}}
						<div class="space-y-3">
							<div class="badge badge-info badge-outline font-bold px-4 py-3">買家</div>
							
							@foreach(collect(config('business.fee_types'))->where('target', 'customer') as $field => $config)
								<x-input 
									label="{{ $config['name'] }}" 
									wire:model.live.debounce.500ms="form.{{ $field }}"
									wire:change="calculateAll"
									prefix="{{ $config['operator'] === 'add' ? '+' : '-' }}"
									icon="{{ $config['icon'] ?? '' }}"
									class="input-sm text-right font-mono {{ $config['operator'] === 'sub' ? 'text-error' : '' }}"
									inputmode="decimal"
									step="0.01"
								/>
							@endforeach

							<div class="pt-2 border-t border-dashed">
								<div class="text-[10px] opacity-50">買家實付</div>
								<div class="text-lg font-bold text-blue-600 font-mono">
									NT$ {{ number_format($form['customer_total'], 2) }}
								</div>
							</div>
						</div>

						{{-- 右側：賣家區塊 --}}
						<div class="space-y-3">
							<div class="badge badge-success badge-outline font-bold px-4 py-3">賣家</div>
							
							@foreach(collect(config('business.fee_types'))->where('target', 'seller') as $field => $config)
								<x-input 
									label="{{ $config['name'] }}" 
									wire:model.live.debounce.500ms="form.{{ $field }}" 
									prefix="{{ $config['operator'] === 'add' ? '+' : '-' }}"
									icon="{{ $config['icon'] ?? '' }}"
									{{-- 針對賣家支出（sub）標註警告顏色 --}}
									class="input-sm text-right font-mono {{ $config['operator'] === 'sub' ? 'text-warning' : '' }}"
									inputmode="decimal"
									step="0.01"
								/>
							@endforeach
							
							{{-- 如果有特殊的 order_adjustment 欄位不在 config 中，可手動加回 --}}
							@if(!isset(config('business.fee_types')['order_adjustment']))
								<x-input label="帳款調整" wire:model.live.debounce.500ms="form.order_adjustment" prefix="±" class="input-sm text-right font-mono" />
							@endif
						</div>
					</div>

                    <div class="divider my-0"></div>

                    {{-- 第三列：賣家實收 --}}
                    <div class="p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-100">
                        <div class="text-[15px] text-emerald-600 font-bold tracking-widest uppercase mb-1">最終訂單進帳</div>
                        <div class="text-4xl font-black text-emerald-600 font-mono">
                            NT$ {{ number_format($form['final_net_amount'], 0) }}
                        </div>
                    </div>

                    <x-button label="確認收銀 / 過帳" icon="o-check" class="btn-primary w-full btn-lg" wire:click="save" spinner />
                </div>
            </x-card>
        </div>
    </div>

    <x-scanner.modal />
    <x-scanner.scripts />
</div>