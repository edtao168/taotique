{{-- 檔案路徑：resources/views/livewire/sales/returns/index.blade.php --}}
<div>
    <x-header title="銷貨退回管理" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋退單號、原單號或客戶..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="回銷售總覽" icon="o-arrow-left" :link="route('sales.index')" />
            <x-button label="開始退貨" icon="o-plus" class="btn-primary" :link="route('sales.index')" tooltip="請從銷售紀錄中選擇單據進行退貨" />
        </x-slot:actions>
    </x-header>

    <x-card shadow separator>
        {{-- PC 端表格 --}}
        <div class="hidden lg:block">
            <x-table :headers="$headers" :rows="$returns" @row-click="$wire.showDetail($event.detail.id)" ...>
				@scope('cell_return_no', $return) {{-- 匹配 return_no --}}
					<x-badge :value="$return->return_no" class="badge-error badge-outline font-mono" />
				@endscope
				
				@scope('cell_sale.invoice_number', $return) {{-- 匹配 sale.invoice_number --}}
					<span class="text-gray-400 italic">{{ $return->sale->invoice_number }}</span>
				@endscope

				@scope('cell_total_refund_amount', $return) {{-- 匹配 total_refund_amount --}}
					<span class="font-bold text-red-600 font-mono">TWD {{ number_format($return->total_refund_amount, 2) }}</span>
				@endscope
			</x-table>
        </div>

        {{-- 手機端卡片 (參照銷售總覽風格) --}}
        <div class="block lg:hidden space-y-3">
            @foreach($returns as $return)
                <div class="border rounded-xl p-4 bg-base-50 active:bg-base-200 transition-colors" @click="$wire.showDetail({{ $return->id }})">
                    <div class="flex justify-between items-start mb-2">
                        <x-badge :value="$return->return_no" class="badge-error badge-sm font-mono" />
                        <span class="text-[10px] text-gray-500">{{ $return->created_at->format('m/d H:i') }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-bold text-base">{{ $return->sale->customer?->name ?? '零售客戶' }}</p>
                            <p class="text-[10px] text-gray-400 font-mono">原單: {{ $return->sale->invoice_number }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-red-600 font-black text-lg font-mono">NT$ {{ number_format($return->total_refund, 2) }}</p>
                            <span class="badge badge-ghost badge-xs">{{ $return->status }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="mt-4">
                {{ $returns->links(data: ['scrollTo' => false]) }}
            </div>
        </div>
    </x-card>

    {{-- 退貨詳情抽屜 --}}
    <x-drawer wire:model="drawer" title="退貨單據詳情" right separator with-close-button class="w-11/12 lg:w-1/3">
        @if($selectedReturn)
            <div class="space-y-6 pb-20">
                
                {{-- 1. 核心指標 (同步 ReturnCreate 計算邏輯) --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-3 border rounded-xl bg-red-50/50">
                        <p class="text-[10px] text-red-600 mb-1 font-bold">預計退款總額</p>
                        <p class="text-xl font-black text-red-800 font-mono">NT$ {{ number_format($selectedReturn->total_refund, 2) }}</p>
                    </div>
                    <div class="p-3 border rounded-xl bg-base-100">
                        <p class="text-[10px] text-gray-500 mb-1 font-bold">商品退回件數</p>
                        <p class="text-xl font-black font-mono">{{ (int)$selectedReturn->items->sum('quantity') }}</p>
                    </div>
                </div>

                {{-- 2. 費用扣除明細 (同步 SalesReturnFee) --}}
                <div class="bg-base-200/50 rounded-xl p-4">
                    <p class="text-xs font-bold text-gray-500 mb-3 flex items-center gap-1">
                        <x-icon name="o-minus-circle" class="w-3 h-3 text-error" /> 費用扣除明細
                    </p>
                    <div class="space-y-2">
                        @forelse($selectedReturn->fees as $fee)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">{{ $fee->fee_type_name ?? $fee->fee_type }}</span>
                                <span class="font-mono text-error">-{{ number_format($fee->amount, 2) }}</span>
                            </div>
                        @empty
                            <div class="text-center text-xs text-gray-400 py-2">無額外費用扣除</div>
                        @endforelse
                    </div>
                </div>

                {{-- 3. 商品退回細目 --}}
                <div>
                    <p class="text-sm font-bold border-l-4 border-error pl-2 mb-4">商品明細</p>
                    <x-table :headers="[['key' => 'product.name', 'label' => '品名'], ['key' => 'quantity', 'label' => '數'], ['key' => 'subtotal', 'label' => '退款小計', 'class' => 'text-right font-mono']]" :rows="$selectedReturn->items" no-hover>
                        @scope('cell_product.name', $item)
                            <span class="text-xs">{{ $item->product->name }}</span>
                        @endscope
                        @scope('cell_quantity', $item)
                            <span class="font-mono">{{ (int)$item->quantity }}</span>
                        @endscope
                        @scope('cell_subtotal', $item)
                            <span class="text-red-600 font-bold font-mono">NT$ {{ number_format($item->subtotal, 2) }}</span>
                        @endscope
                    </x-table>
                </div>
            </div>

            <x-slot:actions>
                <div class="flex gap-3 w-full border-t pt-4 bg-base-100">
                    <x-button 
                        label="刪除紀錄" 
                        icon="o-trash" 
                        wire:click="delete({{ $selectedReturn->id }})" 
                        wire:confirm="警告：刪除此退貨紀錄將導致庫存回滾至退貨前狀態，確定執行？" 
                        class="btn-error btn-outline flex-1" 
                    />
                    <x-button 
                        label="查看原單" 
                        icon="o-eye" 
                        :link="route('sales.index', ['search' => $selectedReturn->sale->invoice_number])"
                        class="btn-primary flex-1 text-white" 
                    />
                </div>
            </x-slot:actions>
        @endif
    </x-drawer>
</div>