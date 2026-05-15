<div>
    
    <x-header title="材質定義設定" subtitle="管理 SKU 編碼第 2~4 碼定義：材質" separator>
        <x-slot:actions>
            <x-input wire:model.live="search" placeholder="搜尋材質名稱或代碼 (不含細分)..." icon="o-magnifying-glass" />
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
			<x-button label="新增材質" wire:click="$set('myModal', true)" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card padding="none">
        {{-- PC 端：表格顯示 --}}
        <div class="hidden md:block">
            <x-table :headers="$headers" :rows="$rows" @row-click="$wire.edit($event.detail.id)" class="cursor-pointer">
                @scope('cell_actions', $item)
                    <div class="flex gap-2 justify-end">
                        <x-button icon="o-pencil" wire:click.stop="edit({{ $item->id }})" class="btn-sm btn-ghost text-primary" />
                        <x-button icon="o-trash" wire:click.stop="delete({{ $item->id }})" 
                            wire:confirm="確定要刪除嗎？" class="btn-sm btn-ghost text-error" />
                    </div>
                @endscope
            </x-table>
        </div>

        {{-- 手機端：卡片佈局 --}}
        <div class="md:hidden">
            @foreach($rows as $item)
                <div class="p-4 border-b border-base-200 last:border-none">
                    <div class="flex justify-between items-start">
                        <div wire:click="edit({{ $item->id }})" class="flex-1 cursor-pointer">
                            <div class="flex items-center gap-2">
                                <span class="badge badge-primary font-mono font-bold">{{ $item->bb_code }}{{ $item->c_code }}</span>
                                <span class="text-lg font-bold">{{ $item->name }}</span>
                            </div>
                            <div class="text-sm text-gray-500 mt-1">
                                <span class="opacity-70">市場名稱：</span>{{ $item->market_names ?: '無' }}
                            </div>
                        </div>
                        <div class="flex gap-1">
                            <x-button icon="o-pencil" wire:click="edit({{ $item->id }})" class="btn-sm btn-ghost text-primary" />
                            <x-button icon="o-trash" wire:click="delete({{ $item->id }})" 
                                wire:confirm="確定要刪除嗎？" class="btn-sm btn-ghost text-error" />
                        </div>
                    </div>
                </div>
            @endforeach
            
            @if($rows->isEmpty())
                <div class="p-8 text-center text-gray-400">目前沒有符合的材質定義</div>
            @endif
        </div>
    </x-card>

    {{-- 將 Modal 移進根 div 裡面 --}}
    <x-modal wire:model="myModal" separator>
        <x-slot:title>
            {{ $editingItem ? '修改材質定義' : '新增材質定義' }}
        </x-slot:title>
        
        <div class="grid gap-4">
            <x-input label="材質代碼 (2碼)" wire:model="bb_code" maxlength="2" />
            <x-input label="細分代碼 (1碼)" wire:model="c_code" maxlength="1" />
            <x-input label="材質名稱" wire:model="name" />
            <x-input label="商業名稱" wire:model="market_names" />
        </div>

        <x-slot:actions>
            <x-button label="取消" @click="$wire.myModal = false" />
            <x-button label="儲存" wire:click="save" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-modal>

</div>