{{-- resources/views/livewire/conversions/index.blade.php --}}
<div>
    <x-header title="拆裝作業紀錄" subtitle="檢視歷史庫存轉換清單" separator progress-indicator>
        <x-slot:actions>
            {{-- 手機端縮小搜尋框 --}}
            <x-input placeholder="搜尋..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable class="w-32 md:w-64" />
            <x-button icon="o-plus" :link="route('inventories.conversions.create')" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- PC 端：表格顯示 --}}
    <div class="hidden md:block">
        <x-card shadow>
            <x-table 
                :headers="$headers" 
                :rows="$conversions" 
                :sort-by="$sortBy" 
                @row-click="$wire.showDetails($event.detail.id)"
                with-pagination 
            >
                @scope('cell_conversion_no', $conversion)
                    <x-badge :value="$conversion->conversion_no" class="badge-outline" />
                @endscope
                @scope('cell_process_date', $conversion)
                    {{ $conversion->process_date->format('Y-m-d') }}
                @endscope
            </x-table>
        </x-card>
    </div>

    {{-- 手機端：卡片顯示 --}}
    <div class="grid grid-cols-1 gap-4 md:hidden">
        @foreach($conversions as $conversion)
            <x-card class="border-l-4 border-primary shadow-sm" @click="$wire.showDetails({{ $conversion->id }})">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-bold text-lg text-primary">{{ $conversion->conversion_no }}</div>
                        <div class="text-xs text-gray-500">{{ $conversion->process_date->format('Y-m-d') }}</div>
                    </div>
                    <x-badge :value="$conversion->user->name" class="badge-ghost" />
                </div>
                <div class="mt-2 text-sm text-gray-600 truncate">
                    {{ $conversion->remark ?: '無備註' }}
                </div>
                <div class="mt-3 flex justify-end">
                    <x-button icon="o-chevron-right" class="btn-circle btn-xs btn-ghost" />
                </div>
            </x-card>
        @endforeach
        
        <div class="mt-4">
            {{ $conversions->links() }}
        </div>
    </div>

    {{-- 2. 查詢詳情 Drawer --}}
    <x-drawer wire:model="showDrawer" title="拆裝單詳情" right separator with-close-button class="w-11/12 md:w-1/3">
        @if($selectedConversion)
            <div class="space-y-4">
				
					<x-list-item :item="$selectedConversion" no-separator no-hover>
						<x-slot:value>單號：{{ $selectedConversion->conversion_no }}</x-slot:value>
						<x-slot:sub-value>日期：{{ $selectedConversion->process_date->format('Y-m-d') }}</x-slot:sub-value>
					</x-list-item>
				
                <div class="flex justify-between items-center mb-4 px-1">
						<p class="text-sm font-bold border-l-4 border-primary pl-2">商品明細</p>
						<span class="text-xs text-gray-400 font-mono">共 {{ $selectedConversion->items->count() }} 項</span>
					</div>
                <div class="bg-base-100 border rounded-xl p-4 shadow-sm">
					{{-- 領料與產出明細 --}}
					@foreach($selectedConversion->items as $item)
						<div class="flex justify-between items-center text-sm border-b mt-2 pb-2">
							<div>
								<span class="badge badge-sm {{ $item->type == 1 ? 'badge-warning' : 'badge-success' }}">
									{{ $item->type == 1 ? '投' : '產' }}
								</span>
								{{ $item->product->full_display_name }}
							</div>
							<div class="font-mono">{{ number_format($item->quantity, 2) }}</div>
						</div>
					@endforeach
				</div>
                <div class="mt-6 text-sm text-gray-500">
                    備註：{{ $selectedConversion->remark ?: '無' }}
                </div>
            </div>
        @endif

        {{-- 3. 下方功能按鈕 --}}
        <x-slot:actions>
		<div class="flex gap-3 w-full border-t pt-4 bg-base-100">
            <x-button label="返回" icon="o-arrow-uturn-left" @click="$wire.showDrawer = false" class="btn-success flex-1"/>
            <x-button label="刪除" icon="o-trash" class="btn-error" class="btn-error flex-1"
                wire:click="delete({{ $selectedConversion?->id }})" 
                wire:confirm="確定要刪除嗎？" />
            
            <x-button label="修改" icon="o-pencil" class="btn-primary flex-1"
                :link="route('inventories.conversions.edit', $selectedConversion?->id ?? 0)" />
				</div>
        </x-slot:actions>
    </x-drawer>
</div>