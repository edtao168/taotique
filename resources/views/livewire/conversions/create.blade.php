{{-- resources/views/livewire/conversions/create.blade.php --}}
<div x-data="{ 
        atBottom: false,
        checkScroll() {
            let scrollHeight = Math.max(
                document.body.scrollHeight, document.documentElement.scrollHeight,
                document.body.offsetHeight, document.documentElement.offsetHeight,
                document.body.clientHeight, document.documentElement.clientHeight
            );
            this.atBottom = (window.innerHeight + window.scrollY) >= (scrollHeight - 150);
        }
     }" 
     x-init="checkScroll()"
     @scroll.window.debounce.50ms="checkScroll()">

    <x-header separator progress-indicator>
        @if($errors->any())
            <div class="alert alert-error mb-4 shadow-lg">
                <x-icon name="o-exclamation-triangle" class="w-6 h-6" />
                <div>
                    <h3 class="font-bold">請修正以下錯誤：</h3>
                    <ul class="list-disc list-inside text-sm mt-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <x-slot:title>
            <div class="flex items-center gap-4">
                <div class="p-3 bg-primary/10 rounded-2xl text-primary">
                    <x-icon name="o-arrows-up-down" class="w-8 h-8" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-base-content">
                        {{ $isEdit ? '修改拆裝作業' : '新增拆裝作業' }}
                    </h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="badge badge-outline badge-sm font-mono opacity-70">
                            {{ $isEdit ? $conversion->conversion_no : $conversion_no }}
                        </span>
                        <span class="badge badge-ghost badge-sm uppercase tracking-tighter">
                            {{ $isEdit ? '正在編輯單號' : '處理半成品拆解與成品重組' }}
                        </span>
                    </div>
                </div>
            </div>
        </x-slot:title>

        <x-slot:actions>
            <x-button label="取消" icon="o-x-mark" onclick="history.back()" class="btn-ghost" />
            <x-button 
                :label="$isEdit ? '確認修改' : '確認過帳'" 
                icon="o-check" 
                class="btn-primary shadow-md hover:shadow-lg transition-all px-8" 
                wire:click="save" 
                spinner 
            />
        </x-slot:actions>
    </x-header>

    <div class="grid gap-5 pb-24">
        {{-- 表頭資訊 --}}
        <x-card shadow class="border-t-4 border-primary">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-datetime label="日期" wire:model.live="form.process_date" />
                <x-select label="分店" wire:model="form.shop_id" :options="$shops ?? []" />
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <x-input label="備註" wire:model="form.remark" />
                
                <x-select 
                    label="成本差異處理" 
                    wire:model="form.variance_treatment" 
                    :options="$varianceOptions"
                    hint="耗損進費用：記為製造費用；資本化：併入在製品成本"
                />
            </div>
            
            <div class="mt-4">
                <x-button label="預覽成本差異" icon="o-eye" class="btn-outline btn-sm" wire:click="previewVariance" />
            </div>
        </x-card>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            {{-- 左側：領料投入 (Type 1) --}}
            <x-card shadow separator class="border-t-4 border-primary">
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <x-icon name="o-arrow-up-tray" class="w-5 h-5 text-primary" />
                        <span class="font-bold text-xl text-base-content">領料投入</span>
                    </div>
                </x-slot:title>
                
                <x-slot:menu>
                    <x-button icon="o-plus" label="增加原料" class="btn-sm btn-outline" wire:click="addItem(1)" />
                </x-slot:menu>

                @foreach($items as $index => $item)
					@if($item['type'] == 1)
						<div class="flex flex-col gap-3 mb-6 border-b pb-4 last:border-0 relative">
							<div class="absolute right-0 top-0">
								<x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeItem({{ $index }})" />
							</div>

							<div class="w-full">
								@if(isset($item['product_id']) && $item['product_id'] > 0)
									{{-- 已選擇商品：顯示標籤 + 編輯按鈕 --}}
									<div class="flex items-center justify-between p-3 border rounded-lg bg-base-100 shadow-sm">
										<div>
											<div class="font-bold text-sm">{{ $item['name'] }}</div>
											<div class="text-xs opacity-50 font-mono">{{ $item['sku'] ?? '' }}</div>
										</div>
										<x-button icon="o-pencil" class="btn-ghost btn-xs text-primary" 
												  wire:click="$set('items.{{ $index }}.product_id', null)" />
									</div>
								@else
									{{-- 未選擇商品：顯示搜尋下拉 --}}
									<x-choices 
										label="選擇原料" 
										wire:model="items.{{ $index }}.product_id" 
										:options="$inputProductOptions" 
										search-function="searchInputProducts"
										option-label="name"
										option-value="id"
										searchable single debounce="300ms" />
								@endif
							</div>

							<div class="grid grid-cols-2 gap-3">
								<x-input label="數量" wire:model="items.{{ $index }}.quantity" type="number" step="0.0001" />
								<x-input 
									label="單位成本" 
									wire:model="items.{{ $index }}.cost_snapshot" 
									prefix="NT$"
									type="number"
									step="0.0001"
								/>
							</div>
						</div>
					@endif
				@endforeach
            </x-card>

            {{-- 右側：成品產出 (Type 2) --}}
            <x-card shadow separator class="border-t-4 border-secondary">
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <x-icon name="o-arrow-down-tray" class="w-5 h-5 text-secondary" />
                        <span class="font-bold text-xl text-base-content">成品產出</span>
                    </div>
                </x-slot:title>

                <x-slot:menu>
                    <x-button icon="o-plus" label="增加成品" class="btn-sm btn-outline" wire:click="addItem(2)" />
                </x-slot:menu>

                @foreach($items as $index => $item)
                    @if($item['type'] == 2)
                        <div class="flex flex-col gap-3 mb-6 border-b pb-4 last:border-0 relative">
                            <div class="absolute right-0 top-0">
                                <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeItem({{ $index }})" />
                            </div>

                            <div class="w-full">
                                <x-choices 
									label="選擇成品" 
									wire:model="items.{{ $index }}.product_id" 
									:options="$outputProductOptions" 
									search-function="searchOutputProducts"
									option-label="name"
									option-value="id"
									searchable single debounce="300ms" />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <x-input label="數量" wire:model="items.{{ $index }}.quantity" type="number" step="0.0001" />
                                <x-input 
                                    label="單位成本" 
                                    wire:model="items.{{ $index }}.cost_snapshot" 
                                    prefix="NT$"
									type="number" 
                                    step="0.0001"
                                />
                            </div>
                        </div>
                    @endif
                @endforeach
            </x-card>
        </div>
    </div>

    {{-- 成本差異預覽 Modal --}}
    <x-modal wire:model="showVariancePreview" title="成本差異預覽" subtitle="拆裝作業成本試算" separator>
        <div class="space-y-4">
            <div class="flex justify-between items-center">
                <span class="font-medium">領料投入總成本：</span>
                <span class="font-mono text-lg text-primary">{{ number_format($previewInputTotal, 4) }}</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="font-medium">成品產出總成本：</span>
                <span class="font-mono text-lg text-secondary">{{ number_format($previewOutputTotal, 4) }}</span>
            </div>
            <div class="divider"></div>
            <div class="flex justify-between items-center">
                <span class="font-bold">成本差異：</span>
                <span class="font-mono text-lg {{ $previewVarianceType == '耗損' ? 'text-error' : ($previewVarianceType == '盤盈' ? 'text-success' : 'text-gray-500') }}">
                    {{ number_format(abs($previewVariance), 4) }}
                    <span class="text-sm ml-2">({{ $previewVarianceType }})</span>
                </span>
            </div>
            <div class="alert alert-info text-sm mt-4">
                <x-icon name="o-information-circle" class="w-5 h-5" />
                <span>根據「成本差異處理」設定，差異金額將自動產生對應會計分錄。</span>
            </div>
        </div>
    </x-modal>

    {{-- 下滑提示區塊 --}}
    <div x-show="!atBottom" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-y-4"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-end="opacity-0 transform translate-y-4"        
         class="flex fixed bottom-20 right-4 lg:bottom-6 lg:right-6 z-50 pointer-events-none">
        
        <div class="flex flex-col items-center">
            <span class="text-[10px] lg:text-xs font-bold text-orange-600 bg-orange-50 px-2 py-1 rounded-full shadow-sm mb-1">
                下滑查看
            </span>
            <div class="bg-orange-500 text-white p-2 lg:p-3 rounded-full shadow-lg animate-bounce">
                <x-icon name="o-chevron-down" class="w-4 h-4 lg:w-6 lg:h-6" />
            </div>
        </div>
    </div>
</div>