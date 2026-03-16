<div>
    <x-header title="供應商管理" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
			<x-button label="新增供應商" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :headers="$headers" :rows="$suppliers" with-pagination @row-click="$wire.showDetails($event.detail.id)">
			@scope('actions', $supplier)
                <div class="flex gap-2">
                    {{-- 編輯按鈕，加上 @click.stop 防止冒泡觸發 row-click --}}
                    <x-button 
                        icon="o-pencil" 
                        wire:click="edit({{ $supplier->id }})" 
                        @click.stop="" 
                        class="btn-sm btn-ghost text-blue-500" 
                        tooltip="編輯"
                    />                    
                    
                    <x-button 
                        icon="o-trash" 
                        wire:click="delete({{ $supplier->id }})" 
                        wire:confirm="確定要刪除此供應商？"
                        @click.stop="" 
                        class="btn-sm btn-ghost text-red-500"
                        tooltip="刪除"
                    />

                </div>
            @endscope
        </x-table>
    </x-card>

    <x-drawer wire:model="drawer" title="{{ $isReadOnly ? '供應商詳情' : ($editingSupplier ? '編輯供應商' : '新增供應商') }}" right separator with-close-button class="w-11/12 lg:w-1/3">
        <x-form wire:submit="save">
            <x-input label="名稱 *" wire:model="name" :readonly="$isReadOnly" />
            <x-input label="聯繫人" wire:model="contact_person" :readonly="$isReadOnly" />
            <x-input label="電話" wire:model="phone" :readonly="$isReadOnly" />
            
            <div class="divider text-xs">社交聯繫方式</div>
            <div class="grid grid-cols-2 gap-2">
                <x-input label="WeChat" wire:model="contact_json.wechat" icon="o-chat-bubble-left-right" :readonly="$isReadOnly" />
                <x-input label="Line" wire:model="contact_json.line" icon="o-chat-bubble-bottom-center-text" :readonly="$isReadOnly" />
            </div>
            
            <x-input label="其他聯繫方式" wire:model="contact_json.other" :readonly="$isReadOnly" />
            <x-textarea label="內部備註" wire:model="notes" rows="4" :readonly="$isReadOnly" />

            <x-slot:actions>
                <x-button label="關閉" @click="$wire.drawer = false" />
                @if(!$isReadOnly)
                    <x-button label="儲存供應商" type="submit" icon="o-check" class="btn-primary" spinner="save" />
                @endif
            </x-slot:actions>
        </x-form>
    </x-drawer>
</div>