<div>
    <x-header title="通路管理" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋通路名稱或類型..." wire:model.live.debounce="search" icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
            <x-button label="新增通路" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card padding="none">
        {{-- PC 端：顯示完整表格 --}}
        <div class="hidden md:block">
            <x-table :headers="$headers" :rows="$channels" striped>
                @scope('cell_platform_fee_rate', $channel)
                    {{ number_format($channel->platform_fee_rate * 100, 2) }} %
                @endscope

                @scope('cell_is_active', $channel)
                    <x-badge :value="$channel->is_active ? '啟用中' : '已停用'" :class="$channel->is_active ? 'badge-success' : 'badge-ghost'" />
                @endscope

                @scope('cell_actions', $channel)
                    <div class="flex gap-2 justify-end">
                        <x-button icon="o-pencil" wire:click="edit({{ $channel->id }})" class="btn-sm btn-ghost text-primary" />
                        <x-button icon="o-trash" wire:click="delete({{ $channel->id }})" wire:confirm="確定刪除此通路？" class="btn-sm btn-ghost text-error" />
                    </div>
                @endscope
            </x-table>
        </div>

        {{-- 手機端：顯示卡片佈局 --}}
        <div class="md:hidden">
            @foreach($channels as $channel)
                <div class="p-4 border-b border-base-200 last:border-none">
                    <div class="flex justify-between items-start">
                        <div wire:click="edit({{ $channel->id }})" class="flex-1 cursor-pointer">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs text-gray-400 font-mono">#{{ $channel->id }}</span>
                                <x-badge :value="$channel->type" class="badge-outline badge-sm" />
                            </div>
                            <div class="text-lg font-bold">{{ $channel->name }}</div>
                            <div class="text-sm text-gray-500">
                                抽成率: {{ number_format($channel->platform_fee_rate * 100, 2) }} %
                            </div>
                        </div>
                        <div class="flex flex-col gap-2 items-end">
                            <x-badge :value="$channel->is_active ? '啟用' : '停用'" :class="$channel->is_active ? 'badge-success' : 'badge-ghost'" size="badge-sm" />
                            <div class="flex gap-1">
                                <x-button icon="o-pencil" wire:click="edit({{ $channel->id }})" class="btn-sm btn-ghost text-primary" />
                                <x-button icon="o-trash" wire:click="delete({{ $channel->id }})" wire:confirm="確定刪除？" class="btn-sm btn-ghost text-error" />
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            @if($channels->isEmpty())
                <div class="p-8 text-center text-gray-400">目前尚無通路資料</div>
            @endif
        </div>
    </x-card>

    {{-- 通路編輯/新增彈窗 --}}
    <x-modal wire:model="channelModal" separator>
        <x-slot:title>
            {{ $editingChannel ? '編輯通路' : '新增通路' }}
        </x-slot:title>

        <div class="grid gap-4">
            <x-input label="通路名稱" wire:model="name" placeholder="例如：蝦皮、官網、實體門市" autofocus />
            
            <div class="grid grid-cols-2 gap-4">
                <x-select 
                    label="類型" 
                    wire:model="type" 
                    :options="[['id' => 'online', 'name' => '線上 (Online)'], ['id' => 'offline', 'name' => '線下 (Offline)']]" 
                />
                <x-input 
                    label="平台手續費率 (0-1)" 
                    wire:model="platform_fee_rate" 
                    type="number" 
                    step="0.0001" 
                    hint="如 5% 請輸入 0.05"
                />
            </div>

            <x-checkbox label="是否啟用" wire:model="is_active" tight />
        </div>

        <x-slot:actions>
            <x-button label="取消" @click="$wire.channelModal = false" />
            <x-button label="儲存通路" wire:click="save" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-modal>
</div>