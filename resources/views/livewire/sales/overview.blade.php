{{-- resources/views/livewire/sales/overview.blade.php --}}
<div>
    {{-- 數據指標卡 --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        {{-- 本月銷售與趨勢 --}}
        <x-stat 
            title="本月銷售額" 
            value="NT$ {{ number_format($monthSales) }}" 
            icon="o-shopping-cart"
            {{-- 根據成長或衰退顯示不同顏色與圖標 --}}
            description="{{ $salesGrowth >= 0 ? '▲' : '▼' }} 較上月 {{ abs($salesGrowth) }}%"
            class="{{ $salesGrowth >= 0 ? 'text-success' : 'text-error' }}"
        />

        {{-- 本月淨利 --}}
        <x-stat 
            title="本月累計淨利" 
            value="NT$ {{ number_format($monthProfit) }}" 
            icon="o-banknotes"
            description="已扣除平台費與進貨成本"
            class="text-primary"
        />

        {{-- 本年銷售 --}}
        <x-stat 
            title="本年度總銷售" 
            value="NT$ {{ number_format($yearSales) }}" 
            icon="o-arrow-trending-up"
            description="{{ date('Y') }} 年 1 月至今"
        />		
		
    </div>
    

    <x-card title="本月銷售紀錄" subtitle="僅顯示本月份產生的訂單" shadow separator>
        @if($recentSales->isEmpty())
            <div class="text-center py-10 text-gray-400">
                目前尚無銷售紀錄，請點擊上方按鈕新增。
            </div>
        @else
            <x-table :headers="[
                ['key' => 'invoice_number', 'label' => '編號'],
				['key' => 'channel', 'label' => '通路'],
                ['key' => 'customer.name', 'label' => '客戶'],
                ['key' => 'customer_total', 'label' => '實收'],
                ['key' => 'created_at', 'label' => '日期'],
            ]" :rows="$recentSales">
                @row-click="$wire.showDetail($event.detail.id)" {{-- 參考商品頁面設計 --}}
				class="cursor-pointer" {{-- 讓滑鼠移上去顯示手指圖示 --}}
				with-pagination
			>
				@scope('cell_invoice_number', $sale)
					<x-badge :value="$sale->invoice_number" class="badge-neutral font-mono" />
				@endscope

				@scope('cell_customer.name', $sale)
					{{ $sale->customer->name ?? '訪客' }}
				@endscope

				@scope('actions', $sale)
					<div class="flex gap-2">
						<x-button icon="o-pencil" :link="route('sales.edit', $sale->id)" class="btn-ghost btn-sm text-blue-500" @click.stop />
						<x-button icon="o-trash" wire:click="delete({{ $sale->id }})" wire:confirm="確定刪除並還原庫存？" class="btn-ghost btn-sm text-error" @click.stop />
					</div>
				@endscope
            </x-table>
			<div class="mt-4">
				{{ $recentSales->links() }}
			</div>
        @endif
    </x-card>
	
	{{-- 快速查詢抽屜 --}}
    <x-drawer wire:model="drawer" title="訂單查詢" right separator with-close-button class="w-1/3">
		@if($selectedSale)
			<div class="space-y-4">
				<div class="flex justify-between">
					<span class="text-gray-500">訂單單號</span>
					<span class="font-mono font-bold">{{ $selectedSale->invoice_number }}</span>
				</div>
				<x-input label="客戶" value="{{ $selectedSale->customer->name }}" readonly />
				<div class="bg-base-200 p-4 rounded-lg">
					<p class="text-xs font-bold mb-2">商品明細</p>
					@foreach($selectedSale->items as $item)
						<div class="flex justify-between text-xs border-b py-1">
							<span>{{ $item->product->name }} x {{ (int)$item->quantity }}</span>
							<span>NT$ {{ number_format($item->subtotal, 2) }}</span>
						</div>
					@endforeach
				</div>
			</div>
		@endif
		<x-slot:actions>
			<x-button label="編輯訂單" icon="o-pencil" :link="route('sales.edit', $selectedSale->id ?? 0)" class="btn-primary" />
		</x-slot:actions>
	</x-drawer>
</div>