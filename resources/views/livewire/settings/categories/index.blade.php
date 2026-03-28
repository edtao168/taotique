<div>
    <x-header title="產品類別設定" subtitle="管理 SKU 編碼的第一碼定義：大類" separator>
        <x-slot:actions>
            <x-input wire:model.live="search" placeholder="搜尋..." icon="o-magnifying-glass" />
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
            {{-- 改呼叫 create 方法 --}}
            <x-button label="新增類別" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card padding="none">
        {{-- PC 端表格 --}}
        <div class="hidden md:block">
            <x-table :headers="$headers" :rows="$rows" class="cursor-pointer">
                @scope('cell_actions', $item)
                    <div class="flex gap-2 justify-end">
                        {{-- 傳入字串 code 需加單引號 --}}
                        <x-button icon="o-pencil" wire:click.stop="edit('{{ $item->code }}')" class="btn-sm btn-ghost text-primary" />
                        <x-button icon="o-trash" wire:click.stop="delete('{{ $item->code }}')" 
                            wire:confirm="確定要刪除嗎？" class="btn-sm btn-ghost text-error" />
                    </div>
                @endscope
            </x-table>
        </div>

        {{-- 手機端卡片 --}}
        <div class="md:hidden">
            @foreach($rows as $item)
                <div class="p-4 border-b border-base-200 last:border-none">
                    <div class="flex justify-between items-center">
                        <div wire:click="edit('{{ $item->code }}')" class="badge badge-primary font-mono">
                            <span class="font-mono text-primary font-bold">[{{ $item->code }}]</span>
                            <span class="ml-2">{{ $item->name }}</span>
                            @if($item->remark)
                                <p class="text-xs text-gray-400 mt-1">{{ $item->remark }}</p>
                            @endif
                        </div>
                        <div class="flex gap-1">
                            <x-button icon="o-pencil" wire:click="edit('{{ $item->code }}')" class="btn-sm btn-ghost text-primary" />
                            <x-button icon="o-trash" wire:click="delete('{{ $item->code }}')" wire:confirm="確定要刪除嗎？" class="btn-sm btn-ghost text-error" />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-card>

    <x-modal wire:model="myModal" separator>
        <x-slot:title>
            {{ $editingItem ? '修改類別' : '新增類別' }}
        </x-slot:title>

        <div class="grid gap-4">
            {{-- 根據是否存在 editingItem 切換唯讀狀態 --}}
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