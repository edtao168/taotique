{{-- resources/views/livewire/settings/accounting-rules/index.blade.php --}}
<div>
    <x-header title="自動過帳規則" subtitle="定義各事件對應的會計分錄" separator>
        <x-slot:actions>
            <x-input wire:model.live="search" placeholder="搜尋事件類型..." icon="o-magnifying-glass" />
            <x-button label="新增規則" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card padding="none">
        {{-- PC 端表格 --}}
        <div class="hidden md:block">
            <x-table :headers="$headers" :rows="$rows" @row-click="$wire.edit($event.detail.id)" sticky class="cursor-pointer">
                @scope('cell_lines_summary', $item)
                    <div class="text-sm">
                        @foreach($item->lines as $line)
                            @php
                                // 使用快取的科目映射或動態解析
                                if ($line->is_dynamic) {
                                    $displayType = 'dynamic';
                                    $displaySpec = substr($line->account_code, 8);
                                } elseif ($line->account_id && $line->account) {
                                    $displayType = 'account';
                                    $displayCode = $line->account->code;
                                    $displayName = $line->account->name;
                                } elseif ($line->account_code && isset($codeToAccountMap[$line->account_code])) {
                                    $displayType = 'account';
                                    $displayCode = $codeToAccountMap[$line->account_code]->code;
                                    $displayName = $codeToAccountMap[$line->account_code]->name;
                                } else {
                                    $displayType = 'missing';
                                    $displayCode = $line->account_code ?? 'N/A';
                                }
                            @endphp
                            
                            <div class="py-0.5">
                                <span class="font-mono text-xs font-semibold {{ $line->entry_type === 'debit' ? 'text-primary' : 'text-error' }}">
                                    {{ $line->entry_type === 'debit' ? '借' : '貸' }}
                                </span>
                                <span class="ml-1">
                                    @if($displayType === 'dynamic')
                                        <span class="text-purple-600 font-medium">[全動態: {{ $displaySpec }}]</span>
                                    @elseif($displayType === 'account')
                                        <span class="font-mono text-gray-600">{{ $displayCode }}</span> - {{ $displayName }}
                                    @else
                                        <span class="text-error">⚠️ 帳戶不存在 ({{ $displayCode }})</span>
                                    @endif
                                </span>
                                @if($line->amount_source)
                                    <span class="text-xs text-gray-400 ml-1">({{ $line->amount_source }})</span>
                                @endif
                                @if($line->ratio != 1)
                                    <span class="text-xs text-gray-400">×{{ $line->ratio }}</span>
                                @endif
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

        {{-- 手機端卡片 --}}
        <div class="md:hidden">
            @foreach($rows as $item)
                <div class="card bg-base-100 border border-base-300 mb-3">
                    <div class="card-body p-3">
                        <div class="font-mono font-bold text-sm">{{ $item->event_type }}</div>
                        <div class="divider my-1"></div>
                        @foreach($item->lines as $line)
                            @php
                                if ($line->is_dynamic) {
                                    $displayText = '[動態: ' . substr($line->account_code, 8) . ']';
                                    $displayClass = 'text-purple-600';
                                } elseif ($line->account_id && $line->account) {
                                    $displayText = $line->account->code . ' - ' . $line->account->name;
                                    $displayClass = 'text-gray-600';
                                } elseif ($line->account_code && isset($codeToAccountMap[$line->account_code])) {
                                    $displayText = $codeToAccountMap[$line->account_code]->code . ' - ' . $codeToAccountMap[$line->account_code]->name;
                                    $displayClass = 'text-gray-600';
                                } else {
                                    $displayText = '⚠️ 帳戶不存在 (' . ($line->account_code ?? 'N/A') . ')';
                                    $displayClass = 'text-gray-400';
                                }
                            @endphp
                            <div class="py-1">
                                <span class="badge {{ $line->entry_type === 'debit' ? 'badge-primary' : 'badge-error' }} text-xs">
                                    {{ $line->entry_type === 'debit' ? '借' : '貸' }}
                                </span>
                                <span class="{{ $displayClass }} text-sm ml-1">{{ $displayText }}</span>
                                @if($line->amount_source)
                                    <span class="text-xs text-gray-400 ml-1">({{ $line->amount_source }})</span>
                                @endif
                                @if($line->ratio != 1)
                                    <span class="text-xs text-gray-400">×{{ $line->ratio }}</span>
                                @endif
                            </div>
                        @endforeach
                        <div class="flex justify-end mt-2">
                            <x-checkbox wire:click.stop="toggleActive({{ $item->id }})" :checked="$item->is_active" label="啟用" class="checkbox-primary" tight />
                            <x-button icon="o-pencil" wire:click.stop="edit({{ $item->id }})" class="btn-xs btn-ghost ml-2" />
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
    <x-modal wire:model="myModal" separator size="4xl" persistent>
        <x-slot:title>
            {{ $editingItem ? '編輯規則' : '新增規則' }}
        </x-slot:title>

        <div class="space-y-4">
            <x-input label="事件類型" wire:model="event_type" :readonly="$editingItem !== null" 
                :class="$editingItem ? 'bg-base-200' : ''" placeholder="例如：sale_revenue_retail, sale_cost" required />

            <div class="flex justify-between items-center">
                <span class="font-bold">分錄明細</span>
                <x-button label="增加分錄線" icon="o-plus" wire:click="addLine" class="btn-sm btn-outline btn-primary" />
            </div>
            
            {{-- 表頭 --}}
            <div class="grid grid-cols-12 gap-2 px-2 py-2 bg-base-200 rounded-lg text-xs font-semibold text-gray-500">
                <div class="col-span-5">輸入科目id、名稱、或動態路由</div>
                <div class="col-span-2">借貸方向</div>
                <div class="col-span-2">金額來源</div>
                <div class="col-span-2">比例</div>
                <div class="col-span-1 text-right">刪除</div>
            </div>
            
            {{-- 明細行 --}}
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @foreach($lines as $index => $line)
                    <div class="grid grid-cols-12 gap-2 items-center p-2 bg-base-100 rounded-lg border-l-4 {{ ($line['entry_type'] ?? '') === 'debit' ? 'border-l-primary' : 'border-l-error' }} border-y border-r border-base-300">
                        
                        <div class="col-span-5">
                            <x-select 
                                wire:model="lines.{{ $index }}.combined_value"
                                :options="$comboOptions"
                                search-function="searchAccounts"
                                debounce="300ms"
                                min-chars="0"
                                single
                                size="sm"
                                class="text-xs"
                            />
                        </div>

                        <div class="col-span-2">
                            <x-select 
                                wire:model.live="lines.{{ $index }}.entry_type" 
                                :options="$entryTypes" 
                                option-label="label"
                                option-value="value"
                                size="sm"
                                class="{{ ($line['entry_type'] ?? '') === 'debit' ? 'text-primary font-bold text-xs' : 'text-error font-bold text-xs' }}"
                            />
                        </div>

                        <div class="col-span-2">
                            <x-select 
                                wire:model="lines.{{ $index }}.amount_source" 
                                :options="$amountSources" 
                                option-label="label" 
                                option-value="value"
                                placeholder="來源" 
                                size="sm" 
                                class="text-xs"
                            />                            
                        </div>
                        
                        <div class="col-span-2">
                            <x-input wire:model="lines.{{ $index }}.ratio" type="number" step="0.1" size="sm" class="text-xs" title="比率" />
                        </div>
                        
                        <div class="col-span-1 text-right">
                            <x-button icon="o-trash" wire:click="removeLine({{ $index }})" class="btn-ghost btn-sm text-error" />
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