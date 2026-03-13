<div>
    <x-header title="拆裝作業紀錄" subtitle="檢視歷史庫存轉換清單" separator progress-indicator>
        <x-slot:actions>
            <x-input placeholder="搜尋單號或備註..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
			<x-button label="新增拆裝作業" icon="o-plus" :link="route('inventories.conversions.create')" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$conversions" :sort-by="$sortBy" with-pagination>
            {{-- 自定義單號欄位 --}}
            @scope('cell_order_no', $conversion)
                <x-badge :value="$conversion->order_no" class="badge-outline" />
            @endscope

            {{-- 自定義日期顯示 --}}
            @scope('cell_process_date', $conversion)
                {{ $conversion->process_date->format('Y-m-d') }}
            @endscope

            {{-- 操作按鈕 --}}
            @scope('actions', $conversion)
                <div class="flex gap-2">
                    <x-button icon="o-eye" class="btn-ghost btn-sm" tooltip="詳情" />
                    <x-button icon="o-trash" class="btn-ghost btn-sm text-error" 
                        wire:click="delete({{ $conversion->id }})" 
                        wire:confirm="確定要刪除此筆紀錄嗎？(注意：此操作不會自動回滾庫存)" />
                </div>
            @endscope
        </x-table>
    </x-card>
</div>