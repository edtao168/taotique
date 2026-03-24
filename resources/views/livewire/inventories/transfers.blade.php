<div>
    <x-header title="倉庫間調撥" subtitle="將商品在不同營業點或庫別間移動" separator>
        <x-slot:actions>
            <x-button label="返回庫存總覽" icon="o-arrow-left" link="/inventories" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- 左側：填寫表單 --}}
        <x-card title="調撥資訊" shadow class="lg:col-span-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-select 
                    label="1. 來源倉庫 (從哪裡移出)" 
                    icon="o-arrow-up-tray" 
                    :options="$warehouses" 
                    wire:model.live="from_warehouse_id" 
                    placeholder="請選擇來源"
                />
                
                <x-select 
                    label="2. 目標倉庫 (移入哪裡)" 
                    icon="o-arrow-down-tray" 
                    :options="$warehouses" 
                    wire:model.live="to_warehouse_id" 
                    placeholder="請選擇目標"
                />

                <x-choices 
                    label="3. 選擇商品" 
                    icon="o-cube" 
                    wire:model="product_id" 
                    :options="$products" 
                    option-label="name"
                    option-sub-label="sku"
					placeholder="輸入商品名稱或 SKU 搜尋..."
                    single 
                    searchable
					search-function="searchProducts" 
					debounce="300ms"
                />

                <x-input 
                    label="4. 調撥數量" 
                    type="number" 
                    wire:model="quantity" 
                    icon="o-hashtag" 
                />
            </div>

            <x-textarea label="備註 (選填)" wire:model="remark" placeholder="原因說明..." class="mt-4" rows="2" />

            <x-slot:actions>
                <x-button label="取消" :link="route('inventories.index')" />
				<x-button label="確認執行調撥" icon="o-check" wire:click="transfer" class="btn-primary" spinner="transfer" />
            </x-slot:actions>
        </x-card>

        {{-- 右側：注意事項 --}}
        <div class="space-y-4">
            <x-card title="調撥說明" icon="o-information-circle" class="bg-blue-50">
                <ul class="text-sm space-y-2 list-disc ml-4 opacity-70">
                    <li>請確認來源倉庫有足夠的現貨。</li>
                    <li>調撥會即時更新雙方的庫存水位。</li>
                    <li>實體店與蝦皮網店間的庫存移動也應在此登記。</li>
                </ul>
            </x-card>
        </div>
    </div>
</div>