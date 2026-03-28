<div>
    <x-header title="產品類別設定" subtitle="管理 SKU 編碼的第一碼定義：大類" separator>
        <x-slot:actions>
            <x-input wire:model.live="search" placeholder="搜尋..." icon="o-magnifying-glass" />
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
			<x-button label="新增類別" wire:click="$set('myModal', true)" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card padding="none">
        {{-- PC 端：顯示完整表格 --}}
        <div class="hidden md:block">
            <x-table :headers="$headers" :rows="$rows" striped>
                @scope('cell_actions', $item)
                    <div class="flex gap-2">
                        <x-button icon="o-pencil" wire:click="edit('{{ $item->code }}')" class="btn-sm btn-ghost text-primary" />
                        <x-button icon="o-trash" wire:click="delete('{{ $item->code }}')" 
                            wire:confirm="確定要刪除嗎？" class="btn-sm btn-ghost text-error" />
                    </div>
                @endscope
            </x-table>
        </div>

        {{-- 手機端：顯示卡片式佈局 --}}
        <div class="md:hidden">
            @foreach($rows as $item)
                <div class="p-4 border-b border-base-200 last:border-none">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <span class="badge badge-primary font-mono">{{ $item->code }}</span>
                            <span class="text-lg font-bold ml-2">{{ $item->name }}</span>
                        </div>
                        <div class="flex gap-1">
                            <x-button icon="o-pencil" wire:click="edit('{{ $item->code }}')" class="btn-xs btn-ghost text-primary" />
                            <x-button icon="o-trash" wire:click="delete('{{ $item->code }}')" 
                                wire:confirm="確定要刪除嗎？" class="btn-xs btn-ghost text-error" />
                        </div>
                    </div>
                    @if($item->remark)
                        <div class="text-sm text-gray-500 italic">
                            {{ $item->remark }}
                        </div>
                    @endif
                </div>
            @endforeach
            
            @if($rows->isEmpty())
                <div class="p-8 text-center text-gray-400">尚無資料</div>
            @endif
        </div>
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