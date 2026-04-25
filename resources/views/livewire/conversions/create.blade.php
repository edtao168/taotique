{{-- 檔案路徑：resources/views/livewire/conversions/create.blade.php --}}
<div>
    <x-header 
        :title="$isEdit ? '修改拆裝作業' : '新增拆裝作業'" 
        :subtitle="$isEdit ? '正在編輯單號：' . $conversion_no : '處理半成品拆解與成品重組'" 
        separator 
        progress-indicator
    >
        <x-slot:actions>
            <x-button label="取消" icon="o-x-mark" onclick="history.back()" />
            <x-button 
                :label="$isEdit ? '確認修改' : '確認過帳'" 
                icon="o-check" 
                class="btn-primary" 
                wire:click="save" 
                spinner 
            />
        </x-slot:actions>
    </x-header>

    <div class="grid gap-5">
        {{-- 表頭資訊 --}}
        <x-card shadow>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-input label="單號" wire:model="conversion_no" readonly class="bg-base-200" />
                <x-datepicker label="日期" wire:model="process_date" />
                <x-select label="分店" wire:model="shop_id" :options="[['id'=>1, 'name'=>'總店']]" />
            </div>
            <div class="mt-4">
                <x-input label="備註" wire:model="remark" />
            </div>
        </x-card>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            {{-- 左側：領料投入 (Type 1) --}}
            <x-card title="領料投入" shadow separator class="border-t-4 border-primary">
                <x-slot:menu>
                    <x-button icon="o-plus" label="增加原料" class="btn-sm btn-outline" wire:click="addItem(1)" />
                </x-slot:menu>

                @foreach($items as $index => $item)
                    @if($item['type'] == 1)
                        <div class="flex flex-col gap-3 mb-6 border-b pb-4 last:border-0 relative">
                            {{-- 移除按鈕 --}}
                            <div class="absolute right-0 top-0">
                                <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeItem({{ $index }})" />
                            </div>

                            <div class="w-full">
                                <x-choices 
                                    label="選擇原料" 
                                    wire:model="items.{{ $index }}.product_id" 
                                    :options="$productOptions" 
                                    search-function="search"
                                    option-label="name"
                                    option-value="id"
                                    searchable single debounce="300ms" />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <x-input label="數量" wire:model="items.{{ $index }}.quantity" type="number" step="0.0001" />
                                <x-input label="成本" wire:model="items.{{ $index }}.cost_snapshot" prefix="$" readonly class="bg-base-200" />
                            </div>
                        </div>
                    @endif
                @endforeach
            </x-card>

            {{-- 右側：成品產出 (Type 2) --}}
            <x-card title="成品產出" shadow separator class="border-t-4 border-secondary">
                <x-slot:menu>
                    <x-button icon="o-plus" label="增加成品" class="btn-sm btn-outline" wire:click="addItem(2)" />
                </x-slot:menu>

                @foreach($items as $index => $item)
                    @if($item['type'] == 2)
                        <div class="flex flex-col gap-3 mb-6 border-b pb-4 last:border-0 relative">
                            {{-- 移除按鈕 --}}
                            <div class="absolute right-0 top-0">
                                <x-button icon="o-trash" class="btn-ghost btn-sm text-error" wire:click="removeItem({{ $index }})" />
                            </div>

                            <div class="w-full">
                                <x-choices 
                                    label="選擇成品" 
                                    wire:model="items.{{ $index }}.product_id" 
                                    :options="$productOptions" 
                                    search-function="search"
                                    option-label="name"
                                    option-value="id"
                                    searchable single debounce="300ms" />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <x-input label="數量" wire:model="items.{{ $index }}.quantity" type="number" step="0.0001" />
                                <x-input label="成本" wire:model="items.{{ $index }}.cost_snapshot" prefix="$" readonly class="bg-base-200" />
                            </div>
                        </div>
                    @endif
                @endforeach
            </x-card>
        </div>
    </div>
</div>