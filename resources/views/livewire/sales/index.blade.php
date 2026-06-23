{{-- 檔案路徑：resources/views/livewire/sales/index.blade.php --}}
@php
    use App\Enums\WorkflowStatus;
@endphp
<div x-data="{ 
        atBottom: false,
        checkScroll() {
            this.atBottom = (window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 100);
        }
     }" 
     x-init="checkScroll()"
     @scroll.window="checkScroll()">
    <x-header title="銷售數據概況" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋單號或客戶..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
			<x-button label="新增銷貨" icon="o-plus" :link="route('sales.create')" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- 1. 數據指標卡 (PC/手機通用) --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-stat title="本月銷售額" value="NT$ {{ number_format($monthSales) }}" icon="o-shopping-cart"
            description="{{ $salesGrowth >= 0 ? '▲' : '▼' }} {{ abs(round($salesGrowth, 1)) }}%"
            class="{{ $salesGrowth >= 0 ? 'text-success' : 'text-error' }}" shadow />
        
        <x-stat title="本月預計淨利" value="NT$ {{ number_format($monthProfit) }}" icon="o-banknotes"
            description="扣除成本與平台費" class="text-primary" shadow />

        <x-stat title="本年度總計" value="NT$ {{ number_format($yearSales) }}" icon="o-arrow-trending-up" shadow />
    </div>

    {{-- 2. 銷售清單區域 --}}
    <x-card title="最近銷售紀錄" shadow separator>
        
        {{-- PC 端表格 --}}
		<div class="hidden lg:block">
			<x-table :headers="$headers" :rows="$sales" @row-click="$wire.showDetail($event.detail.id)" class="cursor-pointer" with-pagination>
				{{-- 單號 --}}
				@scope('cell_invoice_number', $sale)
					<x-badge :value="$sale->invoice_number" 
							 :class="$sale->status->color() . ' font-mono'"
							 title="{{ $sale->status->label() }}" />
				@endscope
				
				{{-- 新增：狀態欄位 --}}
				@scope('cell_status', $sale)
					<x-badge :value="$sale->status->label()" 
							 :class="$sale->status->color() . ' badge-sm'" />
				@endscope
				
				{{-- 通路 --}}
				@scope('cell_channel_id', $sale)
					<x-badge :value="$sale->channel->name ?? '未分類'" class="badge-primary badge-outline" />
				@endscope
				
				{{-- 金額欄位 --}}
				@scope('cell_customer_total', $sale)
					<span class="font-bold text-info">NT$ {{ number_format($sale->customer_total, 0) }}</span>
				@endscope
				
				@scope('cell_final_net_amount', $sale)
					<span class="font-bold text-success">NT$ {{ number_format($sale->final_net_amount, 0) }}</span>
				@endscope
			</x-table>
		</div>

        {{-- 手機端卡片 --}}
		<div class="block lg:hidden space-y-3">
			@foreach($sales as $sale)
				<div class="border rounded-xl p-4 bg-base-50 active:bg-base-200 transition-colors" @click="$wire.showDetail({{ $sale->id }})">
					<div class="flex justify-between items-start mb-2">
						<div class="flex flex-col gap-1">
							<div class="flex items-center gap-2">
								<x-badge :value="$sale->invoice_number" class="badge-neutral badge-sm font-mono" />
								{{-- 新增：狀態標籤 --}}
								<x-badge :value="$sale->status->label()" 
										 :class="$sale->status->color() . ' badge-xs'" />
							</div>
							<span class="text-[10px] opacity-60">
								<x-icon name="o-home-modern" class="w-3 h-3" /> 
								{{ $sale->warehouse?->name ?? '未指定倉庫' }}
							</span>
						</div>
						<span class="text-[10px] text-gray-500">{{ $sale->sold_at->format('m/d') }}</span>
					</div>
					<div class="flex justify-between items-center">
						<div>
							<p class="font-bold text-base">{{ $sale->customer?->name ?? '一般客戶' }}</p>
							<p class="text-xs text-gray-400">
								{{ $sale->payment_method_name ?? $sale->payment_method }}
							</p>
						</div>
						<div class="text-right">
							<p class="text-blue-700 font-black text-lg">NT$ {{ number_format($sale->customer_total, 0) }}</p>
							<p class="text-[10px] text-emerald-600 font-bold">最終訂單進帳 : {{ number_format($sale->final_net_amount, 0) }}</p>
						</div>
					</div>
				</div>
			@endforeach
			<div class="mt-4">
				{{ $sales->links(data: ['scrollTo' => false]) }}
			</div>
		</div>
    </x-card>

	{{-- 滾動提示 --}}
	<div x-show="!atBottom" 
		 x-transition:enter="transition ease-out duration-300"
		 x-transition:enter-start="opacity-0 transform translate-y-4"
		 x-transition:leave="transition ease-in duration-300"
		 x-transition:leave-end="opacity-0 transform translate-y-4"
		 class="hidden lg:flex fixed bottom-6 right-6 z-50 pointer-events-none">
		
		<div class="flex flex-col items-center">
			<span class="text-xs font-bold text-orange-600 bg-orange-50 px-2 py-1 rounded-full shadow-sm mb-1">下面還有</span>
			<div class="bg-orange-500 text-white p-3 rounded-full shadow-lg animate-bounce">
				<x-icon name="o-chevron-double-down" class="w-6 h-6" />
			</div>
		</div>
	</div>
	
	{{-- 詳情抽屜 --}}
	<x-drawer wire:model="drawer" title="銷貨單據詳情" right separator with-close-button class="w-11/12 lg:w-1/3" >
	
		@if($selectedSale?->hasReturnRecords())
			{{-- 浮水印容器 --}}
			<div class="absolute inset-0 flex items-center justify-center overflow-hidden pointer-events-none select-none z-50">
				<div class="border-8 border-error/30 text-error/30 text-7xl font-black uppercase tracking-widest px-8 py-4 rounded-xl border-dashed -rotate-12 transform">
					已退貨
				</div>
			</div>
		@endif
		
		@if($selectedSale)
			@php
				// 呼叫我們在 Model 定義的邏輯
				$isLocked = $selectedSale->hasReturnRecords();
			@endphp
			<div class="space-y-6 pb-20">
				{{-- 基礎單據資訊（包含狀態合併在表頭） --}}
				<div class="bg-base-100 border rounded-xl p-4 shadow-sm">
					{{-- 表頭：單號 + 狀態 + 時間戳 --}}
					<div class="flex flex-wrap items-center justify-between gap-2 mb-3 pb-3 border-b border-base-200">
						<div class="flex items-center gap-3">
							<h3 class="font-mono font-bold text-lg text-gray-800">
								{{ $selectedSale->invoice_number }}
							</h3>
							<x-badge :value="$selectedSale->status->label()" 
									 :class="$selectedSale->status->color() . ' text-base'" />
							@if($selectedSale->hasReturnRecords())
								<x-badge value="已退貨" class="badge-error text-white" />
							@endif
						</div>
						<div class="text-[10px] text-gray-400 text-right">
							<div>更新於：{{ $selectedSale->updated_at->format('Y-m-d H:i') }}</div>
							@if($selectedSale->stocked_out_at)
								<div>過帳於：{{ $selectedSale->stocked_out_at->format('Y-m-d H:i') }}</div>
							@endif
						</div>
					</div>
					
					{{-- 單據資訊網格 --}}
					<div class="grid grid-cols-2 gap-y-4 text-sm">
						<div>
							<p class="text-[10px] text-gray-400">客戶名稱</p>
							<p class="font-medium text-blue-700">{{ $selectedSale->customer?->name ?? '一般客戶' }}</p>
						</div>						
						<div>
							<p class="text-[10px] text-gray-400">銷售通路</p>						
							<x-badge :value="$selectedSale->shop?->name ?? strtoupper($selectedSale->channel)" class="badge-outline badge-sm" />
						</div>
						<div>
							<p class="text-[10px] text-gray-400">出庫倉庫</p>
							<x-badge :value="$selectedSale->warehouse?->name ?? '未指定'" class="badge-ghost badge-sm font-bold" />
						</div>
						<div>
							<p class="text-[10px] text-gray-400">銷售日期</p>
							<p class="font-medium">{{ $selectedSale->sold_at->format('Y-m-d') }}</p>
						</div>
					</div>
				</div>
			
				{{-- 1. 核心指標：買家支付 vs 單據淨利 --}}
				<div class="grid grid-cols-2 gap-4">
					<div class="p-3 border rounded-xl bg-blue-50/50">
						<p class="text-[10px] text-info mb-1 font-bold">買家支付總額</p>
						<p class="text-xl font-black text-blue-800 font-mono">NT$ {{ number_format($selectedSale->customer_total, 0) }}</p>
					</div>
					<div class="p-3 border rounded-xl bg-emerald-50/50">
						<p class="text-[10px] text-success mb-1 font-bold">最終單據進帳</p>
						<p class="text-xl font-black text-emerald-800 font-mono">NT$ {{ number_format($selectedSale->final_net_amount, 0) }}</p>
					</div>
				</div>			

				{{-- 2. 動態顯示 JSON 費用細目 --}}
				<div class="grid grid-cols-2 gap-3">
					{{-- 左側：買家側項目 (如運費、折扣、優惠券) --}}
					<div class="space-y-3 p-3 border rounded-lg bg-base-100 shadow-sm">
						<div class="badge badge-warning badge-outline badge-sm font-bold text-[10px]">賣家折扣</div>
						<div>
							<p class="text-[10px] text-gray-400">賣家折扣</p>
							<p class="text-sm font-mono font-bold text-amber-600">
								- NT$ {{ number_format(abs($selectedSale->seller_discount ?? 0), 0) }}
							</p>
						</div>
        
						<div class="divider my-2"></div>
					
						<div class="badge badge-info badge-outline badge-sm font-bold text-[10px]">買家細目</div>
						<div class="space-y-2">
							@php
								$displayTargets = ['customer', 'both', 'revenue_adjustment'];
								$customerFeeTypes = collect(config('business.fee_types'))
									->filter(fn($config, $key) => in_array($config['target'] ?? '', $displayTargets));
							@endphp

							@foreach($customerFeeTypes as $key => $fee)
								@php
									$amount = $selectedSale->$key ?? 0;
								@endphp
								@if($amount != 0)
									<div>
										<p class="text-[10px] text-gray-400">{{ $fee['name'] }}</p>
										<p class="text-sm font-mono font-bold {{ ($fee['operator'] ?? 'add') === 'sub' ? 'text-success' : 'text-gray-700' }}">
											{{ ($fee['operator'] ?? 'add') === 'sub' ? '-' : '+' }} 
											NT$ {{ number_format(abs($amount), 0) }}
										</p>
									</div>
								@endif
							@endforeach
						</div>
					</div>

					{{-- 右側：賣家側項目 (如手續費、佣金、帳款調整) --}}
					<div class="space-y-3 p-3 border rounded-lg bg-base-100 shadow-sm">
						<div class="badge badge-success badge-outline badge-sm font-bold text-[10px]">賣家支出 / 調整</div>
						<div class="space-y-2">
							@foreach(collect(config('business.fee_types'))->where('target', 'seller') as $key => $fee)
								<div>
									<p class="text-[10px] text-gray-400">{{ $fee['name'] }}</p>
									{{-- 針對帳款調整顯示正負號，其餘顯示支出符號 --}}
									<p class="text-sm font-mono font-bold {{ ($selectedSale->$key ?? 0) < 0 ? 'text-error' : 'text-gray-700' }}">
										{{ $key === 'order_adjustment' && ($selectedSale->$key ?? 0) > 0 ? '+' : '' }}
										NT$ {{ number_format($selectedSale->$key ?? 0, 0) }}
									</p>
								</div>
							@endforeach
						</div>
					</div>
				</div>

				{{-- 3. 商品明細 --}}
				<div>
					<div class="flex justify-between items-center mb-4 px-1">
						<p class="text-sm font-bold border-l-4 border-primary pl-2">商品明細</p>
						<span class="text-xs text-gray-400 font-mono">共 {{ $selectedSale->items->count() }} 項</span>
					</div>
					
					{{-- 手機端商品卡片 --}}
					<div class="lg:hidden space-y-3">
						@foreach($selectedSale->items as $item)
							<div class="p-4 border rounded-xl bg-base-50 shadow-sm">
								<div class="flex justify-between items-start mb-2">
									<span class="font-bold text-sm text-gray-700 line-clamp-1 w-3/4">{{ $item->product->full_display_name }}</span>
									<x-badge :value="'x' . (int)$item->quantity" class="badge-neutral font-mono" />
								</div>
								<div class="flex justify-between items-end">
									<div class="flex flex-col">
										<span class="text-[10px] text-gray-400 font-mono">庫別: {{ $item->warehouse?->name ?? '預設' }}</span>
										<span class="text-xs text-gray-500 italic font-mono">單價: {{ number_format($item->price, 0) }}</span>
									</div>
									<div class="text-right">
										<span class="text-blue-700 font-black text-lg font-mono">NT$ {{ number_format($item->subtotal, 0) }}</span>
									</div>
								</div>
							</div>
						@endforeach
					</div>

					{{-- PC 端商品表格 --}}
					<div class="hidden lg:block">
						<x-table :headers="[['key' => 'product.name', 'label' => '品名'], ['key' => 'warehouse.name', 'label' => '庫別', 'class' => 'text-right'], ['key' => 'quantity', 'label' => '數量', 'class' => 'text-right'], ['key' => 'subtotal', 'label' => '小計', 'class' => 'text-right font-mono']]" :rows="$selectedSale->items" no-hover>
							@scope('cell_product.name', $item)
								<div class="flex flex-col">
									<span class="font-medium text-sm">{{ $item->product->full_display_name }}</span>		
								</div>
							@endscope
							@scope('cell_subtotal', $item)
								<span class="text-blue-700 font-bold italic font-mono">NT$ {{ number_format($item->subtotal, 0) }}</span>
							@endscope
						</x-table>
					</div>
				</div>
			</div>

			{{-- 底部固定動作欄 --}}
			<x-slot:actions>
				<div class="flex gap-3 w-full border-t pt-4 bg-base-100">
					<x-button label="返回" icon="o-arrow-uturn-left" :link="route('sales.index')" class="btn-success flex-1 text-white" />
					
					@if(!$isLocked)
						@if($selectedSale->status->isEditable())
							{{-- 草稿或待審核狀態：可編輯 --}}
							<x-button label="修改" icon="o-pencil" :link="route('sales.edit', $selectedSale->id)" class="btn-primary flex-1 text-white" />
						@endif
						
						@if(!$selectedSale->stocked_out_at && $selectedSale->status->canApprove())
							{{-- 未過帳且可審核：顯示出庫按鈕 --}}
							<x-button 
								label="過帳" 
								icon="o-archive-box-arrow-down" 
								class="btn-warning flex-1"
								wire:click="submitStockOut({{ $selectedSale->id }})"
								wire:confirm="確定要執行出庫扣減庫存嗎？這將會同步產生會計日記帳與主營成本結轉！"
								spinner 
							/>
						@endif
						
						@if($canSettle)
							<x-button 
								label="結算" 
								icon="o-banknotes" 
								class="btn-success flex-1 text-white"
								wire:click="settleSale({{ $selectedSale->id }})"
								wire:confirm="確定要將此訂單標記為已結算嗎？"
								spinner 
							/>
						@endif
						
						@if($selectedSale->stocked_out_at && $selectedSale->status === WorkflowStatus::APPROVED)
							{{-- 退貨按鈕 (保持不變) --}}
							<x-button 
								label="退貨" 
								icon="o-arrow-path" 
								:link="route('sales.returns.create', ['sale' => $selectedSale->id])"
								class="btn-outline-dark flex-1"
							/>
						@endif

						
						@if($selectedSale->status->isDeletable())
							{{-- 可刪除狀態：顯示刪除按鈕 --}}
							<x-button 
								label="刪除" 
								icon="o-trash" 
								wire:click="delete({{ $selectedSale->id }})" 
								wire:confirm="確定要刪除此單據並回補庫存嗎？" 
								class="btn-error btn-outline flex-1" 
							/>
						@endif
					@else
						<div class="text-center py-2 bg-warning/10 border border-warning/30 text-warning rounded-lg text-xs flex-1">
							<x-icon name="o-check-circle"/> 
							{{ $selectedSale->status->label() }}{{ $selectedSale->hasReturnRecords() ? '且已退貨' : '' }}，不可異動！
						</div>
					@endif
				</div>
			</x-slot:actions>
		@endif
	</x-drawer>
</div>