<div>
    <x-header title="倉庫管理" subtitle="管理各分店的庫存存放點" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋倉庫..." wire:model.live.debounce="search" icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
            <x-button label="新增倉庫" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card padding="none">
        {{-- PC 端：表格顯示 --}}
        <div class="hidden md:block">
            <x-table :headers="$headers" :rows="$warehouses" striped>
                @scope('cell_is_active', $warehouse)
                    <x-checkbox wire:click="toggleActive({{ $warehouse->id }})" :checked="$warehouse->is_active" class="checkbox-primary" tight />
                @endscope

                @scope('cell_actions', $warehouse)
                    <div class="flex gap-2 justify-end">
                        <x-button icon="o-pencil" wire:click="edit({{ $warehouse->id }})" class="btn-sm btn-ghost text-primary" />
                        <x-button icon="o-trash" wire:click="delete({{ $warehouse->id }})" wire:confirm="確定要刪除此倉庫嗎？" class="btn-sm btn-ghost text-error" />
                    </div>
                @endscope
            </x-table>
        </div>

        {{-- 手機端：卡片佈局 --}}
        <div class="md:hidden">
            @foreach($warehouses as $warehouse)
                <div class="p-4 border-b border-base-200 last:border-none">
                    <div class="flex justify-between items-center">
                        <div wire:click="edit({{ $warehouse->id }})" class="flex-1 cursor-pointer">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400 font-mono">#{{ $warehouse->id }}</span>
                                <span class="text-lg font-bold">{{ $warehouse->name }}</span>
                                @if(!$warehouse->is_active)
                                    <span class="badge badge-ghost badge-sm text-gray-400">停用中</span>
                                @endif
                            </div>
                            <div class="text-sm text-gray-500 mt-1">
                                <span class="opacity-70">所屬店鋪：</span>{{ $warehouse->shop->name ?? '未設定' }}
                            </div>
                        </div>
                        <div class="flex gap-1 items-center">
                            {{-- 手機端保留快速開關 --}}
                            <x-checkbox wire:click="toggleActive({{ $warehouse->id }})" :checked="$warehouse->is_active" class="checkbox-primary checkbox-sm mr-2" />
                            <x-button icon="o-pencil" wire:click="edit({{ $warehouse->id }})" class="btn-sm btn-ghost text-primary" />
                            <x-button icon="o-trash" wire:click="delete({{ $warehouse->id }})" wire:confirm="確定刪除？" class="btn-sm btn-ghost text-error" />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-card>

    <x-modal wire:model="warehouseModal" separator>
        <x-slot:title>
            {{ $editingWarehouse ? '編輯倉庫' : '新增倉庫' }}
        </x-slot:title>
        <div class="grid gap-4">
            <x-select label="所屬店鋪" icon="o-building-storefront" :options="$shops" wire:model="shop_id" placeholder="選擇店鋪" />
            <x-input label="倉庫名稱" wire:model="name" icon="o-archive-box" />
            <x-checkbox label="啟用此倉庫" wire:model="is_active" />
        </div>
        <x-slot:actions>
            <x-button label="取消" @click="$wire.warehouseModal = false" />
            <x-button label="儲存" wire:click="save" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-modal>
</div>