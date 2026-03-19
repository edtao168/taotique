{{-- 檔案路徑：resources/views/livewire/products/index.blade.php --}}
<div>
    <x-header title="商品清單" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋 SKU 或名稱..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
            <x-button label="新增商品" icon="o-plus" link="/products/create" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- --- PC 端表格模式 (LG 以上顯示) --- --}}
    <div class="hidden lg:block">
        <x-card shadow>
            <x-table :headers="$headers" :rows="$products" @row-click="$wire.showDetail($event.detail.id)" class="cursor-pointer" with-pagination>
                @scope('cell_image', $product)
                    @if($product->images->first())
                        <img src="{{ Storage::url($product->images->first()->path) }}" class="w-12 h-12 object-cover rounded-lg shadow-sm" />
                    @else
                        <div class="w-12 h-12 bg-base-200 rounded-lg flex items-center justify-center text-gray-400">
                            <x-icon name="o-photo" class="w-6 h-6" />
                        </div>
                    @endif
                @endscope

                @scope('cell_sku', $product)
                    <div class="flex items-center gap-2">
                        <x-badge :value="$product->sku" class="badge-neutral font-mono text-sm font-bold px-4 py-3 tracking-tighter" />
                        @if(!$product->is_active)
                            <div class="w-2 h-2 rounded-full bg-error" title="已停售"></div>
                        @endif
                    </div>
                @endscope

                @scope('cell_name', $product)
                    <div class="flex flex-col">
                        <span class="{{ $product->is_active ? 'font-medium' : 'text-gray-400 line-through' }}">
                            {{ $product->name }}
                        </span>
                        @if($product->remark)
                            <span class="text-xs text-gray-500 italic truncate max-w-xs">{{ $product->remark }}</span>
                        @endif
                    </div>
                @endscope

                @if(auth()->user()->role === 'owner')
                    @scope('cell_cost', $product)
                        <span class="text-error font-bold">$ {{ number_format($product->cost, 2) }}</span>
                    @endscope
                @endif

                @scope('cell_price', $product)
                    <span class="font-bold text-blue-700 text-sm">NT$ {{ number_format($product->price, 0) }}</span>
                @endscope

                @scope('cell_total_stock', $product)
                    <span class="{{ $product->total_stock <= ($product->min_stock ?? 0) ? 'text-error font-bold' : '' }}">
                        {{ $product->total_stock }}
                    </span>
                @endscope

                @scope('actions', $product)
                    <div class="flex gap-2">
                        <x-button icon="o-pencil" link="{{ route('products.edit', $product->id) }}" class="btn-ghost btn-sm text-blue-500" onclick="event.stopPropagation();" />
                        <x-button icon="o-trash" wire:click="delete({{ $product->id }})" wire:confirm="確定刪除？" class="btn-ghost btn-sm text-red-500" onclick="event.stopPropagation();" />
                    </div>
                @endscope
            </x-table>
        </x-card>
    </div>

    {{-- --- 手機端卡片模式 (LG 以下顯示) --- --}}
    <div class="block lg:hidden space-y-3">
        @foreach($products as $product)
            <x-card class="shadow-sm border border-base-200" @click="$wire.showDetail({{ $product->id }})">
                <div class="flex gap-4">
                    {{-- 左側：縮圖 --}}
                    <div class="relative w-20 h-20 shrink-0">
                        @if($product->images->first())
                            <img src="{{ Storage::url($product->images->first()->path) }}" class="w-full h-full object-cover rounded-xl" />
                        @else
                            <div class="w-full h-full bg-base-200 rounded-xl flex items-center justify-center text-gray-400">
                                <x-icon name="o-photo" class="w-8 h-8" />
                            </div>
                        @endif
                        {{-- 庫存警示標籤 --}}
                        @if($product->total_stock <= ($product->min_stock ?? 0))
                            <span class="absolute -top-2 -left-2 badge badge-error badge-sm text-white">低庫存</span>
                        @endif
                    </div>

                    {{-- 右側：資訊 --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-mono text-gray-500">{{ $product->sku }}</span>
                            <div class="flex gap-1" onclick="event.stopPropagation();">
                                <x-button icon="o-pencil" link="{{ route('products.edit', $product->id) }}" class="btn-ghost btn-xs text-blue-500" />
                                <x-button icon="o-trash" wire:click="delete({{ $product->id }})" wire:confirm="確定刪除？" class="btn-ghost btn-xs text-red-500" />
                            </div>
                        </div>
                        
                        <h3 class="font-bold truncate text-base mb-1 {{ !$product->is_active ? 'text-gray-400 line-through' : '' }}">
                            {{ $product->name }}
                        </h3>

                        <div class="flex justify-between items-end">
                            <div>
                                <span class="text-blue-700 font-extrabold text-lg">NT$ {{ number_format($product->price, 0) }}</span>
                                @if(auth()->user()->role === 'owner')
                                    <p class="text-[10px] text-error opacity-70">成本: {{ number_format($product->cost, 2) }}</p>
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-400">剩餘庫存</p>
                                <p class="font-bold {{ $product->total_stock <= ($product->min_stock ?? 0) ? 'text-error' : 'text-base-content' }}">
                                    {{ $product->total_stock }} <span class="text-[10px] font-normal">{{ $product->unit }}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </x-card>
        @endforeach

        {{-- 手機端分頁 --}}
        <div class="py-4">
            {{ $products->links(data: ['scrollTo' => false]) }}
        </div>
    </div>
	
	{{-- 快速查詢抽屜 (唯讀展示) --}}
    <x-drawer wire:model="drawer" title="商品詳細資料" right separator with-close-button class="w-11/12 lg:w-1/3">
        @if($selectedProduct)
            <div class="space-y-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-widest">SKU Number</p>
                        <p class="font-mono text-xl font-bold">{{ $selectedProduct->sku }}</p>
                    </div>
                    <x-badge :value="$selectedProduct->is_active ? '銷售中' : '已下架'" class="{{ $selectedProduct->is_active ? 'badge-success' : 'badge-warning' }}" />
                </div>

                <x-input label="商品全名" value="{{ $selectedProduct->name }}" readonly icon="o-tag" />

                <div class="grid grid-cols-2 gap-4">
                    <x-input label="零售價 (TWD)" value="NT$ {{ number_format($selectedProduct->price, 0) }}" readonly icon="o-currency-dollar" />
                    <x-input label="預設單位" value="{{ $selectedProduct->unit }}" readonly icon="o-beaker" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <x-input label="當前總庫存" value="{{ $selectedProduct->total_stock }}" readonly icon="o-cube" />
                    <x-input label="警示水位" value="{{ $selectedProduct->min_stock }}" readonly icon="o-bell" />
                </div>

                <x-textarea label="備註內容" readonly rows="4">{{ $selectedProduct->remark }}</x-textarea>

                <div class="bg-base-200 p-4 rounded-lg text-xs space-y-1">
                    <p>建立日期：{{ $selectedProduct->created_at->format('Y-m-d H:i') }}</p>
                    <p>最後更新：{{ $selectedProduct->updated_at->format('Y-m-d H:i') }}</p>
                </div>
            </div>
        @endif

    
		{{-- 注意：這裡使用 PHP 8 的 nullsafe 運算子 ? --}}
		@if($selectedProduct?->images && $selectedProduct->images->count() > 0)
			<x-card title="商品媒體相簿" shadow separator>
				<div class="grid grid-cols-2 gap-4">
					@foreach($selectedProduct->images as $media)
						<div class="relative aspect-square group">
							@php
								$ext = strtolower(pathinfo($media->path, PATHINFO_EXTENSION));
								$isVideo = in_array($ext, ['mp4', 'mov', 'avi', 'webm']);
							@endphp

							@if($isVideo)
								<video src="{{ Storage::url($media->path) }}" class="w-full h-full object-cover rounded-xl bg-black"></video>
								<div class="absolute inset-0 flex items-center justify-center pointer-events-none">
									<x-icon name="o-play-circle" class="w-8 h-8 text-white/50" />
								</div>
							@else
								<a href="{{ Storage::url($media->path) }}" target="_blank" class="block w-full h-full">
									<img src="{{ Storage::url($media->path) }}" class="w-full h-full object-cover rounded-xl shadow-sm border hover:brightness-90 transition-all" />
								</a>
							@endif
						</div>
					@endforeach
				</div>
			</x-card>
		@else
			<div class="text-center py-10 text-gray-400">
				<x-icon name="o-photo" class="w-12 h-12 mx-auto mb-2 opacity-20" />
				<p>目前無媒體檔案</p>
			</div>
		@endif
            
        <x-slot:actions>
            @if($selectedProduct)
				<x-button label="前往完整修改" icon="o-pencil-square" :link="route('products.edit', $selectedProduct->id)" class="btn-primary" />
			@endif
            <x-button label="關閉" @click="$wire.drawer = false" />
        </x-slot:actions>
    </x-drawer>
</div>