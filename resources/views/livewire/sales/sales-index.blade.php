<div>{{-- C:\laragon\www\taotique\resources\views\livewire\sales\sales-index.blade.php --}}
    <x-header title="銷售記錄清單" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋單號或客戶..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="新增銷貨單" icon="o-plus" :link="route('sales.create')" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table 
            :headers="$headers" 
            :rows="$sales" 
            @row-click="$wire.showDetail($event.detail.id)" 
            class="cursor-pointer" 
            with-pagination
        >
            {{-- 單號樣式 --}}
            @scope('cell_invoice_number', $sale)
                <x-badge :value="$sale->invoice_number" class="badge-neutral font-mono" />
            @endscope

            {{-- 金額樣式 --}}
            @scope('cell_customer_total', $sale)
                <span class="font-bold text-blue-700">NT$ {{ number_format($sale->customer_total, 2) }}</span>
            @endscope

            {{-- 日期樣式 --}}
            @scope('cell_sold_at', $sale)
                {{ $sale->sold_at->format('Y-m-d') }}
            @endscope

            {{-- 操作按鈕 --}}
            @scope('actions', $sale)
                <div class="flex gap-2">
                    <x-button icon="o-pencil" :link="route('sales.edit', $sale->id)" class="btn-ghost btn-sm text-blue-500" @click.stop />
                    <x-button icon="o-trash" wire:click="delete({{ $sale->id }})" wire:confirm="確定刪除並還原庫存？" class="btn-ghost btn-sm text-error" @click.stop />
                </div>
            @endscope
        </x-table>
    </x-card>

    {{-- 快速查詢抽屜 (參考 Product Index 設計) --}}
    <x-drawer wire:model="drawer" title="訂單詳細資料" right separator with-close-button class="w-1/3">
        @if($selectedSale)
            <div class="space-y-6">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-widest">Invoice Number</p>
                        <p class="font-mono text-xl font-bold">{{ $selectedSale->invoice_number }}</p>
                    </div>
                    <x-badge :value="$selectedSale->channel" class="badge-info" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <x-input label="客戶名稱" value="{{ $selectedSale->customer->name }}" readonly icon="o-user" />
                    <x-input label="銷售日期" value="{{ $selectedSale->sold_at->format('Y-m-d') }}" readonly icon="o-calendar" />
                </div>

                {{-- 商品明細表格 --}}
                <div class="border rounded-lg overflow-hidden">
                    <table class="table table-xs w-full bg-base-200">
                        <thead>
                            <tr>
                                <th>商品</th>
                                <th class="text-right">數量</th>
                                <th class="text-right">小計</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($selectedSale->items as $item)
                                <tr>
                                    <td>{{ $item->product->name }}</td>
                                    <td class="text-right">{{ (int)$item->quantity }}</td>
                                    <td class="text-right">{{ number_format($item->subtotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="bg-base-200 p-4 rounded-lg text-xs space-y-1">
                    <p>經手人員：{{ $selectedSale->user->name ?? 'N/A' }}</p>
                    <p>最後更新：{{ $selectedSale->updated_at->format('Y-m-d H:i') }}</p>
                </div>
            </div>
        @endif

        <x-slot:actions>
            @if($selectedSale)
                <x-button label="修改訂單" icon="o-pencil-square" :link="route('sales.edit', $selectedSale->id)" class="btn-primary" />
            @endif
            <x-button label="關閉" @click="$wire.drawer = false" />
        </x-slot:actions>
    </x-drawer>
</div>