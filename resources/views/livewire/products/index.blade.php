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

    <x-card shadow>
        {{-- 點擊整列改為觸發 showDetail 查詢 --}}
        <x-table :headers="$headers" :rows="$products" @row-click="$wire.showDetail($event.detail.id)" class="cursor-pointer" with-pagination>
            {{-- SKU 樣式 --}}
            @scope('cell_sku', $product)
                <x-badge :value="$product->sku" class="badge-neutral font-mono" />
            @endscope
			{{-- 縮略圖 --}}
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
					{{-- 使用大型、深色背景的 Badge，字體加粗且間距加寬 --}}
					<x-badge 
						:value="$product->sku" 
						class="badge-neutral font-mono text-sm font-bold px-4 py-3 tracking-tighter" 
					/>
					
					{{-- 如果商品已下架，僅顯示一個簡單的顏色點提示，不干擾視覺 --}}
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
					$ {{ number_format($product->cost, 2) }}
				@endscope
			@endif
            {{-- 零售價樣式 --}}
            @scope('cell_price', $product)
                <span class="font-bold text-blue-700 text-sm">NT$ {{ number_format($product->price, 0) }}</span>
            @endscope

            {{-- 總庫存 --}}
            @scope('cell_total_stock', $product)
                <span class="@if($product->total_stock <= ($product->min_stock ?? 0)) text-error font-bold @endif">
                    {{ $product->total_stock }}
                </span>
            @endscope

            {{-- 操作按鈕 --}}
            @scope('actions', $product)
				<div class="flex gap-2">
					{{-- 注意 link 屬性的寫法 --}}
					<x-button 
						icon="o-pencil" 
						link="{{ route('products.edit', $product->id) }}" 
						class="btn-ghost btn-sm text-blue-500" 
						tooltip="修改商品" 
						onclick="event.stopPropagation();" 
					/>
					<x-button 
						icon="o-trash" 
						wire:click="delete({{ $product->id }})" 
						wire:confirm="確定刪除？" 
						class="btn-ghost btn-sm text-red-500" 
						tooltip="刪除" 
						onclick="event.stopPropagation();" 
					/>
				</div>
			@endscope
        </x-table>
    </x-card>
	
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