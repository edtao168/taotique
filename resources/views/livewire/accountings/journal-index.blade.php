{{-- 檔案路徑與檔名：resources/views/livewire/accountings/journal-index.blade.php --}}
<div>    
    <x-header title="日記帳查詢" subtitle="所有業務自動產生的分錄流水" separator progress-indicator>
        <x-slot:actions>
            <x-input placeholder="搜尋描述..." wire:model.live.debounce="search" icon="o-magnifying-glass" />
            <x-button label="新增草稿" icon="o-plus" link="{{ route('accountings.journals.create') }}" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- 🖥️ 桌面版 PC 端表格 --}}
    <div class="hidden lg:block">
        <x-card>
            {{-- 表頭 --}}
            <div class="grid grid-cols-12 gap-4 px-6 py-3 text-xs text-gray-500 border-b border-base-300">
                <div class="col-span-1 text-center">ID</div>
                <div class="col-span-1 text-center">日期 / 狀態</div>
                <div class="col-span-6">摘要 / 分錄明細</div>
                <div class="col-span-2 text-right">借方</div>
                <div class="col-span-2 text-right">貸方</div>
            </div>
            
            {{-- 資料行 --}}
            @foreach($journals as $journal)
                <div class="grid grid-cols-12 gap-4 px-6 py-4 border-b border-base-200 hover:bg-base-100/50 items-center cursor-pointer"
                     wire:click="selectJournal({{ $journal->id }})">
                    
                    <div class="col-span-1 text-center font-mono text-gray-400">
                        #{{ $journal->id }}
                    </div>
                    
                    <div class="col-span-1 text-center flex flex-col items-center gap-1">
                        <span class="text-xs text-gray-500 font-mono">{{ $journal->entry_date->format('Y-m-d') }}</span>
                        {{-- 🆕 運用 Trait 動態屬性，自動渲染對應顏色與標籤 --}}
                        <x-badge :value="$journal->status_label" class="{{ $journal->status_color }} badge-xs" />
                    </div>
                    
                    <div class="col-span-6">
                        <div class="font-medium text-sm mb-1 text-gray-700">{{ $journal->description ?: '(無摘要)' }}</div>
                        <div class="text-xs text-gray-400 font-mono">
                            @if($journal->reference_type)
                                <span class="badge badge-outline badge-sm">{{ $journal->reference_type }}</span>
                            @endif
                        </div>
                    </div>
                    
                    <div class="col-span-2 text-right font-mono text-sm text-success">
                        {{ bccomp($journal->items->sum('debit'), '0', 2) > 0 ? number_format($journal->items->sum('debit'), 2) : '-' }}
                    </div>
                    
                    <div class="col-span-2 text-right font-mono text-sm text-error">
                        {{ bccomp($journal->items->sum('credit'), '0', 2) > 0 ? number_format($journal->items->sum('credit'), 2) : '-' }}
                    </div>
                </div>
            @endforeach
        </x-card>
    </div>

    {{-- 📱 手機端行動卡片 --}}
    <div class="block lg:hidden space-y-3">
        @foreach($journals as $journal)
            <x-card class="shadow-sm border border-base-200" wire:click="selectJournal({{ $journal->id }})">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs text-gray-400">#{{ $journal->id }}</span>
                        <span class="text-xs text-gray-500 font-mono">{{ $journal->entry_date->format('Y-m-d') }}</span>
                    </div>
                    {{-- 🆕 手機端同步改用 Trait 屬性 --}}
                    <x-badge :value="$journal->status_label" class="{{ $journal->status_color }} badge-sm" />
                </div>
                <div class="text-sm font-medium text-gray-800 mb-2">{{ $journal->description ?: '(無摘要)' }}</div>
                <div class="flex justify-between text-xs font-mono pt-2 border-t border-dashed border-base-200">
                    <span class="text-success">借: {{ number_format($journal->items->sum('debit'), 2) }}</span>
                    <span class="text-error">貸: {{ number_format($journal->items->sum('credit'), 2) }}</span>
                </div>
            </x-card>
        @endforeach
    </div>

    {{-- 分頁導航 --}}
    <div class="mt-4">
        {{ $journals->links() }}
    </div>

    {{-- 📑 右側詳細檢視抽屜 (Drawer) --}}
    <x-drawer wire:model="showDrawer" title="傳票詳細分錄" right class="w-full max-w-xl">
        @if($selectedJournal)
            <div class="space-y-6">
                {{-- 核心摘要資訊 --}}
                <div class="bg-base-200 p-4 rounded-lg space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">傳票編號</span>
                        <span class="font-mono font-bold">#{{ $selectedJournal->id }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">記帳日期</span>
                        <span class="font-mono">{{ $selectedJournal->entry_date->format('Y-m-d') }}</span>
                    </div>
                    <div class="flex justify-between text-sm items-center">
                        <span class="text-gray-500">目前狀態</span>
                        {{-- 🆕 抽屜內部亦使用 Trait 屬性 --}}
                        <x-badge :value="$selectedJournal->status_label" class="{{ $selectedJournal->status_color }} badge-sm" />
                    </div>
                    <div class="text-sm pt-2 border-t border-base-300">
                        <span class="text-gray-500 block mb-1">分錄摘要說明</span>
                        <div class="text-gray-800 font-medium">{{ $selectedJournal->description ?: '(無摘要)' }}</div>
                    </div>
                </div>

                {{-- 會計科目借貸明細列表 --}}
                <div>
                    <h4 class="text-xs font-bold text-gray-400 mb-2 uppercase tracking-wider">分錄帳目明細</h4>
                    <div class="border border-base-300 rounded-lg overflow-hidden bg-base-100">
                        <table class="table table-sm w-full font-mono text-xs">
                            <thead>
                                <tr class="bg-base-200">
                                    <th>科目代碼 / 名稱</th>
                                    <th class="text-right">借方 (Debit)</th>
                                    <th class="text-right">貸方 (Credit)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($selectedJournal->items as $item)
                                    <tr class="hover">
                                        <td>
                                            <div class="font-bold text-gray-700">{{ $item->account->code ?? '' }}</div>
                                            <div class="text-gray-400 text-[11px]">{{ $item->account->name ?? '' }}</div>
                                        </td>
                                        <td class="text-right text-success font-bold">
                                            {{ bccomp($item->debit, '0', 4) > 0 ? number_format($item->debit, 2) : '-' }}
                                        </td>
                                        <td class="text-right text-error font-bold">
                                            {{ bccomp($item->credit, '0', 4) > 0 ? number_format($item->credit, 2) : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- 🛠️ 動作按鈕防禦區塊 (配合 Enum 與 Trait 方法控制) --}}
                <div class="space-y-2 mt-4">
                    {{-- 🆕 1. 草稿狀態：顯示繼續編輯 --}}
                    @if($selectedJournal->isEditable())
                        <x-button 
                            label="✏️ 繼續編輯草稿" 
                            link="{{ route('accountings.journals.edit', $selectedJournal) }}" 
                            class="btn-primary w-full" 
                        />
                    @endif

                    {{-- 🆕 2. 已過帳 (APPROVED) 且尚未被更正過：顯示建立更正分錄 --}}
                    @if($selectedJournal->status === \App\Enums\WorkflowStatus::APPROVED && !$selectedJournal->is_corrected)
                        <x-button 
                            label="🔧 建立更正分錄" 
                            link="{{ route('accountings.journals.correct', $selectedJournal) }}" 
                            class="btn-warning w-full" 
                        />
                    @endif
                
                    {{-- 🆕 3. 已過帳 (APPROVED) 但已被更正過：顯示鎖定提示與來源 --}}
                    @if($selectedJournal->status === \App\Enums\WorkflowStatus::APPROVED && $selectedJournal->is_corrected)
                        <div class="p-3 bg-warning/10 rounded-lg text-center border border-warning/20">
                            <x-icon name="o-lock-closed" class="w-5 h-5 text-warning mx-auto mb-1" />
                            <div class="text-sm text-warning font-bold">此分錄已沖銷鎖定</div>
                            <div class="text-xs text-gray-500 mt-1">
                                已由更正傳票 #{{ $selectedJournal->hasCorrection->id ?? '?' }} 紅字沖銷，不可重複操作。
                            </div>
                        </div>
                    @endif

                    {{-- 🆕 4. 傳票本身就是更正單 (紅字沖銷單)：鎖定提示 --}}
                    @if($selectedJournal->reference_type == 'correct')
                        <div class="p-3 bg-info/10 rounded-lg text-center border border-info/20">
                            <x-icon name="o-lock-closed" class="w-5 h-5 text-info mx-auto mb-1" />
                            <div class="text-sm text-info font-bold">此為更正分錄</div>
                            <div class="text-xs text-gray-500 mt-1">
                                本單據為更正沖銷分錄，不可再次執行紅字更正。
                            </div>
                        </div>
                    @endif

                    {{-- 🆕 5. 已結帳 (COMPLETED)：全面鎖定歷史會計資料 --}}
                    @if($selectedJournal->status === \App\Enums\WorkflowStatus::COMPLETED)
                        <div class="p-3 bg-error/10 rounded-lg text-center border border-error/20">
                            <x-icon name="o-lock-closed" class="w-5 h-5 text-error mx-auto mb-1" />
                            <div class="text-sm text-error font-bold">該期間已關帳結案</div>
                            <div class="text-xs text-gray-500 mt-1">
                                本會計期間已執行關帳，所有歷史流水均已結算鎖定，嚴禁任何修改或更正。
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
        
        <x-slot:actions>
            <x-button label="關閉" @click="$wire.showDrawer = false" />
        </x-slot:actions>
    </x-drawer>
</div>