<div>
    <x-header title="庫存總覽" subtitle="監控實體店與網店即時庫存" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋 SKU 或名稱..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
            <x-button label="倉庫調撥" icon="o-arrows-right-left" link="/inventories/transfers" />
            <x-button label="盤點作業" icon="o-check-badge" link="/inventories/stocktakes" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <x-select label="篩選營業點" icon="o-map-pin" :options="$shops" wire:model.live="selectedShop" placeholder="全部地點" />
        <x-select label="篩選特定庫別" icon="o-building-office" :options="$warehouses" wire:model.live="selectedWarehouse" placeholder="全部庫別" />
    </div>

    <x-card shadow class="hidden md:block">
        <x-table :headers="$headers" :rows="$products" with-pagination>
            @scope('cell_sku', $product)
                <x-badge :value="$product->sku" class="badge-neutral font-mono" />
            @endscope

            @scope('cell_inventory_details', $product)
                <div class="flex flex-wrap gap-2">
                    @forelse($product->inventories as $inv)
                        <div class="bg-base-200 px-2 py-1 rounded text-xs border border-base-300">
                            <span class="opacity-70">{{ $inv->warehouse->shop->name }}</span>
                            <span class="font-bold mx-1">/</span>
                            <span>{{ $inv->warehouse->name }}:</span>
                            <span class="ml-1 font-bold text-primary">{{ number_format($inv->quantity) }}</span>
                        </div>
                    @empty
                        <span class="text-gray-400 text-xs italic">無庫存資料</span>
                    @endforelse
                </div>
            @endscope

            @scope('cell_total_stock', $product)
                <div class="text-right">
                    <span class="text-lg font-bold {{ $product->total_stock <= $product->min_stock ? 'text-error' : '' }}">
                        {{ number_format($product->total_stock) }}
                    </span>
                    <span class="text-xs opacity-50 block">水位: {{ $product->min_stock }}</span>
                </div>
            @endscope
        </x-table>
    </x-card>

    <div class="md:hidden space-y-3">
        @foreach($products as $product)
            <x-card class="border-l-4 {{ $product->total_stock <= $product->min_stock ? 'border-l-error' : 'border-l-primary' }}">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <x-badge :value="$product->sku" class="badge-neutral font-mono mb-1" />
                        <div class="font-bold text-lg">{{ $product->name }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xl font-black {{ $product->total_stock <= $product->min_stock ? 'text-error' : 'text-primary' }}">
                            {{ number_format($product->total_stock) }}
                        </div>
                        <div class="text-xs opacity-50">安全水位: {{ $product->min_stock }}</div>
                    </div>
                </div>

                <div class="divider my-1"></div>

                <div class="space-y-2">
                    @forelse($product->inventories as $inv)
                        <div class="flex justify-between text-sm italic">
                            <span>{{ $inv->warehouse->shop->name }} - {{ $inv->warehouse->name }}</span>
                            <span class="font-bold">{{ number_format($inv->quantity) }}</span>
                        </div>
                    @empty
                        <div class="text-center text-gray-400 text-xs">無庫存細節</div>
                    @endforelse
                </div>
            </x-card>
        @endforeach

        <div class="mt-4">
            {{ $products->links() }}
        </div>
    </div>
</div>