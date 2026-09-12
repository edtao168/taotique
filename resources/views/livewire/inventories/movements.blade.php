{{-- resources/views/livewire/inventories/movements.blade.php --}}

<div>
    {{-- ===== 頁首 ===== --}}
    <x-header title="庫存異動流水帳" subtitle="追蹤所有庫存增減的歷史紀錄" separator>
        <x-slot:middle class="!justify-end">
            <x-input 
                placeholder="搜尋 SKU 或商品名稱..." 
                wire:model.live.debounce.300ms="search" 
                icon="o-magnifying-glass" 
                clearable 
            />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
        </x-slot:actions>
    </x-header>

    {{-- ===== 篩選列：RWD 自動換行 ===== --}}
    <div class="mb-4 flex flex-wrap gap-2 sm:gap-4">
        <x-select 
            :options="$types" 
            wire:model.live="type" 
            placeholder="所有類型" 
            icon="o-funnel" 
            class="w-full sm:w-48"
        />
    </div>

    {{-- ===== 桌面版：表格 (md 以上) ===== --}}
    <x-card shadow class="hidden md:block">
        <x-table 
            :headers="$headers" 
            :rows="$movements" 
            :sort-by="$sortBy" 
            with-pagination
        >
            @scope('cell_created_at', $m)
                <span class="text-xs opacity-70">{{ $m->created_at->format('Y-m-d H:i') }}</span>
            @endscope

            @scope('cell_product.sku', $m)
                <span class="font-mono text-sm">{{ $m->product->sku }}</span>
            @endscope

            @scope('cell_product_name', $m)
                <span class="text-sm">{{ $m->product_name ?? '—' }}</span>
            @endscope

            @scope('cell_warehouse.name', $m)
                <span class="text-sm">
                    {{ $m->warehouse->shop->name ?? '' }} {{ $m->warehouse->name ?? '' }}
                </span>
            @endscope

            @scope('cell_type_label', $m)
                <x-badge :value="$m->type_name" class="{{ $m->type_color }} badge-sm" />
            @endscope

            @scope('cell_quantity', $m)
                <span class="{{ $m->quantity > 0 ? 'text-green-600' : 'text-red-600' }} font-bold">
                    {{ $m->quantity > 0 ? '+' : '' }}{{ number_format($m->quantity) }}
                </span>
            @endscope

            @scope('cell_remark', $m)
                <span class="text-sm text-gray-600">{{ $m->remark ?: '—' }}</span>
            @endscope

            @scope('cell_user.name', $m)
                <span class="text-sm">{{ $m->user->name ?? '系統' }}</span>
            @endscope
        </x-table>
    </x-card>

    {{-- ===== 手機版：卡片 (md 以下) ===== --}}
    <div class="md:hidden space-y-3">
        @forelse($movements as $movement)
            <x-card class="border-l-4 {{ $movement->quantity > 0 ? 'border-l-green-500' : 'border-l-red-500' }}">
                {{-- 時間 + 類型 --}}
                <div class="flex justify-between items-start mb-3">
                    <div class="text-xs opacity-70">
                        {{ $movement->created_at->format('Y-m-d H:i') }}
                    </div>
                    <x-badge :value="$movement->type_name" class="{{ $movement->type_color }} badge-sm" />
                </div>

                {{-- 商品資訊：SKU + name --}}
                <div class="mb-3">
                    <div class="font-mono text-xs bg-base-200 inline-block px-2 py-0.5 rounded">
                        {{ $movement->product->sku }}
                    </div>
                    @if($movement->product->name)
                        <div class="font-medium text-base mt-1.5">
                            {{ $movement->product->name }}
                        </div>
                    @endif
                </div>

                {{-- 異動量 + 倉庫 --}}
                <div class="flex justify-between items-center mb-2">
                    <div class="text-sm">
                        <span class="opacity-70">異動量：</span>
                        <span class="{{ $movement->quantity > 0 ? 'text-green-600' : 'text-red-600' }} font-bold text-lg">
                            {{ $movement->quantity > 0 ? '+' : '' }}{{ number_format($movement->quantity) }}
                        </span>
                    </div>
                    <div class="text-xs text-right opacity-70">
                        {{ $movement->warehouse->shop->name ?? '' }} {{ $movement->warehouse->name ?? '' }}
                    </div>
                </div>

                {{-- 備註 --}}
                @if($movement->remark)
                    <div class="text-sm text-gray-500 border-t border-base-200 pt-2 mt-2">
                        <span class="opacity-70">備註：</span>{{ $movement->remark }}
                    </div>
                @endif

                {{-- 操作人 --}}
                <div class="text-xs opacity-50 mt-2 text-right">
                    操作人：{{ $movement->user->name ?? '系統' }}
                </div>
            </x-card>
        @empty
            <div class="text-center text-gray-400 py-8">暫無異動紀錄</div>
        @endforelse
    </div>

    {{-- ===== 載入更多 / 分頁（手機與桌面共用） ===== --}}
    <div class="py-6 flex flex-col items-center gap-2">
        @if($movements->hasMorePages())
            <x-button 
                label="載入更多" 
                wire:click="loadMore" 
                wire:loading.attr="disabled"
                wire:target="loadMore"
                class="w-full md:w-auto btn-primary" 
            />
            <div wire:loading wire:target="loadMore" class="text-sm opacity-50">載入中...</div>
        @else
            <div class="divider text-xs opacity-50 italic">已載入全部異動紀錄</div>
        @endif
    </div>
</div>