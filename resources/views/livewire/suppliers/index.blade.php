<div>
    <x-header title="供應商管理" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="新增供應商" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :headers="$headers" :rows="$suppliers" with-pagination>
            @scope('actions', $supplier)
                <div class="flex gap-2">
                    <x-button icon="o-pencil" wire:click="edit({{ $supplier->id }})" class="btn-sm btn-ghost" />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-modal wire:model="supplierModal" title="{{ $editingSupplier ? '編輯供應商' : '新增供應商' }}" separator>
        <div class="grid gap-4">
            <x-input label="名稱 *" wire:model="name" />
            <x-input label="聯繫人" wire:model="contact_person" />
            <x-input label="電話" wire:model="phone" />
			<div class="divider text-xs">社交聯繫方式</div>
			<div class="grid grid-cols-2 gap-2">
				<x-input label="WeChat" wire:model="social_contacts.wechat" icon="o-chat-bubble-left-right" />
				<x-input label="Line" wire:model="social_contacts.line" icon="o-chat-bubble-bottom-center-text" />
			</div>
			
			<x-input label="其他聯繫方式" wire:model="social_contacts.other" placeholder="例如：QQ 或 手機" />
			
			<x-textarea label="內部備註 (Notes)" wire:model="internal_notes" rows="4" placeholder="記錄供應商的信譽、配合度等雜項..." />
        </div>
        <x-slot:actions>
            <x-button label="取消" @click="$wire.supplierModal = false" />
            <x-button label="儲存" wire:click="save" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-modal>
</div>