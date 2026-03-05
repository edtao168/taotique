<div>
    <x-header 
        title="{{ $this->sale->exists ? '修改銷貨單' : '新增銷貨單' }}" 
        subtitle="{{ $this->sale->exists ? '正在編輯單號：' . $this->sale->invoice_number : '建立新的銷售紀錄' }}" 
        separator 
    />

    <x-form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                {{-- 基本資訊卡片 --}}
                <x-card title="基本資訊" shadow>
                    <div class="grid grid-cols-2 gap-4">
                        <x-datetime label="銷售日期" wire:model="sold_at" type="date" icon="o-calendar" />
                        
                        {{-- 這裡需要 QuickSale.php 的 render() 傳入 $customers --}}
                        <x-select label="客戶" :options="$customers" wire:model="customer_id" placeholder="選擇客戶" />

                        <x-select 
                            label="銷售通路" 
                            wire:model="channel"
                            :options="[
                                ['id' => 'shopee', 'name' => '蝦皮購物'],
                                ['id' => 'store', 'name' => '實體店面'],                
                                ['id' => 'social', 'name' => '社群/私訊']
                            ]" 
                        />
                        <x-select 
                            label="付款方式" 
                            wire:model="payment_method"
                            :options="[
                                ['id' => 'shopee_coll', 'name' => '平台代收(蝦皮)'],
                                ['id' => 'cash', 'name' => '現金'],
                                ['id' => 'transfer', 'name' => '銀行轉帳']
                            ]" 
                        />
                    </div>
                </x-card>

                {{-- 商品明細卡片 --}}
                <x-card title="商品明細" shadow>
                    <div class="overflow-visible">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="w-16">#</th>
                                    <th>商品 (SKU：名稱 = 庫存)</th>
                                    <th class="w-24">數量</th>
                                    <th class="w-32">單價 (NT$)</th>
                                    <th class="w-32">小計</th>
                                    <th class="w-16"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $index => $item)
                                    <tr wire:key="sale-item-{{ $index }}" class="hover">
                                        <td>{{ $index + 1 }}</td>
                                        <td class="align-top">
                                            {{-- 單一欄位搜尋：由 HasProductSearch Trait 提供 search 功能 --}}
                                            <x-choices
                                                wire:model.live="items.{{ $index }}.product_id"
                                                :options="$products"
                                                placeholder="搜尋 SKU 或名稱..."
                                                search-function="search"
                                                no-result-text="找不到商品"
                                                debounce="300ms"
                                                single
                                                searchable
                                            />
                                        </td>
                                        <td class="align-top">
                                            <x-input type="number" wire:model.live="items.{{ $index }}.quantity" class="input-sm" />
                                        </td>
                                        <td class="align-top">
                                            <x-input type="number" wire:model.live="items.{{ $index }}.price" class="input-sm" />
                                        </td>
                                        <td class="text-right font-mono align-middle">
                                            {{ number_format($item['quantity'] * $item['price'], 2) }}
                                        </td>
                                        <td>
                                            <x-button icon="o-trash" wire:click="removeRow({{ $index }})" class="btn-ghost btn-sm text-error" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <x-slot:actions>
                        <x-button label="增加商品" wire:click="addRow" icon="o-plus" class="btn-outline btn-sm" />
                    </x-slot:actions>
                </x-card>
            </div>

            {{-- 右側：金額計算 --}}
            <div class="lg:col-span-1">
                <x-card title="金額結算" shadow class="sticky top-6">
                    <div class="grid grid-cols-2 gap-4">
                        {{-- 左側：買家端 --}}
                        <div class="space-y-3">
                            <div class="badge badge-outline badge-info">買家</div>                            
                            <x-input label="平台優惠" wire:model.live="platform_coupon" prefix="NT$" />                            
                            <x-input label="買家支付運費" wire:model.live="shipping_fee_customer" prefix="NT$" />
                            <x-input label="買家應付總額" wire:model="customer_total" prefix="NT$" readonly class="text-blue-600 font-bold bg-base-200" />
                        </div>

                        {{-- 右側：賣家端 --}}
                        <div class="space-y-3">
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
    </x-form>
</div>