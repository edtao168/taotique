<div>
    <x-header title="銷貨單詳情" subtitle="{{ $sale->invoice_number }}" separator>
        <x-slot:actions>
            <x-button label="返回列表" icon="o-arrow-left" link="{{ route('sales.overview') }}" />
            <x-button label="修改訂單" icon="o-pencil" link="{{ route('sales.edit', $sale) }}" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-card title="基本資訊" shadow>
            <p><strong>客戶：</strong> {{ $sale->customer->name }}</p>
            <p><strong>日期：</strong> {{ $sale->sold_at->format('Y-m-d') }}</p>
            <p><strong>通路：</strong> {{ $sale->channel }}</p>
            <p><strong>支付：</strong> {{ $sale->payment_method }}</p>
        </x-card>

        <x-card title="帳務摘要" shadow class="md:col-span-2">
            <div class="flex justify-around text-center">
                <div>
                    <div class="text-gray-400 text-sm">買家支付總額</div>
                    <div class="text-xl font-bold text-blue-600">NT$ {{ number_format($sale->customer_total, 2) }}</div>
                </div>
                <div>
                    <div class="text-gray-400 text-sm">預計淨利</div>
                    <div class="text-xl font-bold text-emerald-600">NT$ {{ number_format($sale->final_net_amount, 2) }}</div>
                </div>
            </div>
        </x-card>
    </div>

    <x-card title="商品明細" separator class="mt-6 shadow">
        <x-table :headers="[
            ['key' => 'product.sku', 'label' => 'SKU'],
            ['key' => 'product.name', 'label' => '品名'],
            ['key' => 'price', 'label' => '單價'],
            ['key' => 'quantity', 'label' => '數量'],
            ['key' => 'subtotal', 'label' => '小計']
        ]" :rows="$sale->items" />
    </x-card>
</div>