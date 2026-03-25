<div>
    <x-header title="拆裝組合作業" subtitle="處理半成品拆解與成品重組" separator progress-indicator>
        <x-slot:actions>
            <x-button label="取消" icon="o-x-mark" onclick="history.back()" />
            <x-button label="確認過帳" icon="o-check" class="btn-primary" wire:click="save" spinner />
        </x-slot:actions>
    </x-header>

    <div class="grid gap-5">
        <x-card shadow>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-datepicker label="作業日期" wire:model="process_date" icon="o-calendar" />
                <x-input label="備註" wire:model="remark" placeholder="輸入加工細節說明..." />
                <x-choices label="分店" wire:model="store_id" :options="[['id'=>1, 'name'=>'總店']]" readonly />
            </div>
        </x-card>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            {{-- 左側：領料投入 (Type 1) --}}
            <x-card title="領料投入" shadow separator>
                <x-slot:menu>
                    <x-button icon="o-plus" label="增加原料" class="btn-sm btn-outline" wire:click="addItem(1)" />
                </x-slot:menu>

                @foreach($items as $index => $item)
                    @if($item['type'] == 1)
                    <div class="flex flex-col md:flex-row items-stretch md:items-end gap-3 mb-6 border-b pb-4 last:border-0" >
                        <div class="flex-1 min-w-0">
                            <x-choices 
                                label="原料/半成品" 
                                wire:model="items.{{ $index }}.product_id" 
                                :options="$input_products" 
                                option-label="display_name"
                                option-sub-label="sku"
                                search-function="searchInputs"
                                searchable
								single
                                debounce="300ms"
                            />
                        </div>
                        <div class="flex items-end gap-2">
                            <div class="w-full md:w-32">
                                <x-input label="數量" wire:model="items.{{ $index }}.quantity" type="number" step="0.0001" />
                            </div>
                            <x-button icon="o-trash" class="btn-ghost text-error" wire:click="removeItem({{ $index }})" />
                        </div>
                    </div>
                    @endif
                @endforeach
            </x-card>
            
            {{-- 右側：成品產出 (Type 2) --}}
            <x-card title="成品產出" shadow separator>
                <x-slot:menu>
                    <x-button icon="o-plus" label="增加成品" class="btn-sm btn-outline" wire:click="addItem(2)" />
                </x-slot:menu>

                @foreach($items as $index => $item)
                    @if($item['type'] == 2)
                    <div class="flex flex-col md:flex-row items-stretch md:items-end gap-3 mb-6 border-b pb-4 last:border-0">
                        <div class="flex-1 min-w-0">
                            <x-choices 
                                label="成品" 
                                wire:model="items.{{ $index }}.product_id" 
                                :options="$output_products" 
                                option-label="display_name"
                                option-sub-label="sku"
                                search-function="searchOutputs"
                                searchable
								single
                                debounce="300ms"
                            />
                        </div>
                        <div class="flex items-end gap-2">
                            <div class="w-24 md:w-28">
                                <x-input label="數量" wire:model="items.{{ $index }}.quantity" type="number" step="0.0001" />
                            </div>
                            <div class="w-28 md:w-32">
                                <x-input label="成本" wire:model="items.{{ $index }}.cost_snapshot" prefix="NT$" />
                            </div>
                            <x-button icon="o-trash" class="btn-ghost text-error" wire:click="removeItem({{ $index }})" />
                        </div>
                    </div>
                    @endif
                @endforeach
            </x-card>
        </div>
    </div>
</div>