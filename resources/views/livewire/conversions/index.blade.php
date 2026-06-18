{{-- resources/views/livewire/conversions/index.blade.php --}}

{{-- 在頂部引入 Enum（方便使用） --}}
@php use App\Enums\WorkflowStatus; @endphp

<div>
    <x-header title="拆裝組合管理" subtitle="庫存轉換作業紀錄" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋單號或備註..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="回庫存總覽" icon="o-arrow-left" :link="route('inventories.index')" />
            <x-button label="新增拆裝" icon="o-plus" class="btn-primary" :link="route('inventories.conversions.create')" />
        </x-slot:actions>
    </x-header>

    <x-card shadow separator>
        {{-- PC 端表格 --}}
        <div class="hidden lg:block">
            <x-table 
                :headers="$headers" 
                :rows="$conversions" 
                :sort-by="$sortBy"
                @row-click="$wire.showDetails($event.detail.id)" 
                class="cursor-pointer" 
                with-pagination
            >
                {{-- 拆裝單號 --}}
                @scope('cell_conversion_no', $conversion)
                    <x-badge :value="$conversion->conversion_no" 
                             :class="$conversion->status->color()"
                             class="badge-outline font-mono" />
                @endscope

                {{-- 狀態（使用 Enum） --}}
                @scope('cell_status', $conversion)
                    <x-badge :value="$conversion->status_label" :class="$conversion->status_color" />
                @endscope

                {{-- 作業日期 --}}
                @scope('cell_process_date', $conversion)
                    <span class="text-sm">{{ $conversion->process_date->format('Y-m-d') }}</span>
                @endscope

                {{-- 操作員 --}}
                @scope('cell_user.name', $conversion)
                    <span class="text-sm">{{ $conversion->user->name ?? '-' }}</span>
                @endscope

                {{-- 品項數 --}}
                @scope('cell_items_count', $conversion)
                    <span class="badge badge-sm">{{ $conversion->items->count() }} 項</span>
                @endscope
            </x-table>
        </div>

        {{-- 手機端卡片 --}}
        <div class="block lg:hidden space-y-3">
            @foreach($conversions as $conversion)
                <div class="border rounded-xl p-4 bg-base-50 active:bg-base-200 transition-colors" @click="$wire.showDetails({{ $conversion->id }})">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex flex-wrap gap-2">
                            <x-badge :value="$conversion->conversion_no" 
                                     :class="$conversion->status->color()"
                                     class="badge-sm font-mono" />
                            <x-badge :value="$conversion->status_label" 
                                     :class="$conversion->status_color . ' badge-xs'" />
                        </div>
                        <span class="text-[10px] text-gray-500">{{ $conversion->process_date->format('m/d') }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-bold text-base">{{ $conversion->warehouse->name ?? '未知倉庫' }}</p>
                            <p class="text-[10px] text-gray-400 font-mono">操作員: {{ $conversion->user->name ?? '-' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-gray-600 font-bold text-sm font-mono">{{ $conversion->items->count() }} 項商品</p>
                            @if($conversion->remark)
                                <p class="text-[10px] text-gray-400 truncate max-w-[120px]">{{ $conversion->remark }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="mt-4">
                {{ $conversions->links(data: ['scrollTo' => false]) }}
            </div>
        </div>
    </x-card>

    {{-- 拆裝單詳情抽屜 --}}
    <x-drawer wire:model="showDrawer" title="拆裝單據詳情" right separator with-close-button class="w-11/12 lg:w-1/3">
        
        {{-- 已完成浮水印 --}}
        @if($selectedConversion && $selectedConversion->status === WorkflowStatus::COMPLETED)
            <div class="absolute inset-0 flex items-center justify-center overflow-hidden pointer-events-none select-none z-50">
                <div class="border-8 border-success/30 text-success/30 text-7xl font-black uppercase tracking-widest px-8 py-4 rounded-xl border-dashed -rotate-12 transform">
                    已結案
                </div>
            </div>
        @endif

        @if($selectedConversion)
            <div class="space-y-6 pb-20">
                
                {{-- 基礎單據資訊 --}}
				<div class="bg-base-100 border rounded-xl p-4 shadow-sm">                    
					<div class="grid grid-cols-2 gap-y-4 text-sm">
						<div>
							<p class="text-[10px] text-gray-400">拆裝單號</p>
							<p class="font-mono font-medium text-gray-700">{{ $selectedConversion->conversion_no }}</p>
						</div>
						<div>
							<p class="text-[10px] text-gray-400">狀態</p>
							<x-badge :value="$selectedConversion->status_label" 
									 :class="$selectedConversion->status_color" />
						</div>
						<div>
							<p class="text-[10px] text-gray-400">作業日期</p>
							<p class="text-sm">{{ $selectedConversion->process_date->format('Y-m-d') }}</p>
						</div>
						<div>
							<p class="text-[10px] text-gray-400">倉庫</p>
							<p class="font-medium">{{ $selectedConversion->warehouse->name ?? '-' }}</p>
						</div>
						<div>
							<p class="text-[10px] text-gray-400">操作人員</p>
							<p class="text-xs">{{ $selectedConversion->user?->name ?? '-' }}</p>
						</div>
						<div>
							<p class="text-[10px] text-gray-400">過帳人員</p>
							<p class="text-xs">{{ $selectedConversion->approver?->name ?? '未過帳' }}</p>
						</div>
						<div>
							<p class="text-[10px] text-gray-400">建立時間</p>
							<p class="text-xs">{{ $selectedConversion->created_at?->format('Y-m-d H:i') ?? '-' }}</p>
						</div>
						<div>
							<p class="text-[10px] text-gray-400">過帳時間</p>
							<p class="text-xs">{{ $selectedConversion->approved_at?->format('Y-m-d H:i') ?? '-' }}</p>
						</div>
					</div>
				</div>
                
                {{-- 核心指標 --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-3 border rounded-xl bg-primary/5">
                        <p class="text-[10px] text-primary mb-1 font-bold">投入品項</p>
                        <p class="text-xl font-black font-mono">
                            {{ $selectedConversion->items->where('type', 1)->count() }} 項
                        </p>
                    </div>
                    <div class="p-3 border rounded-xl bg-success/5">
                        <p class="text-[10px] text-success mb-1 font-bold">產出品項</p>
                        <p class="text-xl font-black font-mono">
                            {{ $selectedConversion->items->where('type', 2)->count() }} 項
                        </p>
                    </div>
                </div>

                {{-- 商品明細 --}}
                <div>
                    <p class="text-sm font-bold border-l-4 border-primary pl-2 mb-4">商品明細</p>
                    @php
                        $user = auth()->user();
                        $showCost = in_array($user->role, ['owner', 'manager']);
                        $inputItems = $selectedConversion->items->where('type', 1);
                        $outputItems = $selectedConversion->items->where('type', 2);
                    @endphp

                    {{-- 投入項目 --}}
                    @if($inputItems->count() > 0)
                        <div class="mb-3">
                            <p class="text-xs font-semibold text-warning flex items-center gap-1 mb-2">
                                <x-icon name="o-arrow-up-circle" class="w-4 h-4" /> 投入 / 領料
                            </p>
                            @foreach($inputItems as $item)
                                <div class="p-2 border-l-2 border-warning bg-base-50 rounded-r-lg mb-1">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <p class="font-medium text-sm">
                                                {{ $item->product->sku ?? '' }} - {{ $item->product->name ?? '未知商品' }}
                                            </p>
                                            <p class="text-[10px] text-gray-400">倉庫: {{ $item->warehouse->name ?? '-' }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-mono">× {{ number_format($item->quantity, 2) }}</p>
                                            @if($showCost && $item->cost_snapshot)
                                                <p class="text-[10px] text-gray-400">@ {{ number_format($item->cost_snapshot, 2) }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- 產出項目 --}}
                    @if($outputItems->count() > 0)
                        <div>
                            <p class="text-xs font-semibold text-success flex items-center gap-1 mb-2">
                                <x-icon name="o-arrow-down-circle" class="w-4 h-4" /> 產出 / 入庫
                            </p>
                            @foreach($outputItems as $item)
                                <div class="p-2 border-l-2 border-success bg-base-50 rounded-r-lg mb-1">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <p class="font-medium text-sm">
                                                {{ $item->product->sku ?? '' }} - {{ $item->product->name ?? '未知商品' }}
                                            </p>
                                            <p class="text-[10px] text-gray-400">倉庫: {{ $item->warehouse->name ?? '-' }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-mono">× {{ number_format($item->quantity, 2) }}</p>
                                            @if($showCost && $item->cost_snapshot)
                                                <p class="text-[10px] text-gray-400">成本 @ {{ number_format($item->cost_snapshot, 2) }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- 備註 --}}
                @if($selectedConversion->remark)
                    <div class="bg-base-200/50 rounded-xl p-4">
                        <p class="text-xs font-bold text-gray-500 mb-1 flex items-center gap-1">
                            <x-icon name="o-chat-bubble-left" class="w-3 h-3" /> 備註
                        </p>
                        <p class="text-sm text-gray-700">{{ $selectedConversion->remark }}</p>
                    </div>
                @endif
            </div>

            {{-- 底部固定動作欄 --}}
			<x-slot:actions>            
				<div class="flex gap-3 w-full border-t pt-4 bg-base-100">
					<x-button 
						label="返回" 
						icon="o-arrow-uturn-left" 
						@click="$wire.showDrawer = false" 
						class="btn-success flex-1" 
					/>
					
					{{-- 草稿或待審核：顯示執行過帳按鈕（比照採購退貨） --}}
					@if(!$selectedConversion->isFinalized())
						<x-button 
							label="過帳" 
							icon="o-archive-box" 
							class="btn-warning flex-1"
							wire:click="submitConversionPost({{ $selectedConversion->id }})"
							wire:confirm="確定要執行庫存過帳嗎？此操作將會增減庫存並產生會計分錄！"
							spinner 
						/>
					@endif
					
					{{-- 只有未結案狀態才顯示修改/刪除按鈕 --}}
					@if(!$selectedConversion->isFinalized())
						<x-button 
							label="修改" 
							icon="o-pencil" 
							class="btn-primary flex-1"
							:link="route('inventories.conversions.edit', $selectedConversion->id)" 
						/>
						<x-button 
							label="刪除" 
							icon="o-trash" 
							wire:click="delete({{ $selectedConversion->id }})" 
							wire:confirm="確定要刪除此拆裝紀錄嗎？" 
							class="btn-error btn-outline flex-1" 
						/>
					@endif
				</div>                
			</x-slot:actions>
        @endif
    </x-drawer>
</div>