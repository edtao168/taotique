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

                {{-- 2. 商品明細：僅在此處透過 lg 分流 --}}
                <x-card title="商品明細" shadow separator>
                    {{-- 手機端設計：卡片式 (lg:hidden) --}}
                    <div class="lg:hidden space-y-4">
                        @foreach($items as $index => $item)
                            <div class="p-4 border rounded-xl bg-base-50 relative">
                                <x-button icon="o-trash" class="btn-circle btn-xs absolute -top-2 -right-2 btn-error text-white" wire:click="removeItem({{ $index }})" />
                                <div class="space-y-3">
                                    <x-select label="商品" :options="$products" wire:model.live="items.{{ $index }}.product_id" />
                                    <div class="grid grid-cols-2 gap-2">
                                        <x-input type="number" label="數量" wire:model.live="items.{{ $index }}.quantity" />
                                        <x-input label="單價" wire:model.live="items.{{ $index }}.price" />
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <x-button label="新增商品" icon="o-plus" class="btn-outline btn-sm w-full mt-2" wire:click="addItem" />
                    </div>

                    {{-- PC 端：100% 原始表格設計 (hidden lg:block) --}}
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th class="w-1/2">商品</th>
                                    <th class="text-right">數量</th>
                                    <th class="text-right">單價</th>
                                    <th class="w-16"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $index => $item)
                                    <tr>
                                        <td><x-select :options="$products" wire:model.live="items.{{ $index }}.product_id" /></td>
                                        <td><x-input type="number" wire:model.live="items.{{ $index }}.quantity" class="text-right" /></td>
                                        <td><x-input wire:model.live="items.{{ $index }}.price" class="text-right" /></td>
                                        <td><x-button icon="o-trash" class="btn-ghost text-error" wire:click="removeItem({{ $index }})" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <x-button label="新增一行" icon="o-plus" class="btn-ghost btn-sm" wire:click="addItem" />
                    </div>
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