{{-- resources/views/livewire/accountings/journal-create.blade.php --}}
{{-- [費曼註釋：此視圖只處理 draft 的新增與修改。已過帳分錄會被導向 journal-correct] --}}

<div>
    <x-header 
        :title="$isEdit ? '✏️ 修改草稿憑證' : '➕ 新增日記帳草稿'" 
        subtitle="歷史記憶 + 會計規則雙引擎匹配" 
        separator 
    >
        <x-slot:actions>
            <x-button label="返回列表" icon="o-arrow-left" link="{{ route('accountings.journals.index') }}" class="btn-outline" />
            
            @if($isEdit)
                <x-button 
                    label="刪除草稿" 
                    icon="o-trash" 
                    wire:click="deleteDraft" 
                    wire:confirm="確認刪除此草稿？此操作不可恢復。" 
                    class="btn-error btn-outline btn-sm" 
                />
            @endif
            
            <x-button 
                label="💾 儲存草稿" 
                icon="o-check" 
                class="btn-primary" 
                type="submit" 
                form="journalForm" 
                spinner="save" 
            />
        </x-slot:actions>
    </x-header>

    <x-form wire:submit="save" id="journalForm">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-4">
            
            <div class="lg:col-span-2">
                <x-datetime 
                    label="📅 交易日" 
                    wire:model="entry_date" 
                    icon="o-calendar" 
                    required 
                />
            </div>

            <div class="lg:col-span-2">
                <x-input 
                    label="1️⃣ 摘要" 
                    wire:model.live="description"
                    placeholder="例如：POS機無法開機送修、中午請客戶吃飯..." 
                    icon="o-chat-bubble-left" 
                    hint="系統會自動比對歷史紀錄與基礎規則" 
                    required 
                />
            </div>

            <div class="lg:col-span-2">
                <x-select 
                    label="2️⃣ 系統智能匹配科目 (可手動修改 | 自動匹配不一定正確，需確認。)" 
                    wire:model.live="selected_account" 
                    :options="$accountOptions" 
                    option-label="name" 
                    option-value="id" 
                    placeholder="請輸入上方摘要，或手動選擇科目..." 
                    searchable 
                    icon="o-check-circle" 
                    :class="!empty($selected_account) ? 'text-success font-bold' : ''" 
                />
				<div wire:loading wire:target="description" class="text-xs text-warning mt-1 items-center flex">
					<x-loading class="w-3 h-3 mr-1" /> 正在分析摘要並匹配會計科目...
				</div>
                {{-- 顯示匹配結果 --}}
    @if(!empty($selected_account) && !empty($description))
        <div class="text-xs text-success mt-1 flex items-center">
            <x-icon name="o-sparkles" class="w-3 h-3 mr-1" />
            匹配成功：{{ $currentAccountName }}
        </div>
    @endif
            </div>

            <x-input 
                label="3️⃣ 金額 (TWD)" 
                wire:model.live="amount" 
                type="number" 
                step="0.0001" 
                icon="o-currency-dollar" 
                placeholder="0.0000" 
                :disabled="empty($selected_account)" 
                :hint="empty($selected_account) ? '請先確認科目' : '輸入金額以生成分錄'" 
                required 
            />

            <x-select 
                label="4️⃣ 資金帳戶" 
                wire:model.live="payment_method" 
                :options="$paymentOptions" 
                option-label="name" 
                option-value="id" 
                placeholder="-- 請先確認金額 --" 
                :disabled="empty($amount) || bccomp($amount, '0', 4) <= 0" 
                searchable 
                required 
            />
        </div>

        {{-- 分錄預覽 --}}
        @if(!empty($payment_method) && count($generated_lines) > 0)
            <div class="mt-8 p-5 bg-base-200 rounded-xl border border-primary/20 shadow-sm">
                <div class="font-bold mb-3 text-primary flex items-center text-lg">
                    <span class="mr-2">✅</span> 分錄預覽確認
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="border-r border-base-300 pr-4">
                        <div class="text-success font-bold mb-2 text-sm uppercase tracking-wide">借方 (Dr)</div>
                        @foreach($generated_lines as $line)
                            @if($line['entry_type'] === 'debit')
                                <div class="text-sm py-2 px-3 bg-base-100 rounded mb-2 flex justify-between">
                                    <span>{{ $line['account_name'] }} <span class="text-xs text-gray-400">({{ $line['account_id'] }})</span></span>
                                    <span class="font-bold font-mono">{{ number_format($line['amount'], 4) }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    
                    <div>
                        <div class="text-error font-bold mb-2 text-sm uppercase tracking-wide">貸方 (Cr)</div>
                        @foreach($generated_lines as $line)
                            @if($line['entry_type'] === 'credit')
                                <div class="text-sm py-2 px-3 bg-base-100 rounded mb-2 flex justify-between">
                                    <span>{{ $line['account_name'] }} <span class="text-xs text-gray-400">({{ $line['account_id'] }})</span></span>
                                    <span class="font-bold font-mono">{{ number_format($line['amount'], 4) }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
                
                {{-- 借貸平衡驗證 --}}
                @php
                    $totalDebit = '0';
                    $totalCredit = '0';
                    foreach($generated_lines as $line) {
                        if($line['entry_type'] === 'debit') {
                            $totalDebit = bcadd($totalDebit, $line['amount'], 4);
                        } else {
                            $totalCredit = bcadd($totalCredit, $line['amount'], 4);
                        }
                    }
                    $isBalanced = bccomp($totalDebit, $totalCredit, 4) === 0;
                @endphp
                
                <div class="mt-4 text-center {{ $isBalanced ? 'text-success' : 'text-error' }} font-bold">
                    借方合計：{{ number_format($totalDebit, 4) }} | 貸方合計：{{ number_format($totalCredit, 4) }}
                    {{ $isBalanced ? '✓ 借貸平衡' : '✗ 借貸不平衡' }}
                </div>
            </div>
        @endif
    </x-form>
</div>