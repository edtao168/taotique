<div>
    <x-header title="營業點管理" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋..." wire:model.live.debounce="search" icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="新增營業點" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :headers="$headers" :rows="$shops">
            @scope('cell_actions', $shop)
                <div class="flex gap-2">
                    <x-button icon="o-pencil" wire:click="edit({{ $shop->id }})" class="btn-sm btn-ghost" />
                    <x-button icon="o-trash" wire:click="delete({{ $shop->id }})" wire:confirm="確定刪除？" class="btn-sm btn-ghost text-error" />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-modal wire:model="shopModal" title="{{ $editingShop ? '編輯' : '新增' }}" separator>
        <x-input label="營業點名稱" wire:model="name" />
        <x-slot:actions>
            <x-button label="取消" @click="$wire.shopModal = false" />
            <x-button label="儲storing" wire:click="save" class="btn-primary" />
        </x-slot:actions>
    </x-modal>
</div>