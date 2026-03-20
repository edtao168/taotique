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
                            <p class="text-xs text-gray-400">{{ strtoupper($sale->channel) }} / {{ $sale->payment_method }}</p>
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
    {{-- 檔案路徑：resources/views/livewire/sales/index.blade.php (Drawer 部分) --}}

<x-drawer wire:model="drawer" title="銷貨單據詳情" right separator with-close-button class="w-1/2 lg:w-1/2">
    @if($selectedSale)
        <div class="space-y-6 pb-20"> {{-- 預留底部空間給按鈕 --}}
            
            {{-- 狀態與核心數據 --}}
            <div class="flex justify-between items-center bg-base-200 p-4 rounded-lg">
                <div>
                    <p class="text-xs text-gray-500">單據編號</p>
                    <p class="font-mono font-bold text-lg">{{ $selectedSale->invoice_number }}</p>
                </div>
                <x-badge :value="strtoupper($selectedSale->channel)" class="badge-primary" />
            </div>

            {{-- 財務概覽卡片 --}}
            <div class="grid grid-cols-2 gap-4">
                <div class="p-3 border rounded-xl bg-blue-50/50">
                    <p class="text-xs text-blue-600 mb-1">買家支付總額</p>
                    <p class="text-xl font-black text-blue-800">NT$ {{ number_format($selectedSale->customer_total, 0) }}</p>
                </div>
                <div class="p-3 border rounded-xl bg-emerald-50/50">
                    <p class="text-xs text-emerald-600 mb-1">預計單據淨利</p>
                    <p class="text-xl font-black text-emerald-800">NT$ {{ number_format($selectedSale->final_net_amount, 0) }}</p>
                </div>
            </div>

            {{-- 交易資訊列表 --}}
            <x-card title="交易資訊" separator shadow class="bg-base-100">
                <div class="grid grid-cols-2 gap-y-3 text-sm">
                    <span class="text-gray-500">銷售日期</span>
                    <span class="text-right font-medium">{{ $selectedSale->sold_at->format('Y-m-d H:i') }}</span>
                    
                    <span class="text-gray-500">客戶名稱</span>
                    <span class="text-right font-medium text-blue-700">{{ $selectedSale->customer?->name ?? '一般客戶' }}</span>
                    
                    <span class="text-gray-500">支付方式</span>
                    <span class="text-right"><x-badge :value="$selectedSale->payment_method" class="badge-outline badge-sm" /></span>
                    
                    <span class="text-gray-500">物流費用</span>
                    <span class="text-right font-mono text-error">- {{ number_format($selectedSale->shipping_fee ?? 0) }}</span>
                    
                    <span class="text-gray-500">平台手續費</span>
                    <span class="text-right font-mono text-error">- {{ number_format($selectedSale->platform_fee ?? 0) }}</span>
                </div>
            </x-card>

            {{-- 商品明細表格 - 使用 Mary UI Table 處理複雜排版 --}}
            <div>
                <div class="flex justify-between items-center mb-2 px-1">
                    <p class="text-sm font-bold border-l-4 border-primary pl-2">商品明細</p>
                    <span class="text-xs text-gray-400 font-mono">共 {{ $selectedSale->items->count() }} 項</span>
                </div>
                
                <x-table :headers="[
                    ['key' => 'product.name', 'label' => '品名'],
                    ['key' => 'quantity', 'label' => '數量', 'class' => 'text-right'],
                    ['key' => 'subtotal', 'label' => '小計', 'class' => 'text-right font-mono']
                ]" :rows="$selectedSale->items" no-hover>
                    @scope('cell_product.name', $item)
                        <div class="flex flex-col">
                            <span class="font-medium text-sm line-clamp-1">{{ $item->product->name }}</span>
                            <span class="text-[10px] text-gray-400 font-mono">成本: NT$ {{ number_format($item->cost_snapshot, 0) }}</span>
                        </div>
                    @endscope
                    @scope('cell_quantity', $item)
                        <span class="font-bold">x{{ (int)$item->quantity }}</span>
                    @endscope
                    @scope('cell_subtotal', $item)
                        <span class="text-blue-700 font-bold italic">NT$ {{ number_format($item->subtotal, 0) }}</span>
                    @endscope
                </x-table>
            </div>

            {{-- 備註區域 --}}
            @if($selectedSale->notes)
                <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-100">
                    <p class="text-xs text-yellow-600 font-bold mb-1">單據備註</p>
                    <p class="text-sm text-gray-700 leading-relaxed">{{ $selectedSale->notes }}</p>
                </div>
            @endif
        </div>

        {{-- 底部固定動作欄 --}}
        <x-slot:actions>
            <div class="flex gap-2 w-full">
                <x-button label="刪除單據" icon="o-trash" wire:click="delete({{ $selectedSale->id }})" 
                    wire:confirm="警告：刪除銷售單將自動回補庫存。確定執行？" class="btn-error btn-outline flex-1" />
                <x-button label="修改內容" icon="o-pencil" :link="route('sales.edit', $selectedSale->id)" class="btn-primary flex-1" />
            </div>
        </x-slot:actions>
    @endif
</x-drawer>
</div>