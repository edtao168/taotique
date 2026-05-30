{{-- 傳票（台灣：收入傳票、支出傳票、轉帳傳票）= 
	 記帳憑證（大陸：或稱「記帳憑單」、「分錄憑單」） --}}
<div>
    <x-header 
        :title="$isEdit ? '✏️ 修改草稿傳票' : '➕ 新增手動傳票'" 
        subtitle="手動輸入多科目分錄，系統自動驗證借貸平衡" 
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
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
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
                    label="摘要" 
                    wire:model="description"
                    placeholder="例如：支付平台手續費、銷售商品收入..." 
                    icon="o-chat-bubble-left" 
                    required 
                />
            </div>

            {{-- 🆕 多科目分錄表格（參考 journal-correct.blade.php） --}}
            <div class="lg:col-span-2 mt-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="font-bold text-base">📝 分錄明細</div>
                    <x-button 
                        label="➕ 新增科目" 
                        icon="o-plus" 
                        wire:click="addEntry" 
                        class="btn-sm btn-ghost" 
                    />
                </div>
                
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr class="text-sm">
                                <th class="w-24">借/貸</th>
                                <th>會計科目</th>
                                <th class="w-48 text-right">金額</th>
                                <th class="w-16 text-center">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($entries as $index => $entry)
                                <tr>
                                    <td>
                                        <x-select 
                                            wire:model="entries.{{ $index }}.entry_type" 
                                            wire:change="updateEntryType({{ $index }}, $event.target.value)"
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
                                        <div>
											<x-choices 
												wire:model.live="entries.{{ $index }}.account_code"
												:options="$accountSearchResults"
												option-label="name"
												option-value="id"
												searchable
												placeholder="🔍 輸入科目代碼或名稱..."
												class="w-full"
												single
												:debounce="300"
												@search="search($event.detail)"
											/>
										</div>
                                    </td>
                                    <td>
                                        <x-input 
                                            wire:model.live="entries.{{ $index }}.amount"
                                            type="number" 
                                            step="0.0001" 
                                            placeholder="0.0000"                                            
											class="input-sm text-right pr-3"
                                        />
                                    </td>
                                    <td class="text-center">
                                        <x-button 
                                            icon="o-trash" 
                                            class="btn-sm btn-ghost text-error"
                                            wire:click="removeEntry({{ $index }})"
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-base-300">
                            @php
                                $totalDebit = 0;
                                $totalCredit = 0;
                                foreach($entries as $entry) {
                                    $amt = (float)($entry['amount'] ?? 0);
                                    if($amt <= 0) continue;
                                    if($entry['entry_type'] === 'debit') {
                                        $totalDebit += $amt;
                                    } else {
                                        $totalCredit += $amt;
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
                
                <div class="text-xs text-gray-400 mt-2">
                    💡 提示：金額為 0 的行會自動忽略，不會儲存。
                </div>
            </div>
        </div>
    </x-form>
</div>