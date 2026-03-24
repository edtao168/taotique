{{-- 檔案路徑：resources/views/livewire/sales/create.blade.php --}}
<div>
    <x-header 
        title="{{ $this->sale->exists ? '修改銷貨單' : '新增銷貨單' }}" 
        subtitle="{{ $this->sale->exists ? '正在編輯單號：' . $this->sale->invoice_number : '建立新的銷售紀錄' }}" 
        separator 
    />

    <x-form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- 左側區域 --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- 1. 基本資訊：完全保留您的原始代碼 --}}
                <x-card title="基本資訊" shadow>
                    <div class="grid grid-cols-2 gap-4">
                        <x-datetime label="銷售日期" wire:model="sold_at" type="date" icon="o-calendar" />
                        <x-select label="客戶" :options="$customers" wire:model="customer_id" placeholder="選擇客戶" />
                        <x-select 
                            label="銷售通路" 
                            wire:model="channel"
                            :options="[['id' => 'shopee', 'name' => '蝦皮購物'], ['id' => 'store', 'name' => '實體店面'], ['id' => 'social', 'name' => '社群/私訊']]" 
                        />
                        <x-select 
                            label="付款方式" 
                            wire:model="payment_method"
                            :options="[['id' => 'shopee-', 'name' => '蝦皮錢包'], ['id' => 'cash', 'name' => '現金'], ['id' => 'transfer', 'name' => '銀行轉帳']]" 
                        />
                    </div>
                </x-card>

                {{-- 2. 商品明細：重構為 Grid 佈局 --}}
				<x-card title="商品明細" shadow separator>
					{{-- PC 端表頭：確保對齊感 (僅在 lg 顯示) --}}
					<div class="hidden lg:grid grid-cols-12 gap-4 mb-3 px-4 text-xs font-bold opacity-40 uppercase tracking-widest">
						<div class="col-span-6">搜尋商品 (SKU / 名稱)</div>
						<div class="col-span-2 text-center">數量</div>
						<div class="col-span-2 text-right">銷售單價</div>
						<div class="col-span-2 text-right">小計 (TWD)</div>
					</div>

					<div class="space-y-4">
						@foreach($items as $index => $item)
							<div wire:key="sale-row-{{ $index }}" class="p-4 border rounded-xl bg-base-50 relative">
								
								{{-- 刪除按鈕：漂浮在右上角 --}}
								<x-button 
									icon="o-trash" 
									class="btn-error btn-xs absolute -top-2 -right-2 rounded-full shadow-sm text-white" 
									wire:click="removeRow({{ $index }})" 
								/>

								{{-- Grid 容器 --}}
								<div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-center">
									
									{{-- 1. 商品搜尋：寬度佔 6/12 --}}
									<div class="lg:col-span-6">
										<x-choices 
											wire:model.live="items.{{ $index }}.product_id" 
											:options="$productOptions" 
											search-function="search"
											option-label="name"
											searchable
											single
											debounce="300ms"
											placeholder="輸入 SKU 或名稱搜尋..."
											no-result-text="找不到商品"
										/>
									</div>

									{{-- 2. 數量：寬度佔 2/12 --}}
									<div class="lg:col-span-2 text-center">
										<x-input 
											type="number" 
											label="數量" {{-- 手機端會顯示 Label --}}
											wire:model.live="items.{{ $index }}.quantity" 
											class="text-center font-bold lg:label-none" 
										/>
									</div>

									{{-- 3. 銷售單價：寬度佔 2/12 --}}
									<div class="lg:col-span-2 text-right">
										<x-input 
											label="單價" 
											wire:model.live="items.{{ $index }}.price" 
											class="text-right text-blue-700 lg:label-none" 
										/>
									</div>

									{{-- 4. 小計：寬度佔 2/12 --}}
									<div class="lg:col-span-2 text-right px-2">
										<span class="text-xs text-gray-400 block lg:hidden">小計</span>
										<span class="font-mono font-bold text-gray-700">
											{{ number_format(bcmul($items[$index]['price'] ?? 0, $items[$index]['quantity'] ?? 0, 4), 2) }}
										</span>
									</div>

								</div>
							</div>
						@endforeach
					</div>

					<x-slot:actions>
						<x-button label="追加商品列" icon="o-plus" class="btn-outline btn-sm w-full lg:w-auto" wire:click="addRow" />
					</x-slot:actions>
				</x-card>
            </div>

            {{-- 3. 右側帳務摘要 --}}
            <div class="lg:col-span-1">
                <div class="sticky top-6">
                    <x-card title="帳務摘要" shadow>
						<div class="space-y-3 mb-4">
							<x-input label="銷售總額" wire:model.live="subtotal" prefix="NT$" readonly class="bg-base-200" />
						</div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-3 p-3 border rounded-lg bg-base-100">
                                <div class="badge badge-outline badge-info">買家</div>
                                
                                <x-input label="買家付運費" wire:model.live="shipping_fee_customer" prefix="NT$" />
                                <x-input label="賣場折扣" wire:model.live="discount" prefix="NT$" />
                                <x-input label="平台優惠券" wire:model.live="platform_coupon" prefix="NT$" />
								<div class="mt-auto pt-2">
									<x-input 
										label="買家實付金額" 
										wire:model.live="customer_total" 
										prefix="NT$" 
										readonly 
										class="bg-transparent border-info text-info font-black" 
									/>
								</div>
                            </div>

                            <div class="space-y-3 p-3 border rounded-lg bg-base-100">
                                <div class="badge badge-outline badge-error">賣家</div>                            
                                <x-input label="手續費" wire:model.live="platform_fee" prefix="NT$" />
                                <x-input label="平台代付運費" wire:model.live="shipping_fee_platform" prefix="NT$" />
                                <x-input label="帳款調整" wire:model.live="order_adjustment" prefix="NT$" />
                            </div>
                        </div>

                        <div class="divider"></div>

                        {{-- 底部：預計淨收益 --}}
                        <div class="p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-100">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-emerald-600 font-bold">預計淨進帳 (Net)</span>
                                <div class="text-2xl font-black text-emerald-700">
                                    NT$ {{ number_format(max(0, (float)$customer_total - (float)$platform_fee - (float)$shipping_fee_platform + (float)$order_adjustment), 2) }}
                                </div>
                            </div>
                        </div>

                        <x-slot:actions>
                            <x-button label="取消" :link="route('sales.index')" />
                            <x-button label="儲存單據" type="submit" class="btn-primary" icon="o-check" spinner="save" />
                        </x-slot:actions>
                    </x-card>
                </div>
            </div>
        </div>
    </x-form>
</div>