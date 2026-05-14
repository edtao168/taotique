<div>    
    <x-header title="日記帳查詢" subtitle="所有業務自動產生的分錄流水" separator progress-indicator>
        <x-slot:actions>
            <x-input placeholder="搜尋描述..." wire:model.live.debounce="search" icon="o-magnifying-glass" />
            <x-button label="新增草稿" icon="o-plus" link="{{ route('accountings.journals.create') }}" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- 桌面版列表 --}}
    <div class="hidden lg:block">
        <x-card>
            {{-- 表頭 --}}
            <div class="grid grid-cols-12 gap-4 px-6 py-3 text-xs text-gray-500 border-b border-base-300">
                <div class="col-span-2 text-center">日期 / 狀態</div>
                <div class="col-span-6">摘要 / 分錄明細</div>
				<div class="col-span-2 text-right">借方</div>
                <div class="col-span-2 text-right">貸方</div>
            </div>
            
            {{-- 資料行 --}}
            @foreach($journals as $journal)
                @php
                    $js = $journal->status;
                    $jStatusLabel = match($js) {
                        'draft' => '草稿', 'posted' => '已過帳',
                        'cancelled' => '已作廢', 'closed' => '已結帳',
                        default => $js,
                    };
                    $jStatusClass = match($js) {
                        'draft' => 'badge-ghost', 'posted' => 'badge-success',
                        'cancelled' => 'badge-error', 'closed' => 'badge-info',
                        default => 'badge-ghost',
                    };
                @endphp
                <div 
                    class="grid grid-cols-12 gap-4 px-6 py-4 border-b border-base-200 last:border-0 hover:bg-base-200 transition-colors cursor-pointer"
                    wire:click="openDrawer({{ $journal->id }})"
                >
                    {{-- 日期 + 狀態 --}}
                    <div class="col-span-2 text-center">
                        <div class="font-mono text-sm">{{ $journal->entry_date->format('Y-m-d') }}</div>
                        <x-badge :value="$jStatusLabel" class="{{ $jStatusClass }} mt-1" />
						{{-- ✅ 新增：更正標記 --}}
						@if($journal->is_corrected && $journal->reference_type !== 'correct')
							<div class="mt-1">
								<x-badge value="已更正" class="badge-warning badge-soft" />
							</div>
							<div class="text-xs text-gray-500 mt-0.5">
								<x-icon name="o-lock-closed" class="w-3 h-3 inline" />
								#{{ $journal->hasCorrection->id ?? '?' }}
							</div>
						@endif
						
						@if($journal->reference_type === 'correct')
							<div class="mt-1">
								<x-badge value="更正分錄" class="badge-error badge-soft" />
							</div>
						@endif
                    </div>
                    
                    {{-- 摘要 + 分錄明細（含借貸方合計） --}}
                    <div class="col-span-10">
                        <div class="col-span-6 font-bold text-sm mb-3 truncate" title="{{ $journal->description }}">
                            {{ $journal->description }}
                        </div>
                        
                        
                        {{-- 分錄明細資料 --}}
                        <div class="space-y-1 pl-4">
                            @foreach($journal->items as $item)
                                <div class="grid grid-cols-10 gap-2 items-center text-xs">
                                    <div class="col-span-6 text-gray-600">
                                        <span class="font-mono bg-base-300 px-1 rounded mr-2">{{ $item->account->code }}</span>
                                        <span class="text-gray-500">{{ $item->account->name }}</span>
                                    </div>
                                    <div class="col-span-2 text-right font-mono {{ $item->debit > 0 ? 'text-success font-bold' : 'text-gray-300' }}">
                                        {{ $item->debit > 0 ? number_format($item->debit, 2) : '-' }}
                                    </div>
                                    <div class="col-span-2 text-right font-mono {{ $item->credit > 0 ? 'text-error font-bold' : 'text-gray-300' }}">
                                        {{ $item->credit > 0 ? number_format($item->credit, 2) : '-' }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        
                        @if($journal->source_number)
                            <div class="text-xs text-info mt-2 pl-4">
                                📎 {{ $journal->source_type_label }} #{{ $journal->source_number }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </x-card>
    </div>

    {{-- 手機版卡片 --}}
    <div class="lg:hidden space-y-3">
        @foreach($journals as $journal)
            @php
                $ms = $journal->status;
                $mStatusLabel = match($ms) {
                    'draft' => '草稿', 'posted' => '過帳',
                    'cancelled' => '作廢', 'closed' => '結帳',
                    default => $ms,
                };
                $mStatusClass = match($ms) {
                    'draft' => 'badge-ghost', 'posted' => 'badge-success',
                    'cancelled' => 'badge-error', 'closed' => 'badge-info',
                    default => 'badge-ghost',
                };
            @endphp
            <x-card 
                class="shadow-sm border border-base-300 !p-0 overflow-hidden cursor-pointer active:bg-base-200"
                wire:click="openDrawer({{ $journal->id }})"
            >
                <div class="bg-base-200 px-4 py-2 flex justify-between items-center border-b border-base-300">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-mono font-bold">{{ $journal->entry_date->format('Y-m-d') }}</span>
                        <x-badge :value="$mStatusLabel" class="{{ $mStatusClass }}" />
						{{-- ✅ 新增：更正標記 --}}
						@if($journal->is_corrected && $journal->reference_type !== 'correct')
							<x-badge value="已更正" class="badge-warning badge-soft" />
						@endif
						
						@if($journal->reference_type === 'correct')
							<x-badge value="更正" class="badge-error badge-soft" />
						@endif
                    </div>
                    <x-icon name="o-chevron-right" class="w-4 h-4 text-gray-400" />
                </div>
                <div class="p-4">
                    <div class="font-bold text-sm mb-2 truncate">{{ $journal->description }}</div>
                    
                    {{-- 手機版：科目+金額逐行顯示 --}}
                    <div class="space-y-1 mb-2">
                        @foreach($journal->items as $item)
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">{{ $item->account->name }}</span>
                                <span class="font-mono {{ $item->debit > 0 ? 'text-success' : 'text-error' }}">
                                    {{ $item->debit > 0 ? 'Dr ' . number_format($item->debit, 2) : 'Cr ' . number_format($item->credit, 2) }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-between items-center text-xs text-gray-500">
                        @if($journal->source_number)
                            <span class="text-info">📎 {{ $journal->source_number }}</span>
                        @else
                            <span></span>
                        @endif
                        <span class="font-mono">{{ $journal->items->count() }} 筆</span>
                    </div>
                </div>
            </x-card>
        @endforeach
    </div>	
	
    {{-- Drawer --}}
    <x-drawer wire:model="showDrawer" title="憑證詳情" right separator class="w-11/12 lg:w-1/3">
		@if($selectedJournal)
			{{-- 1. 引入共用的詳情組件 --}}
			@include('livewire.accountings.includes._journal-detail', ['journal' => $selectedJournal])

			{{-- 2. 僅保留此頁面特有的操作按鈕 --}}
			<div class="border-t pt-4 mt-6 flex flex-col gap-2">
				@if($selectedJournal->status === 'draft')
					<div class="flex gap-2">
						<x-button label="編輯" icon="o-pencil" :link="route('accountings.journals.edit', $selectedJournal)" class="btn-primary flex-1" />
						<x-button label="刪除" icon="o-trash" wire:click="delete({{ $selectedJournal->id }})" wire:confirm="確認刪除？" class="btn-error btn-outline flex-1" spinner />
						<x-button 
							label="過帳" 
							icon="o-check-circle" 
							wire:click="submitForApproval({{ $selectedJournal->id }})" 
							class="btn-success text-white font-bold flex-1" 
							spinner 
						/>
					</div>
				
				{{-- ✅ 修改：已過帳 + 未被更正 → 顯示更正按鈕 --}}
				@elseif($selectedJournal->status === 'posted' && !$selectedJournal->is_corrected && $selectedJournal->reference_type !== 'correct')
					<x-button 
						label="📝 產生更正分錄" 
						icon="o-arrow-path-rounded-square" 
						:link="route('accountings.journals.correct', $selectedJournal)" 
						class="btn-warning w-full" 
					/>
				
				{{-- ✅ 新增：已過帳 + 已被更正 → 顯示鎖定提示 --}}
				@elseif($selectedJournal->status === 'posted' && $selectedJournal->is_corrected)
					<div class="p-3 bg-warning/10 rounded-lg text-center">
						<x-icon name="o-lock-closed" class="w-5 h-5 text-warning mx-auto mb-1" />
						<div class="text-sm text-warning font-bold">此分錄已鎖定</div>
						<div class="text-xs text-gray-500 mt-1">
							已由 #{{ $selectedJournal->hasCorrection->id ?? '?' }} 更正，不可再次更正
						</div>
					</div>
				@elseif($selectedJournal->reference_type == 'correct')
					<div class="p-3 bg-warning/10 rounded-lg text-center">
						<x-icon name="o-lock-closed" class="w-5 h-5 text-warning mx-auto mb-1" />
						<div class="text-sm text-warning font-bold">此分錄已鎖定</div>
						<div class="text-xs text-gray-500 mt-1">
							此即更正分錄，不可再次更正
						</div>
					</div>
				@elseif($selectedJournal->status == 'closed')
					<div class="p-3 bg-warning/10 rounded-lg text-center">
						<x-icon name="o-lock-closed" class="w-5 h-5 text-warning mx-auto mb-1" />
						<div class="text-sm text-warning font-bold">此分錄已鎖定</div>
						<div class="text-xs text-gray-500 mt-1">
							此分錄已結案
						</div>
					</div>
				@endif
			</div>
        @else
            <div class="p-8 text-center text-gray-500">
                載入中...
            </div>
        @endif
    </x-drawer>

    <div class="mt-6">
        {{ $journals->links() }}
    </div>
</div>