<div>
    <x-header title="產品類別設定" subtitle="管理 SKU 編碼的第一碼定義：大類" separator>
        <x-slot:actions>
            <x-input wire:model.live="search" placeholder="搜尋..." icon="o-magnifying-glass" />
            <x-button label="新增類別" wire:click="$set('myModal', true)" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card padding="none">
        <x-table :headers="$headers" :rows="$rows" class="cursor-pointer">
            @scope('cell_actions', $item)
                <div class="flex gap-2 justify-end">
                    <x-button icon="o-pencil" wire:click.stop="edit({{ $item->code }})" class="btn-sm btn-ghost text-primary" />
                    <x-button icon="o-trash" wire:click.stop="delete({{ $item->code }})" 
                        wire:confirm="確定要刪除嗎？" class="btn-sm btn-ghost text-error" />
                </div>
            @endscope
        </x-table>
    </x-card>
	
	{{-- 將 Modal 移進根 div 裡面 --}}
    <x-modal wire:model="myModal" separator>
		<x-slot:title>
            {{ $editingItem ? '修改類別' : '新增類別' }}
        </x-slot:title>

        <div class="grid gap-4">
            <x-input label="大類" wire:model="code" :readonly="$editingItem !== null" :class="$editingItem ? 'bg-base-200' : ''" />
            <x-input label="類別名稱" wire:model="name" autofocus/>
			<x-input label="備註" wire:model="remark" />
        </div>
        <x-slot:actions>
            <x-button label="取消" @click="$wire.myModal = false" />
            <x-button label="儲存" wire:click="save" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-modal>
</div>