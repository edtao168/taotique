<div>
    <x-header title="倉庫管理" subtitle="管理各分店的庫存存放點" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋倉庫..." wire:model.live.debounce="search" icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="新增倉庫" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :headers="$headers" :rows="$warehouses">
			{{-- 快速切換開關 --}}
			@scope('cell_is_active', $warehouse)
				<x-checkbox 
					wire:click="toggleActive({{ $warehouse->id }})" 
					:checked="$warehouse->is_active" 
					class="checkbox-primary"
					tight
				/>
				{{-- 或者如果你喜歡開關樣式，可以使用 Mary UI 的 toggle --}}
				{{-- <x-toggle wire:click="toggleActive({{ $warehouse->id }})" :checked="$warehouse->is_active" class="toggle-primary" /> --}}
			@endscope

			@scope('cell_actions', $warehouse)
				<div class="flex gap-2">
					<x-button icon="o-pencil" wire:click="edit({{ $warehouse->id }})" class="btn-sm btn-ghost text-blue-500" />
					
					{{-- 增加一個簡單的刪除確認，如果沒資料關聯的話 --}}
					<x-button 
						icon="o-trash" 
						wire:click="delete({{ $warehouse->id }})" 
						wire:confirm="確定要刪除此倉庫嗎？" 
						class="btn-sm btn-ghost text-error" 
					/>
				</div>
			@endscope
		</x-table>
    </x-card>

    <x-modal wire:model="warehouseModal" title="{{ $editingWarehouse ? '編輯倉庫' : '新增倉庫' }}" separator>
        <div class="grid gap-4">
            <x-select label="所屬店鋪" icon="o-building-storefront" :options="$shops" wire:model="shop_id" placeholder="選擇店鋪" />
            <x-input label="倉庫名稱" wire:model="name" icon="o-archive-box" />
            <x-checkbox label="啟用此倉庫" wire:model="is_active" />
        </div>

        <x-slot:actions>
            <x-button label="取消" @click="$wire.warehouseModal = false" />
            <x-button label="儲存" wire:click="save" class="btn-primary" />
        </x-slot:actions>
    </x-modal>
</div>