<div>
    <x-header title="庫存異動流水帳" subtitle="追蹤所有庫存增減的歷史紀錄" separator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋 SKU..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
		<x-slot:actions>
			<x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
		</x-slot:actions>
    </x-header>

    <div class="mb-4 flex gap-4">
        <x-select :options="$types" wire:model.live="type" placeholder="所有類型" icon="o-funnel" />
    </div>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$movements" with-pagination >
            @scope('cell_created_at', $m)
                <span class="text-xs opacity-70">{{ $m->created_at->format('Y-m-d H:i') }}</span>
            @endscope

            @scope('cell_type_label', $m)
                <x-badge :value="$m->type_name" class="{{ $m->type_color }} badge-sm" />
            @endscope

            @scope('cell_quantity', $movement)
                <span class="{{ $movement->quantity > 0 ? 'text-green-600' : 'text-red-600' }} font-bold">
                    {{ $movement->quantity > 0 ? '+' : '' }}{{ number_format($movement->quantity) }}
                </span>
            @endscope
			
			@scope('cell_remark', $movement)
                <span class="text-sm text-gray-600">
                    {{ $movement->remark ?: '—' }}
                </span>
            @endscope
        </x-table>
    </x-card>
</div>