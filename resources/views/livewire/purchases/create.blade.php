{{-- 檔案路徑：resources/views/livewire/purchases/create.blade.php --}}
<div>
    <x-header title="新增採購單" separator progress-indicator>
        <x-slot:actions>
            <x-button label="返回列表" icon="o-arrow-left" :link="route('purchases.index')" />
            <x-button label="儲存並入庫" icon="o-check" class="btn-primary" wire:click="save" spinner />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {{-- 左側：主表資訊 --}}
        <div class="lg:col-span-1">
            <x-card title="基本資訊" shadow separator>
                <div class="space-y-4">
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <x-select label="供應商" icon="o-user" :options="$suppliers" wire:model="supplier_id" placeholder="請選擇" />
                        </div>
                        <x-button icon="o-plus" class="btn-ghost" @click="$wire.showSupplierModal = true" />
                    </div>

                    <x-datetime label="採購日期" wire:model="purchased_at" icon="o-calendar" />
                    
                    <div class="grid grid-cols-2 gap-2">
                        <x-input label="幣別" wire:model="currency" readonly />
                        <x-input label="即時匯率" wire:model.live="exchange_rate" suffix="TWD" />
                    </div>
                    
                    <x-textarea label="備註" wire:model="remark" rows="3" />
                </div>
            </x-card>
        </div>

        {{-- 右側：採購明細 --}}
        <div class="lg:col-span-3">
            <x-card title="採購明細" shadow separator>
                {{-- --- PC 端表格 (LG 以上顯示) --- --}}
                <div class="hidden lg:block overflow-x-auto">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th class="w-1/3">商品名稱</th>
                                <th>入庫倉庫</th>
                                <th class="w-32 text-right">數量</th>
                                <th class="w-40 text-right">外幣單價 ({{ $currency }})</th>
                                <th class="w-24"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $index => $item)
                                <tr wire:key="item-pc-{{ $index }}">
                                    <td>
                                        <x-select :options="$products" wire:model.live="items.{{ $index }}.product_id" placeholder="選擇商品" />
                                    </td>
                                    <td>
                                        <x-select :options="$warehouses" wire:model="items.{{ $index }}.warehouse_id" />
                                    </td>
                                    <td>
                                        <x-input type="number" step="0.01" wire:model="items.{{ $index }}.quantity" class="text-right" />
                                    </td>
                                    <td>
                                        <x-input type="number" step="0.0001" wire:model="items.{{ $index }}.foreign_price" class="text-right" />
                                    </td>
                                    <td class="text-center">
                                        <x-button icon="o-trash" class="btn-ghost text-red-500" wire:click="removeRow({{ $index }})" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- --- 手機端卡片式編輯 (LG 以下顯示) --- --}}
                <div class="block lg:hidden space-y-4">
                    @foreach($items as $index => $item)
                        <div wire:key="item-mobile-{{ $index }}" class="border rounded-xl p-4 bg-base-50 relative">
                            <div class="absolute -top-2 -right-2">
                                <x-button icon="o-x-mark" class="btn-circle btn-xs btn-error text-white" wire:click="removeRow({{ $index }})" />
                            </div>
                            
                            <div class="space-y-3">
                                <x-select label="商品 (Row #{{ $index + 1 }})" :options="$products" wire:model.live="items.{{ $index }}.product_id" />
                                
                                <div class="grid grid-cols-2 gap-3">
                                    <x-select label="入庫倉" :options="$warehouses" wire:model="items.{{ $index }}.warehouse_id" />
                                    <x-input label="數量" type="number" step="0.01" wire:model="items.{{ $index }}.quantity" />
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <x-input label="外幣單價 ({{ $currency }})" type="number" step="0.0001" wire:model="items.{{ $index }}.foreign_price" />
                                    <div class="flex flex-col justify-end pb-1">
                                        <span class="text-[10px] text-gray-500">預估本幣成本 (TWD)</span>
                                        <span class="font-bold text-blue-700">
                                            {{ number_format(bcmul($items[$index]['foreign_price'] ?: 0, $exchange_rate ?: 0, 4), 2) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <x-slot:actions>
                    <x-button label="新增一列" icon="o-plus" class="btn-outline btn-sm w-full lg:w-auto" wire:click="addRow" />
                </x-slot:actions>
            </x-card>
        </div>
    </div>

    {{-- 快速新增供應商 Modal (不變) --}}
    <x-modal wire:model="showSupplierModal" title="快速新增供應商">
        <x-input label="供應商名稱" wire:model="newSupplierName" placeholder="例如：巴西原礦工廠" />
        <x-slot:actions>
            <x-button label="取消" @click="$wire.showSupplierModal = false" />
            <x-button label="確認建立" class="btn-primary" wire:click="saveSupplier" spinner />
        </x-slot:actions>
    </x-modal>
</div>