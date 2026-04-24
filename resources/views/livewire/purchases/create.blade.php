{{-- 檔案路徑：resources/views/livewire/purchases/create.blade.php --}}
<div x-data="{ 
        atBottom: false,
        checkScroll() {
            this.atBottom = (window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 100);
        }
     }" 
     x-init="checkScroll()"
     @scroll.window="checkScroll()">

    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-4">
                <div class="p-3 bg-primary/10 rounded-2xl text-primary">
                    <x-icon name="o-shopping-bag" class="w-8 h-8" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-base-content">
                        {{ $isEdit ? '修改採購單' : '採購入庫作業' }}
                    </h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="badge badge-outline badge-sm font-mono opacity-70">{{ $isEdit ? $purchase->purchase_number : $purchase_number }}</span>
                        <span class="badge badge-ghost badge-sm uppercase tracking-tighter">Inventory Inbound</span>
                    </div>
                </div>
            </div>
        </x-slot:title>
        <x-slot:actions>
            <x-button label="返回列表" icon="o-arrow-left" link="{{ route('purchases.index') }}" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 pb-24">
        {{-- 左側：表單主要內容 --}}
        <div class="lg:col-span-8 space-y-6">
            {{-- 基本資訊卡片 --}}
            <x-card title="單據資訊" shadow class="bg-base-100/60 backdrop-blur border-t-4 border-primary">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-choices label="供應商" wire:model.live="supplier_id" :options="$suppliers" searchable single icon="o-truck" />
                    <x-datetime label="採購日期" wire:model.live="purchased_at" icon="o-calendar" />
                    <x-select label="歸帳倉庫" wire:model.live="warehouse_id" :options="$warehouses" icon="o-home-modern" />					
                </div>
				@php
					$isAuto = (bool) App\Models\Setting::get('po_auto_stock_in', true);
				@endphp
				<div class="p-3 bg-base-200/50 rounded-lg border border-dashed border-base-300 flex items-center gap-3">
					<x-icon :name="$isAuto ? 'o-check-circle' : 'o-pause-circle'" 
							:class="$isAuto ? 'text-success' : 'text-warning'" />
					<div class="text-xs">
						<span class="font-bold">目前庫存處理模式：</span>
						{{ $isAuto ? '儲存即完成入庫並計算成本' : '儲存後僅保留單據，需手動執行入庫' }}
					</div>
				</div>
                <x-textarea label="備註" wire:model.live="remark" rows="3" placeholder="輸入此訂單的特殊說明..." />            
            </x-card>

			{{-- 採購明細區塊 --}}
			<x-card separator shadow class="bg-base-100/60 backdrop-blur border-t-4 border-primary">
				<x-slot:title>
					<div class="flex justify-between items-center w-full">
						<span class="font-bold text-xl text-base-content">採購明細</span>
						<div class="flex items-center gap-2">
							<span class="text-xs opacity-50 hidden sm:inline">連續掃描模式</span>
							<x-scanner.button mode="continuous" class="btn-xs btn-outline flex flex-row items-center gap-1" />
						</div>
					</div>
				</x-slot:title>
				<x-slot:menu>
					<x-button label="手動新增商品" icon="o-plus" wire:click="addRow" class="btn-outline btn-sm btn-primary" />
				</x-slot:menu>

				{{-- 電腦端：表格標題 --}}
				<div class="hidden lg:grid grid-cols-12 gap-4 px-2 mb-2 text-xs font-bold text-gray-500 uppercase tracking-widest">
					<div class="col-span-4">商品資訊 (含描述)</div>
					<div class="col-span-2">收貨倉庫</div>
					<div class="col-span-2 text-right">單價 ({{ $currency }})</div>
					<div class="col-span-1 text-right">數量</div>
					<div class="col-span-2 text-right">小計</div>
					<div class="col-span-1"></div>
				</div>

				<div class="space-y-4">
					@foreach($items as $index => $item)
						<div wire:key="purchase-item-{{ $index }}" class="group relative p-4 lg:p-0 border lg:border-none rounded-2xl bg-base-200/30 lg:bg-transparent">
							
							{{-- 電腦端佈局 --}}
							<div class="hidden lg:grid grid-cols-12 gap-4 items-center">
								<div class="col-span-4">
									@if(isset($item['product_id']) && $item['product_id'] > 0)
										<div class="flex items-center justify-between p-2 border rounded-lg bg-base-100 shadow-sm">
											<span class="font-bold text-sm truncate">{{ $item['name'] }}</span>
											<x-button icon="o-pencil" class="btn-ghost btn-xs text-primary" wire:click="$set('items.{{ $index }}.product_id', null)" />
										</div>
									@else
										<x-choices wire:model.live="items.{{ $index }}.product_id" :options="$productOptions" 
											search-function="search" option-label="name" searchable single />
									@endif
								</div>
								<div class="col-span-2">									
									<x-select 
										wire:model.live="items.{{ $index }}.warehouse_id" 
										:options="$warehouses"
										placeholder="請選擇"
										class="text-sm"
									/>
								</div>
								<div class="col-span-2">
									<x-input wire:model.live.debounce.500ms="items.{{ $index }}.foreign_price" type="number" step="0.0001" class="text-right font-mono" />
								</div>
								<div class="col-span-1">
									<x-input wire:model.live.debounce.500ms="items.{{ $index }}.quantity" type="number" step="1" class="text-center font-mono" />
								</div>
								<div class="col-span-2 text-right font-mono font-bold text-primary">
									{{ number_format(bcmul($item['quantity'] ?? 0, $item['foreign_price'] ?? 0, 4), 2) }}
								</div>
								<div class="col-span-1 text-right">
									<x-button icon="o-trash" class="btn-ghost btn-sm text-error opacity-0 group-hover:opacity-100" wire:click="removeRow({{ $index }})" />
								</div>
							</div>

							{{-- 手機端佈局 (採購明細卡片) --}}
							<div class="block lg:hidden space-y-3">
								<div class="flex justify-between items-start">
									<div class="flex-1">
										<div class="text-xs font-bold opacity-50 mb-1 uppercase">採購明細</div>
										@if(isset($item['product_id']) && $item['product_id'] > 0)
											<div class="flex items-center justify-between p-2 border rounded-lg bg-base-100 shadow-sm">
												<span class="font-bold text-sm">{{ $item['name'] }}</span>
												<x-button icon="o-pencil" class="btn-ghost btn-xs text-primary" wire:click="$set('items.{{ $index }}.product_id', null)" />
											</div>
										@else
											<x-choices wire:model.live="items.{{ $index }}.product_id" :options="$productOptions" 
												search-function="search" option-label="name" searchable single />
										@endif
									</div>
									<x-button icon="o-trash" class="btn-ghost btn-sm text-error ml-2" wire:click="removeRow({{ $index }})" />
								</div>								
								<div class="grid grid-cols-2 gap-3">
									<x-choices wire:model.live="warehouse_id" :options="$warehouses" icon="o-home-modern" />
									<x-input label="單價 ({{ $currency }})" wire:model.live.debounce.500ms="items.{{ $index }}.foreign_price" type="number" step="0.0001" class="font-mono" />
									<x-input label="數量" wire:model.live.debounce.500ms="items.{{ $index }}.quantity" type="number" step="1" class="font-mono" />
								</div>
								<div class="flex justify-between items-center pt-2 border-t border-dashed border-base-content/10">
									<span class="text-xs opacity-60 italic">項目小計</span>
									<span class="font-mono font-bold text-primary">
										{{ number_format(bcmul($item['quantity'] ?? 0, $item['foreign_price'] ?? 0, 4), 2) }}
									</span>
								</div>
							</div>
						</div>
					@endforeach
				</div>

				@if(count($items) === 0)
					<div class="py-12 text-center">
						<x-icon name="o-cube" class="w-12 h-12 mx-auto opacity-20" />
						<p class="mt-2 text-sm opacity-50">尚未加入任何商品</p>
					</div>
				@endif
			</x-card>
		</div>

		{{-- 右側：費用結算區 --}}
		<div class="lg:col-span-4 space-y-6">
			<x-card shadow class="bg-base-100 sticky top-6 border-t-4 border-primary">
				<div class="space-y-6">
					{{-- 費用輸入區 --}}
					<div class="space-y-4">
						<div class="flex items-center gap-2 mb-2">
							<x-icon name="o-calculator" class="w-5 h-5 text-primary" />
							<span class="font-bold">結算</span>
						</div>
						<x-input label="運費" wire:model.live.debounce.500ms="shipping_fee" icon="o-truck" class="input-sm text-right font-mono" />
						<x-input label="稅金" wire:model.live.debounce.500ms="tax" icon="o-receipt-percent" class="input-sm text-right font-mono" />
						<x-input label="其他費用" wire:model.live.debounce.500ms="other_fees" icon="o-plus-circle" class="input-sm text-right font-mono" />
						<x-input label="折扣金額" wire:model.live.debounce.500ms="discount" icon="o-minus-circle" class="input-sm text-right font-mono text-error" />
					</div>

					<div class="divider"></div>

					{{-- 應付總額 --}}
					<div class="p-6 bg-base-content text-base-100 rounded-2xl shadow-inner relative overflow-hidden">
						<div class="absolute -right-4 -top-4 opacity-10">
							<x-icon name="o-banknotes" class="w-24 h-24" />
						</div>
						<div class="relative z-10">
							<div class="text-xs opacity-60 uppercase tracking-widest mb-1">應付總額 ({{ $currency }})</div>
							<div class="text-4xl font-black font-mono leading-none tracking-tighter">
								{{ number_format($this->totalAmount, 2) }}
							</div>
						</div>
					</div>

					{{-- 匯率轉換 --}}
					<div class="pt-2 px-1">
						<x-input label="評估匯率" wire:model.live="exchange_rate" icon="o-arrows-right-left" :readonly="$currency === 'TWD'" 
							class="input-sm text-right font-mono {{ $currency === 'TWD' ? 'bg-base-200' : '' }}" />
					</div>

					{{-- 最終 TWD 成本 --}}
					<div class="p-5 bg-primary/10 rounded-xl border border-primary/20">
						<div class="text-[13px] text-primary font-bold uppercase tracking-widest mb-1">估計庫存成本 (TWD)</div>
						<div class="text-3xl font-black text-base-content font-mono tracking-tighter">
							NT$ {{ number_format($this->totalTwd, 0) }}
						</div>
					</div>

					<x-button 
						:label="$isAuto ? '確認入庫 / 過帳' : ($isEdit ? '更新採購單' : '確認建檔')" 
						:icon="$isAuto ? 'o-check-circle' : 'o-document-plus'" 
						class="w-full btn-lg shadow-xl btn-primary" 
						wire:click="save" 
						spinner 
					/>
				</div>
			</x-card>
		</div>		
	</div>	
		
	<x-scanner.modal />
	<x-scanner.scripts />

	{{-- 滾動提示 --}}
	<div x-show="!atBottom" 
		 x-transition:enter="transition ease-out duration-300"
		 x-transition:enter-start="opacity-0 transform translate-y-4"
		 x-transition:leave="transition ease-in duration-300"
		 x-transition:leave-end="opacity-0 transform translate-y-4"
		 class="hidden lg:flex fixed bottom-6 right-6 z-50 pointer-events-none">
		
		<div class="flex flex-col items-center">
			<span class="text-xs font-bold text-orange-600 bg-orange-50 px-2 py-1 rounded-full shadow-sm mb-1">下面還有</span>
			<div class="bg-orange-500 text-white p-3 rounded-full shadow-lg animate-bounce">
				<x-icon name="o-chevron-double-down" class="w-6 h-6" />
			</div>
		</div>
	</div>
</div>