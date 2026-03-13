<div>
    <x-header title="拆裝組合作業" subtitle="處理半成品拆解與成品重組" separator progress-indicator>
        <x-slot:actions>
            <x-button label="取消" icon="o-x-mark" onclick="history.back()" />
            <x-button label="確認過帳" icon="o-check" class="btn-primary" wire:click="save" spinner />
        </x-slot:actions>
    </x-header>

    <div class="grid gap-5">
        {{-- 單頭資訊 --}}
        <x-card shadow>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-datepicker label="作業日期" wire:model="process_date" icon="o-calendar" />
                <x-input label="備註" wire:model="remark" placeholder="輸入加工細節說明..." />
                <x-choices label="分店 (預設)" wire:model="store_id" :options="[['id'=>1, 'name'=>'總店']]" readonly />
            </div>
        </x-card>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            {{-- 左側：領料/投入區 (Type 1) --}}
            <x-card title="領料投入 (原料/半成品)" shadow separator>
                <x-slot:menu>
                    <x-button icon="o-plus" label="增加原料" class="btn-sm btn-outline" wire:click="addItem(1)" />
                </x-slot:menu>

                @foreach(collect($items)->where('type', 1) as $index => $item)
                    <div class="flex items-end gap-2 mb-3 border-b pb-3 border-base-200">
                        <div class="flex-1">
                            <x-select label="商品" wire:model="items.{{ $index }}.product_id" :options="$products" placeholder="選擇原料" />
                        </div>
                        <div class="w-32">
                            <x-input label="數量" wire:model="items.{{ $index }}.quantity" type="number" step="0.0001" />
                        </div>
                        <x-button icon="o-trash" class="btn-ghost text-error" wire:click="removeItem({{ $index }})" />
                    </div>
                @endforeach
            </x-card>

            {{-- 右側：入庫/產出區 (Type 2) --}}
            <x-card title="成品產出 (入庫)" shadow separator>
                <x-slot:menu>
                    <x-button icon="o-plus" label="增加產出" class="btn-sm btn-outline btn-primary" wire:click="addItem(2)" />
                </x-slot:menu>

                @foreach(collect($items)->where('type', 2) as $index => $item)
                    <div class="flex items-end gap-2 mb-3 border-b pb-3 border-base-200">
                        <div class="flex-1">
                            <x-select label="產出商品" wire:model="items.{{ $index }}.product_id" :options="$products" placeholder="選擇成品" />
                        </div>
                        <div class="w-32">
                            <x-input label="數量" wire:model="items.{{ $index }}.quantity" type="number" step="0.0001" />
                        </div>
                        <div class="w-32">
                            <x-input label="核算成本" wire:model="items.{{ $index }}.cost_snapshot" prefix="TWD" />
                        </div>
                        <x-button icon="o-trash" class="btn-ghost text-error" wire:click="removeItem({{ $index }})" />
                    </div>
                @endforeach
            </x-card>
        </div>
    </div>
</div>