<div>
    <x-header title="建立採購進貨" separator progress-indicator>
        <x-slot:actions>
            <x-button label="取消" :link="route('purchases.index')" />
            <x-button label="確認入庫" wire:click="save" class="btn-primary" spinner="save" icon="o-check" />
        </x-slot:actions>
    </x-header>

    <div class="grid lg:grid-cols-4 gap-6">
        {{-- 左側：主表資訊 --}}
        <div class="lg:col-span-1 space-y-4">
            <x-card title="單據資訊" shadow separator>
                <div class="space-y-1">
					<x-choices label="供應商" wire:model="supplier_id" :options="$suppliers" single searchable />
					<div class="flex justify-end">
						<x-button label="新增供應商" icon="o-plus" class="btn-ghost btn-xs text-primary" @click="$wire.showSupplierModal = true" />
					</div>
				</div>
				
                <x-datetime label="採購日期" wire:model="purchased_at" icon="o-calendar" />
                <x-select label="幣別" wire:model.live="currency" :options="[['id'=>'CNY','name'=>'人民幣'],['id'=>'TWD','name'=>'新台幣']]" />
                <x-input label="匯率" wire:model.live="exchange_rate" prefix="1 {{ $currency }} =" suffix="TWD" type="number" step="0.000001" />
                <x-textarea label="備註" wire:model="remark" rows="3" />
            </x-card>
        </div>

        {{-- 右側：明細項目 --}}
        <div class="lg:col-span-3">
            <x-card title="採購明細" shadow>
                <div class="overflow-visible">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-16">#</th>
								<th class="min-w-[300px]">商品 (SKU：名稱 = 庫存)</th>
								<th class="w-24">數量</th>
								<th class="w-32">成本價</th>
								<th class="w-16"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $index => $item)
                                <tr wire:key="purchase-item-{{ $index }}">
                                        <td>{{ $index + 1 }}</td>
                                        <td class="relative">
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
                                        <td>
                                            <x-input type="number" wire:model.live="items.{{ $index }}.quantity" class="input-sm" />
                                        </td>
                                        <td>
                                            <x-input type="number" wire:model.live="items.{{ $index }}.cost_price" class="input-sm" />
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

            {{-- 總計預覽 --}}
            <div class="mt-4 flex justify-end">
                <div class="stats shadow">
                    <div class="stat">
                        <div class="stat-title">預計總成本 (TWD)</div>
                        <div class="stat-value text-primary">
                            @php
                                $total = collect($items)->reduce(fn($carry, $item) => bcadd($carry, bcmul($item['quantity'], $item['cost_twd'], 4), 4), '0');
                                echo number_format($total, 2);
                            @endphp
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
	{{-- 快速新增供應商的 Modal --}}
<x-modal wire:model="showSupplierModal" title="快速新增供應商">
    <x-input label="供應商名稱" wire:model="newSupplierName" placeholder="輸入名稱..." />
    <x-slot:actions>
        <x-button label="取消" @click="$wire.showSupplierModal = false" />
        <x-button label="確認建立" wire:click="saveSupplier" class="btn-primary" spinner="saveSupplier" />
    </x-slot:actions>
</x-modal>
</div>