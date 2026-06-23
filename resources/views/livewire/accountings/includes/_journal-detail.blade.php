{{-- resources/views/livewire/accountings/includes/_journal-detail.blade.php --}}
@if($journal)
    <div class="space-y-4">
        {{-- 標題區：狀態 + 來源 --}}
        <div class="flex items-center gap-2">
            <x-badge :value="$journal->status_label" class="{{ $journal->status_color }}" />
            <x-badge :value="$journal->source_type_label ?? '手動分錄'" class="badge-outline" />
        </div>

        {{-- 資訊網格 --}}
        <x-card class="bg-base-200">
            <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div class="text-gray-500">傳票編號</div>
                <div class="font-mono font-bold text-right">#{{ $journal->id }}</div>
                
                <div class="text-gray-500">交易日</div>
                <div class="font-mono text-right">{{ $journal->entry_date->format('Y-m-d') }}</div>
                
                <div class="text-gray-500">🏪 分店</div>
                <div class="text-right">
                    @if($journal->shop)
                        {{ $journal->shop->name ?? $journal->shop->shop_name ?? '未設定' }}
                        <span class="text-xs text-gray-400">(ID: {{ $journal->shop_id }})</span>
                    @else
                        <span class="text-gray-400">未關聯分店</span>
                    @endif
                </div>
                
                <div class="text-gray-500">幣別 / 匯率</div>
                <div class="font-mono text-right">{{ $journal->currency }} @ {{ $journal->exchange_rate }}</div>
            </div>
        </x-card>

        {{-- 摘要 --}}
        <x-card>
            <x-slot:title>
                <span class="text-xs text-gray-500 font-normal">📝 摘要</span>
            </x-slot:title>
            <div class="font-bold text-base">{{ $journal->description }}</div>
        </x-card>

        {{-- 分錄明細 --}}
        <x-card title="📊 分錄明細" class="p-0 overflow-hidden">
            <x-table 
                :headers="[
                    ['key' => 'account', 'label' => '科目'],
                    ['key' => 'debit', 'label' => '借方', 'class' => 'text-right font-mono'],
                    ['key' => 'credit', 'label' => '貸方', 'class' => 'text-right font-mono'],
                ]" 
                :rows="$journal->items"
                row-class="text-sm"
                class="table-sm"
            >
                {{-- 客製化欄位顯示 --}}
                @scope('cell_debit', $row)
                    <span class="{{ $row['debit'] !== '-' ? 'text-success font-bold' : 'text-gray-300' }}">
                        {{ $row['debit'] }}
                    </span>
                @endscope
                
                @scope('cell_credit', $row)
                    <span class="{{ $row['credit'] !== '-' ? 'text-error font-bold' : 'text-gray-300' }}">
                        {{ $row['credit'] }}
                    </span>
                @endscope
            </x-table>
        </x-card>

        {{-- 合計列（可選） --}}
        @php
            $totalDebit = $journal->items->sum('debit');
            $totalCredit = $journal->items->sum('credit');
        @endphp
        @if($totalDebit > 0 || $totalCredit > 0)
            <div class="flex justify-end gap-8 text-sm font-bold px-4">
                <span>合計 借方：<span class="text-success font-mono">{{ number_format($totalDebit, 2) }}</span></span>
                <span>貸方：<span class="text-error font-mono">{{ number_format($totalCredit, 2) }}</span></span>
                @if(abs($totalDebit - $totalCredit) < 0.0001)
                    <span class="badge badge-success badge-sm">✓ 平衡</span>
                @endif
            </div>
        @endif
    </div>
@endif