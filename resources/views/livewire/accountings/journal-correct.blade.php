<div>
    <x-header 
        title="🔧 更正日記帳憑證" 
        subtitle="已過帳分錄不可直接修改，僅可產生差額更正分錄" 
        class="text-warning"
        separator 
    >
        <x-slot:actions>
            <x-button label="返回列表" icon="o-arrow-left" link="{{ route('accountings.journals.index') }}" class="btn-outline" />
            <x-button 
                label="📋 查看原始憑證" 
                icon="o-document-text" 
                @click="$wire.showOriginalDrawer = true" 
                class="btn-ghost btn-sm" 
            />
        </x-slot:actions>
    </x-header>

    {{-- 原始分錄資訊 --}}
    <div class="mt-4 p-4 bg-base-200 rounded-lg border-l-4 border-gray-400">
        <div class="text-sm text-gray-500 mb-2">📜 原始憑證資訊（唯讀）</div>
        
        {{-- 原始分錄表格 --}}
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr class="text-xs text-gray-500">
                        <th>科目</th>
                        <th>借方</th>
                        <th>貸方</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($originalItems as $item)
                        <tr class="text-sm">
                            <td>{{ $item['account_name'] }} <span class="text-xs text-gray-400">({{ $item['account_code'] }})</span></td>
                            <td class="font-mono">{{ $item['entry_type'] === 'debit' ? number_format($item['amount'], 4) : '-' }}</td>
                            <td class="font-mono">{{ $item['entry_type'] === 'credit' ? number_format($item['amount'], 4) : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-base-300">
                    @php
                        $originalTotalDebit = 0;
                        $originalTotalCredit = 0;
                        foreach($originalItems as $item) {
                            if($item['entry_type'] === 'debit') {
                                $originalTotalDebit += (float)$item['amount'];
                            } else {
                                $originalTotalCredit += (float)$item['amount'];
                            }
                        }
                    @endphp
                    <tr class="font-bold">
                        <td>合計</td>
                        <td class="font-mono">{{ number_format($originalTotalDebit, 4) }}</td>
                        <td class="font-mono">{{ number_format($originalTotalCredit, 4) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <x-form wire:submit="save" class="mt-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-datepicker label="📅 更正日期" wire:model="entry_date" icon="o-calendar" required />
            
            <div class="lg:col-span-2">
                <x-input 
                    label="更正原因（會計法必填）" 
                    wire:model="correction_reason" 
                    placeholder="例如：金額誤植、科目誤用、發票金額調整..." 
                    icon="o-exclamation-circle" 
                    required 
                />
            </div>

            <div class="lg:col-span-2">
                <x-input 
                    label="更正後摘要" 
                    wire:model="description" 
                    icon="o-chat-bubble-left" 
                />
            </div>
        </div>

        {{-- 多科目編輯表格 --}}
        <div class="mt-6">
            <div class="flex items-center justify-between mb-3">
                <div class="font-bold text-base">📝 更正後分錄項目</div>
                <x-button 
                    label="➕ 新增科目" 
                    icon="o-plus" 
                    @click="$wire.addItem()" 
                    class="btn-sm btn-ghost" 
                />
            </div>
            
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr class="text-sm">
                            <th>借/貸</th>
                            <th>會計科目</th>
                            <th class="text-right">金額</th>
                            <th class="text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($correctedItems as $index => $item)
                            <tr>
                                <td class="w-24">
                                    <x-select 
                                        wire:model="correctedItems.{{ $index }}.entry_type" 
                                        :options="[
                                            ['id' => 'debit', 'name' => '借方'],
                                            ['id' => 'credit', 'name' => '貸方'],
                                        ]"
                                        option-label="name"
                                        option-value="id"
                                        class="select-sm"
                                    />
                                </td>
                                <td>
                                    <x-select 
                                        wire:model="correctedItems.{{ $index }}.account_code" 
                                        :options="$availableAccounts"
                                        option-label="name"
                                        option-value="code"
                                        searchable
                                        placeholder="選擇科目"
                                        class="select-sm w-full"
                                    />
                                </td>
                                <td class="w-48">
                                    <x-input 
                                        wire:model="correctedItems.{{ $index }}.amount" 
                                        type="number" 
                                        step="0.0001" 
                                        placeholder="0.0000"
                                        class="input-sm text-right"
                                    />
                                </td>
                                <td class="text-center w-16">
                                    <x-button 
                                        icon="o-trash" 
                                        class="btn-sm btn-ghost text-error"
                                        @click="$wire.removeItem({{ $index }})"
                                        spinner
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-base-300">
                        @php
                            $totalDebit = 0;
                            $totalCredit = 0;
                            foreach($correctedItems as $item) {
                                if($item['entry_type'] === 'debit') {
                                    $totalDebit += (float)($item['amount'] ?? 0);
                                } else {
                                    $totalCredit += (float)($item['amount'] ?? 0);
                                }
                            }
                            $isBalanced = abs($totalDebit - $totalCredit) < 0.0001;
                        @endphp
                        <tr class="font-bold">
                            <td colspan="2" class="text-right">合計：</td>
                            <td class="text-right">
                                <span class="text-success">借 {{ number_format($totalDebit, 4) }}</span>
                                <span class="text-error"> / 貸 {{ number_format($totalCredit, 4) }}</span>
                                @if(!$isBalanced)
                                    <span class="badge badge-error badge-sm ml-2">不平衡！</span>
                                @endif
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- 差額分錄預覽 --}}
        @if(count($diff_lines) > 0)
            <div class="mt-8">
                <div class="mb-4 p-4 bg-info/10 rounded-xl border border-info/30">
                    <div class="flex items-start gap-3">
                        <x-icon name="o-light-bulb" class="text-info w-6 h-6 mt-0.5" />
                        <div>
                            <div class="font-bold text-info mb-1">💡 系統將進行以下更正：</div>
                            <div class="text-sm text-gray-700 leading-relaxed">
                                {{ $correction_summary }}
                            </div>
                            <div class="mt-2 text-xs text-gray-500">
                                原始分錄會保留不動，系統會自動產生一筆「更正分錄」來調整帳務。
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 視覺化分錄預覽 --}}
                <div class="bg-base-100 rounded-xl border shadow-sm overflow-hidden">
                    <div class="px-5 py-3 bg-warning/10 border-b border-warning/20 flex items-center justify-between">
                        <div class="font-bold text-warning flex items-center gap-2">
                            <x-icon name="o-eye" />
                            更正分錄預覽
                        </div>
                    </div>
                    
                    <div class="p-5 space-y-3">
                        @foreach($diff_lines as $line)
                            <div class="flex items-center gap-3 p-3 rounded-lg border-l-4 {{ $line['action'] === 'cancel' ? 'bg-error/5 border-error/50' : 'bg-success/5 border-success/50' }}">
                                <div class="flex-shrink-0">
                                    <x-icon name="{{ $line['icon'] }}" class="w-5 h-5 {{ $line['action'] === 'cancel' ? 'text-error' : 'text-success' }}" />
                                </div>
                                <div class="flex-grow min-w-0">
                                    <div class="flex items-baseline gap-2 flex-wrap">
                                        <span class="text-xs font-bold px-2 py-0.5 rounded {{ $line['action'] === 'cancel' ? 'bg-error/10 text-error' : 'bg-success/10 text-success' }}">
                                            {{ $line['action_label'] }}
                                        </span>
                                        <span class="font-medium text-sm">{{ $line['account_name'] }}</span>
                                        <span class="text-xs text-gray-400 font-mono">({{ $line['account_code'] }})</span>
                                    </div>
                                </div>
                                <div class="flex-shrink-0 text-right">
                                    <div class="font-bold font-mono text-sm {{ $line['action'] === 'cancel' ? 'text-error' : 'text-success' }}">
                                        {{ $line['action'] === 'cancel' ? '-' : '+' }}{{ number_format($line['amount'], 4) }}
                                    </div>
                                    <div class="text-xs text-gray-400">{{ $line['entry_type'] === 'debit' ? '借' : '貸' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <div class="mt-8 p-4 bg-info/10 rounded-lg text-info text-center">
                <x-icon name="o-information-circle" />
                <span>修改金額或科目後將自動產生更正分錄。</span>
            </div>
        @endif

        <x-slot:actions>
            <x-button label="取消" link="{{ route('accountings.journals.index') }}" />
            <x-button 
                label="✅ 確認產生更正分錄" 
                type="submit" 
                class="btn-warning" 
                spinner="save"
                wire:loading.attr="disabled"
                :disabled="count($diff_lines) === 0"
            />
        </x-slot:actions>
    </x-form>
    
    <x-drawer wire:model="showOriginalDrawer" title="原始憑證詳情" right separator class="w-11/12 lg:w-1/3">
        @include('livewire.accountings.includes._journal-detail', ['journal' => $originalJournal])
    </x-drawer>
</div>