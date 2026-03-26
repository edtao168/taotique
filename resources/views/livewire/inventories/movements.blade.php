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

    <!-- 桌面版表格 (md以上螢幕顯示) -->
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
				<span class="font-mono">{{ $m->product->sku }}</span>
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

    <!-- 手機端卡片化顯示 (md以下螢幕顯示) -->
    <div class="md:hidden space-y-3">
        @forelse($movements as $movement)
            <x-card class="border-l-4 {{ $movement->quantity > 0 ? 'border-l-green-500' : 'border-l-red-500' }}">
                <!-- 上方：時間與類型 -->
                <div class="flex justify-between items-start mb-2">
                    <div class="text-xs opacity-70">
                        {{ $movement->created_at->format('Y-m-d H:i') }}
                    </div>
                    <div>
                        <x-badge :value="$movement->type_name" class="{{ $movement->type_color }} badge-sm" />
                    </div>
                </div>

                <!-- 商品資訊 -->
                <div class="mb-2">
                    <div class="font-mono text-sm bg-base-200 inline-block px-2 py-0.5 rounded">
                        {{ $movement->product->sku }}
                    </div>
                    @if($movement->product->name)
                        <div class="font-medium text-base mt-1">
                            {{ $movement->product->name }}
                        </div>
                    @endif
                </div>

                <!-- 異動量與倉庫 -->
                <div class="flex justify-between items-center mb-2">
                    <div class="text-sm">
                        <span class="opacity-70">異動量：</span>
                        <span class="{{ $movement->quantity > 0 ? 'text-green-600' : 'text-red-600' }} font-bold text-lg">
                            {{ $movement->quantity > 0 ? '+' : '' }}{{ number_format($movement->quantity) }}
                        </span>
                    </div>
                    <div class="text-sm text-right">
                        <span class="opacity-70">庫別：</span>
                        <span class="font-medium">{{ $movement->warehouse->shop->name ?? '' }} {{ $movement->warehouse->name }}</span>
                    </div>
                </div>

                <!-- 備註 (如果有) -->
                @if($movement->remark)
                    <div class="text-sm text-gray-500 border-t border-base-200 pt-2 mt-1">
                        <span class="opacity-70">備註：</span>
                        {{ $movement->remark }}
                    </div>
                @endif

                <!-- 操作人資訊 -->
                <div class="text-xs opacity-50 mt-2 text-right">
                    操作人：{{ $movement->user->name ?? '系統' }}
                </div>
            </x-card>
        @empty
            <div class="text-center text-gray-400 py-8">
                暫無異動紀錄
            </div>
        @endforelse

        <!-- 手機端分頁/載入更多 -->
        <div class="py-6 flex flex-col items-center gap-2">
            @if($movements->hasMorePages())
                <x-button 
                    label="載入更多" 
                    wire:click="loadMore" 
                    wire:loading.attr="disabled"
                    wire:target="loadMore"
                    class="w-full btn-primary" 
                />
                <div wire:loading wire:target="loadMore" class="text-sm opacity-50">
                    載入中...
                </div>
            @else
                <div class="divider text-xs opacity-50 italic">已載入全部異動紀錄</div>
            @endif
        </div>
    </div>

    <!-- 桌面版分頁保留 (但需要配合 loadMore 方法) -->
    @if($movements->hasPages() && !$movements->hasMorePages())
        <div class="hidden md:block mt-4">
            {{ $movements->links() }}
        </div>
    @endif
</div>