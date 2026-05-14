{{-- resources/views/livewire/settings/accounting-rules/index.blade.php --}}
<div>
    <x-header title="自動過帳規則" subtitle="定義各事件對應的會計分錄" separator>
        <x-slot:actions>
            <x-input wire:model.live="search" placeholder="搜尋事件類型..." icon="o-magnifying-glass" />
            <x-button label="新增規則" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card padding="none">
        {{-- PC 端表格：加入 @row-click 與 cursor-pointer --}}
        <div class="hidden md:block">
            <x-table :headers="$headers" :rows="$rows" @row-click="$wire.edit($event.detail.id)" class="cursor-pointer">
                @scope('cell_lines_summary', $item)
                    <div class="text-sm space-y-0.5">
                        @foreach($item->lines as $line)
                            <div>
                                <span class="badge {{ $line->entry_type === 'debit' ? 'badge-success' : 'badge-error' }} text-xs">
                                    {{ $line->entry_type === 'debit' ? '借' : '貸' }}
                                </span>
                                <span class="font-mono text-xs">{{ $line->account->code }}</span>
                                <span class="text-gray-600">{{ $line->account->name }}</span>
                                <span class="text-gray-400 text-xs">({{ $line->amount_source }})</span>
                            </div>
                        @endforeach
                    </div>
                @endscope

                @scope('cell_status', $item)                   
					<x-checkbox wire:click.stop="toggleActive({{ $item->id }})" :checked="$item->is_active" class="checkbox-primary" tight />
                @endscope

                @scope('cell_actions', $item)
                    <div class="flex gap-2 justify-end">
                        <x-button icon="o-pencil" wire:click.stop="edit({{ $item->id }})" class="btn-sm btn-ghost text-primary" />
                    </div>
                @endscope
            </x-table>
        </div>

        {{-- 手機端卡片：點擊整塊區域皆可修改 --}}
        <div class="md:hidden">
            @foreach($rows as $item)
                <div class="p-4 border-b border-base-200 last:border-none active:bg-base-200 cursor-pointer" wire:click="edit({{ $item->id }})">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="font-mono font-bold text-primary">{{ $item->event_type }}</div>
                            <div class="text-sm mt-1 space-y-0.5">
                                @foreach($item->lines as $line)
                                    <div>
                                        <span class="badge {{ $line->entry_type === 'debit' ? 'badge-success' : 'badge-error' }} text-xs">
                                            {{ $line->entry_type === 'debit' ? '借' : '貸' }}
                                        </span>
                                        {{ $line->account->name }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex gap-1">
                            <x-button icon="o-pencil" class="btn-sm btn-ghost text-primary" />
                        </div>
                    </div>
                </div>
            @endforeach

            @if($rows->isEmpty())
                <div class="p-8 text-center text-gray-400">目前沒有過帳規則</div>
            @endif
        </div>
    </x-card>

    {{-- 新增/編輯 Modal --}}
    <x-modal wire:model="myModal" separator size="3xl" persistent>
        <x-slot:title>
            {{ $editingItem ? '編輯規則' : '新增規則' }}
        </x-slot:title>

        <div class="space-y-4">
            <x-input label="事件類型" wire:model="event_type" :readonly="$editingItem !== null" 
                :class="$editingItem ? 'bg-base-200' : ''" placeholder="例如：sale_cash, purchase_inbound" required />

            <div class="flex justify-between items-center">
                <span class="font-bold">分錄明細</span>
                <x-button label="增加一行" icon="o-plus" wire:click="addLine" class="btn-sm btn-outline" />
            </div>

            <div class="space-y-2">
                @foreach($lines as $index => $line)
        {{-- 加入動態邊框色，讓整行更有感 --}}
        <div class="grid grid-cols-12 gap-2 items-center p-2 bg-base-100 rounded-lg border-l-4 {{ ($line['entry_type'] ?? '') === 'debit' ? 'border-l-success' : 'border-l-error' }} border-y border-r border-base-300">
            
            <div class="col-span-4">
                <x-select wire:model="lines.{{ $index }}.account_id" 
                    :options="$accounts" option-label="name" option-sub-label="code"
                    placeholder="選擇科目" size="sm" />
            </div>

            <div class="col-span-2">
                {{-- 借貸選單：加入屬性改變文字顏色 --}}
                <x-select 
                    wire:model.live="lines.{{ $index }}.entry_type" 
                    :options="$entryTypes" 
                    size="sm"
                    class="{{ ($line['entry_type'] ?? '') === 'debit' ? 'text-success font-bold' : 'text-error font-bold' }}"
                />
            </div>

            <div class="col-span-3">
                <x-select wire:model="lines.{{ $index }}.amount_source" 
                    :options="$amountSources" option-label="label" option-value="value"
                    placeholder="金額來源" size="sm" />
            </div>

            <div class="col-span-2">
                <x-input wire:model="lines.{{ $index }}.ratio" type="number" step="0.01" 
                    label="比例" class="input-sm" />
            </div>

            <div class="col-span-1 text-right">
                <x-button icon="o-trash" wire:click="removeLine({{ $index }})" 
                    class="btn-ghost btn-sm text-error" />
            </div>
        </div>
    @endforeach
            </div>
        </div>

        <x-slot:actions>
            <x-button label="取消" @click="$wire.myModal = false" />
            <x-button label="儲存" wire:click="save" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-modal>
</div>