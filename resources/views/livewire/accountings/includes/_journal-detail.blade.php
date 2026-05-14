{{-- resources/views/livewire/accountings/includes/_journal-detail.blade.php --}}
@if($journal)
    @php
        $s = $journal->status;
        $statusLabel = match($s) {
            'draft' => '草稿', 'posted' => '已過帳',
            'cancelled' => '已作廢', 'closed' => '已結案',
            default => $s,
        };
        $statusClass = match($s) {
            'draft' => 'badge-ghost', 'posted' => 'badge-success',
            'cancelled' => 'badge-error', 'closed' => 'badge-info',
            default => 'badge-ghost',
        };
    @endphp
    
    <div class="space-y-4">
        <div class="flex items-center gap-2">
            <x-badge :value="$statusLabel" class="{{ $statusClass }}" />
            <x-badge :value="$journal->source_type_label ?? '手動分錄'" class="badge-outline" />
        </div>

        <div class="bg-base-200 p-4 rounded-lg space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500">憑證編號</span>
                <span class="font-mono font-bold">#{{ $journal->id }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">交易日</span>
                <span class="font-mono">{{ $journal->entry_date->format('Y-m-d') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">幣別 / 匯率</span>
                <span class="font-mono">{{ $journal->currency }} @ {{ $journal->exchange_rate }}</span>
            </div>
        </div>

        <div class="p-4 border border-base-300 rounded-lg">
            <div class="text-xs text-gray-500 mb-1">摘要</div>
            <div class="font-bold">{{ $journal->description }}</div>
        </div>

        {{-- 分錄明細 --}}
        <div class="border border-base-300 rounded-lg overflow-hidden">
            <table class="table table-sm w-full">
                <thead>
                    <tr class="text-xs text-gray-500">
                        <th>科目</th>
                        <th class="text-right">借方</th>
                        <th class="text-right">貸方</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($journal->items as $item)
                        <tr>
                            <td class="text-sm">
                                <span class="font-mono text-xs bg-base-300 px-1 rounded">{{ $item->account->code }}</span>
                                {{ $item->account->name }}
                            </td>
                            <td class="text-right font-mono {{ bccomp($item->debit, '0', 4) > 0 ? 'text-success font-bold' : 'text-gray-300' }}">
                                {{ bccomp($item->debit, '0', 4) > 0 ? number_format($item->debit, 2) : '-' }}
                            </td>
                            <td class="text-right font-mono {{ bccomp($item->credit, '0', 4) > 0 ? 'text-error font-bold' : 'text-gray-300' }}">
                                {{ bccomp($item->credit, '0', 4) > 0 ? number_format($item->credit, 2) : '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif