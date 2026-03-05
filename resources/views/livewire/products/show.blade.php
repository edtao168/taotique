<div>
    <x-header :title="'商品詳情: ' . $product->sku" separator>
        <x-slot:actions>
            <x-button label="返回列表" icon="o-arrow-left" link="/products" />
            <x-button label="編輯" icon="o-pencil" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- 基本資訊 --}}
        <x-card title="基本資料" shadow>
            <x-list-item :item="$product" sub-value="sku">
                <x-slot:avatar>
                    <x-icon name="o-cube" class="w-10 h-10 text-primary" />
                </x-slot:avatar>
            </x-list-item>
            <div class="mt-4 space-y-2">
                <div class="flex justify-between"><span>備註:</span> <strong>{{ $product->remark }}</strong></div>
                <div class="flex justify-between"><span>目前售價:</span> <strong class="text-blue-600">NT$ {{ number_format($product->price) }}</strong></div>
            </div>
        </x-card>

        {{-- 各庫別分布 (Read - 庫存分布) --}}
        <div class="md:col-span-2">
            <x-card title="庫存分佈" shadow>
                <x-table :headers="[['key'=>'w_name', 'label'=>'庫別'], ['key'=>'qty', 'label'=>'數量'], ['key'=>'cost', 'label'=>'進貨成本(TWD)']]" 
                         :rows="$product->inventories->map(fn($i) => ['w_name'=>$i->warehouse->name, 'qty'=>$i->quantity, 'cost'=>$i->cost])">
                    @scope('cell_qty', $i)
                        <x-badge :value="$i['qty']" class="badge-neutral" />
                    @endscope
                </x-table>
            </x-card>			
        </div>		
    </div>
	{{-- 放在 show.blade.php 最後面 --}}
    <x-card title="商品媒體相簿" shadow separator>
		@if($product->images->count() > 0)
			<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
				@foreach($product->images as $media)
					<div class="relative aspect-square group">
						@php
							$ext = strtolower(pathinfo($media->path, PATHINFO_EXTENSION));
							$isVideo = in_array($ext, ['mp4', 'mov', 'avi', 'webm']);
						@endphp

						@if($isVideo)
							<video src="{{ Storage::url($media->path) }}" controls class="w-full h-full object-cover rounded-xl bg-black shadow-sm"></video>
						@else
							{{-- 查詢頁面可以加入點擊放大的功能 --}}
							<a href="{{ Storage::url($media->path) }}" target="_blank" class="block w-full h-full">
								<img src="{{ Storage::url($media->path) }}" class="w-full h-full object-cover rounded-xl shadow-sm border hover:brightness-90 transition-all" />
							</a>
						@endif
					</div>
				@endforeach
			</div>
		@else
			<div class="text-center py-10 text-gray-400">
				<x-icon name="o-photo" class="w-12 h-12 mx-auto mb-2 opacity-20" />
				<p>目前無媒體檔案</p>
			</div>
		@endif
	</x-card>
</div> {{-- 最外層 div 結束 --}}