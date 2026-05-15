<div>
    <x-header title="營業點管理" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋..." wire:model.live.debounce="search" icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
            {{-- 呼叫 create 方法以重置狀態 --}}
            <x-button label="新增營業點" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card padding="none">
        {{-- PC 端：顯示完整表格 --}}
        <div class="hidden md:block">
            <x-table :headers="$headers" :rows="$shops" striped>
                @scope('cell_actions', $shop)
                    <div class="flex gap-2 justify-end">
                        <x-button icon="o-pencil" wire:click="edit({{ $shop->id }})" class="btn-sm btn-ghost text-primary" />
                        <x-button icon="o-trash" wire:click="delete({{ $shop->id }})" wire:confirm="確定刪除？" class="btn-sm btn-ghost text-error" />
                    </div>
                @endscope
            </x-table>
        </div>

        {{-- 手機端：顯示卡片佈局 --}}
        <div class="md:hidden">
            @foreach($shops as $shop)
                <div class="p-4 border-b border-base-200 last:border-none">
                    <div class="flex justify-between items-center">
                        <div wire:click="edit({{ $shop->id }})" class="flex-1 cursor-pointer">
                            <span class="text-xs text-gray-400 font-mono mr-2">#{{ $shop->id }}</span>
                            <span class="text-lg font-bold">{{ $shop->name }}</span>
                        </div>
                        <div class="flex gap-1">
                            <x-button icon="o-pencil" wire:click="edit({{ $shop->id }})" class="btn-sm btn-ghost text-primary" />
                            <x-button icon="o-trash" wire:click="delete({{ $shop->id }})" wire:confirm="確定刪除？" class="btn-sm btn-ghost text-error" />
                        </div>
                    </div>
                </div>
            @endforeach

            @if($shops->isEmpty())
                <div class="p-8 text-center text-gray-400">目前尚無營業點資料</div>
            @endif
        </div>
    </x-card>

    <x-modal wire:model="shopModal" separator>
        <x-slot:title>
            {{ $editingShop ? '編輯營業點' : '新增營業點' }}
        </x-slot:title>

        <div class="grid gap-4">
            <x-input label="營業點名稱" wire:model="name" autofocus />
        </div>

        <x-slot:actions>
            <x-button label="取消" @click="$wire.shopModal = false" />
            <x-button label="儲存" wire:click="save" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-modal>
</div>