{{-- 檔案路徑：resources/views/livewire/sales/index.blade.php --}}
<div>
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
                @scope('cell_invoice_number', $sale)
                    <x-badge :value="$sale->invoice_number" class="badge-neutral font-mono" />
                @endscope
                @scope('cell_customer_total', $sale)
                    <span class="font-bold text-blue-700">NT$ {{ number_format($sale->customer_total, 0) }}</span>
                @endscope
            </x-table>
        </div>

        {{-- 手機端卡片 --}}
        <div class="block lg:hidden space-y-3">
            @foreach($sales as $sale)
                <div class="border rounded-xl p-4 bg-base-50 active:bg-base-200 transition-colors" @click="$wire.showDetail({{ $sale->id }})">
                    <div class="flex justify-between items-start mb-2">
                        <x-badge :value="$sale->invoice_number" class="badge-neutral badge-sm font-mono" />
                        <span class="text-[10px] text-gray-500">{{ $sale->sold_at->format('m/d H:i') }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-bold text-base">{{ $sale->customer?->name ?? '一般客戶' }}</p>
                            <p class="text-xs text-gray-400">
								{{ strtoupper($sale->channel) }} / 
								{{ collect(config('business.payment_methods'))->firstWhere('id', $sale->payment_method)['name'] ?? $sale->payment_method }}
							</p>
                        </div>
                        <div class="text-right">
                            <p class="text-blue-700 font-black text-lg">NT$ {{ number_format($sale->customer_total, 0) }}</p>
                            <p class="text-[10px] text-emerald-600 font-bold">利潤: {{ number_format($sale->final_net_amount, 0) }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="mt-4">
                {{ $sales->links(data: ['scrollTo' => false]) }}
            </div>
        </div>
    </x-card>

    {{-- 詳情抽屜 (與之前邏輯一致) --}}
	<x-drawer wire:model="drawer" title="銷貨單據詳情" right separator with-close-button class="w-11/12 lg:w-1/3" >
		
		@if($selectedSale)		
			<div class="space-y-6 pb-20">
		
			{{-- 基本資訊：日期、客戶、通路、付款方式 (並排顯示) --}}
			<div class="bg-base-100 border rounded-xl p-4 shadow-sm">
				<div class="flex items-center gap-2 mb-3 border-b pb-2">
					<x-icon name="o-information-circle" class="w-4 h-4 text-primary" />
					<span class="text-sm font-bold">單據基本資訊</span>
				</div>
				<div class="grid grid-cols-2 gap-y-4 text-sm">
					<div>
						<p class="text-[10px] text-gray-400">銷售日期</p>
						<p class="font-medium text-gray-700">{{ $selectedSale->sold_at->format('Y-m-d') }}</p>
					</div>
					<div>
						<p class="text-[10px] text-gray-400">客戶名稱</p>
						<p class="font-medium text-blue-700">{{ $selectedSale->customer?->name ?? '一般客戶' }}</p>
					</div>
					<div>
						<p class="text-[10px] text-gray-400">銷售通路</p>						
						<x-badge :value="$selectedSale->shop?->name ?? strtoupper($selectedSale->channel)" class="badge-outline badge-sm" />
					</div>
					<div>
						<p class="text-[10px] text-gray-400">付款方式</p>
						<x-badge :value="$selectedSale->payment_method_name" class="badge-ghost badge-sm font-bold" />
					</div>					
				</div>
			</div>
			
			{{-- 1. 核心指標：買家支付 vs 單據淨利 --}}
			<div class="grid grid-cols-2 gap-4">
				<div class="p-3 border rounded-xl bg-blue-50/50">
					<p class="text-[10px] text-blue-600 mb-1 font-bold">買家支付總額</p>
					<p class="text-xl font-black text-blue-800 font-mono">NT$ {{ number_format($selectedSale->customer_total, 0) }}</p>
				</div>
				<div class="p-3 border rounded-xl bg-emerald-50/50">
					<p class="text-[10px] text-emerald-600 mb-1 font-bold">預計單據淨利</p>
					<p class="text-xl font-black text-emerald-800 font-mono">NT$ {{ number_format($selectedSale->final_net_amount, 0) }}</p>
				</div>
			</div>			

			{{-- 3. 買家細目與賣家支出 (並排顯示) --}}
			<div class="grid grid-cols-2 gap-3">
				{{-- 買家側 --}}
				<div class="space-y-3 p-3 border rounded-lg bg-base-100 shadow-sm">
					<div class="badge badge-info badge-outline badge-sm font-bold text-[10px]">買家</div>
					<div class="space-y-1">
						<p class="text-[10px] text-gray-400">買家付運費</p>
						<p class="text-sm font-mono font-bold text-gray-700">NT$ {{ number_format($selectedSale->shipping_fee_customer, 0) }}</p>
						<p class="text-[10px] text-gray-400">賣場折扣</p>
						<p class="text-sm font-mono font-bold text-success">NT$ {{ number_format($selectedSale->discount, 0) }}</p>
						<p class="text-[10px] text-gray-400">平台優惠券</p>
						<p class="text-sm font-mono font-bold text-success">NT$ {{ number_format($selectedSale->platform_coupon, 0) }}</p>
					</div>
				</div>

				{{-- 賣家側 --}}
				<div class="space-y-3 p-3 border rounded-lg bg-base-100 shadow-sm">
					<div class="badge badge-error badge-outline badge-sm font-bold text-[10px]">賣家</div>
					<div class="space-y-1">
						<p class="text-[10px] text-gray-400">成交手續費</p>
						<p class="text-sm font-mono font-bold text-gray-700">NT$ {{ number_format($selectedSale->platform_fee, 0) }}</p>                    
						<p class="text-[10px] text-gray-400">平台代付運費</p>
						<p class="text-sm font-mono font-bold text-gray-700">NT$ {{ number_format($selectedSale->shipping_fee_platform, 0) }}</p>
						<p class="text-[10px] text-gray-400">帳款調整</p>
						<p class="text-sm font-mono font-bold {{ $selectedSale->order_adjustment >= 0 ? 'text-success' : 'text-error' }}">
							NT$ {{ number_format($selectedSale->order_adjustment, 0) }}
						</p>
					</div>
				</div>
			</div>

			{{-- 4. 商品明細 (保持手機卡片/PC表格分流) --}}
			<div>
				<div class="flex justify-between items-center mb-4 px-1">
					<p class="text-sm font-bold border-l-4 border-primary pl-2">商品明細</p>
					<span class="text-xs text-gray-400 font-mono">共 {{ $selectedSale->items->count() }} 項</span>
				</div>
				
				{{-- 手機端卡片 --}}
				<div class="lg:hidden space-y-3">
					@foreach($selectedSale->items as $item)
						<div class="p-4 border rounded-xl bg-base-50 shadow-sm">
							<div class="flex justify-between items-start mb-2">
								<span class="font-bold text-sm text-gray-700 line-clamp-1 w-3/4">{{ $item->product->full_display_name }}</span>
								<x-badge :value="'x' . (int)$item->quantity" class="badge-neutral font-mono" />
							</div>
							<div class="flex justify-between items-end">
								<div class="flex flex-col">
									<span class="text-[10px] text-gray-400 font-mono">成本: {{ number_format($item->cost_snapshot, 0) }}</span>
									<span class="text-xs text-gray-500 italic font-mono">單價: {{ number_format($item->price, 0) }}</span>
								</div>
								<div class="text-right">
									<span class="text-blue-700 font-black text-lg font-mono">NT$ {{ number_format($item->subtotal, 0) }}</span>
								</div>
							</div>
						</div>
					@endforeach
				</div>

				{{-- PC 端表格 --}}
				<div class="hidden lg:block">
					<x-table :headers="[['key' => 'product.name', 'label' => '品名'], ['key' => 'warehouse.name', 'label' => '庫別', 'class' => 'text-right'], ['key' => 'quantity', 'label' => '數量', 'class' => 'text-right'], ['key' => 'subtotal', 'label' => '小計', 'class' => 'text-right font-mono']]" :rows="$selectedSale->items" no-hover>
						@scope('cell_product.name', $item)
							<div class="flex flex-col">
								<span class="font-medium text-sm">{{ $item->product->full_display_name }}</span>
								<span class="text-[10px] text-gray-400 font-mono">成本: {{ number_format($item->cost_snapshot, 0) }}</span>
							</div>
						@endscope
						@scope('cell_subtotal', $item)
							<span class="text-blue-700 font-bold italic font-mono">NT$ {{ number_format($item->subtotal, 0) }}</span>
						@endscope
					</x-table>
				</div>
			</div>
		</div>
		{{-- 底部固定動作欄：刪除與修改 --}}
			<x-slot:actions>
				<div class="flex gap-3 w-full border-t pt-4 bg-base-100">
					<x-button 
						label="刪除" 
						icon="o-trash" 
						wire:click="delete({{ $selectedSale->id }})" 
						wire:confirm="警告：刪除銷售單將自動回補庫存。確定執行？" 
						class="btn-error btn-outline flex-1" 
					/>
					<x-button 
						label="修改" 
						icon="o-pencil" 
						:link="route('sales.edit', $selectedSale->id)" 
						class="btn-primary flex-1 text-white" 
					/>
					<x-button 
						label="退貨" 
						icon="o-arrow-path" 
						:link="route('sales.returns.create', ['sale' => $selectedSale->id])"
						class="btn-outline-dark flex-1"	
					/>
				</div>
			</x-slot:actions>
		@endif
	</x-drawer>
</div>