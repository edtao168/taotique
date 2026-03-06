<div>
    <x-header title="庫存盤點" subtitle="校正系統數量與實物庫存" separator>
        <x-slot:actions>
            <x-button label="返回庫存總覽" icon="o-arrow-left" link="/inventories" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <x-card title="盤點錄入" shadow class="lg:col-span-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- 選擇倉庫 --}}
                <x-select 
                    label="盤點倉庫" 
                    icon="o-building-office" 
                    :options="$warehouses" 
                    wire:model.live="warehouse_id" 
                    placeholder="選擇要盤點的庫別"
                />

                {{-- 選擇商品 --}}
                <x-choices 
                    label="盤點商品" 
                    icon="o-magnifying-glass" 
                    wire:model.live="product_id" 
                    :options="$products" 
                    option-label="display_name"
                    single 
                    searchable 
                />

                {{-- 數量對比區 --}}
                <div class="bg-base-200 p-4 rounded-lg flex flex-col justify-center border border-dashed border-base-300">
                    <span class="text-sm opacity-60">系統當前庫存</span>
                    <span class="text-3xl font-mono font-bold">{{ number_format($current_quantity) }}</span>
                </div>

                <x-input 
                    label="實際清點數量" 
                    type="number" 
                    wire:model="actual_quantity" 
                    icon="o-check-circle" 
                    class="text-xl font-bold text-primary"
                    hint="輸入你在現場數到的真實數字"
                />
            </div>

            <x-textarea label="盤點備註" wire:model="remark" placeholder="例如：報廢、失竊、紀錄錯誤..." class="mt-4" rows="2" />

            <x-slot:actions>
                <x-button label="確認盤點更新" icon="o-arrow-path" wire:click="submit" class="btn-primary" spinner="submit" />
            </x-slot:actions>
        </x-card>

        <div class="space-y-4">
            <x-card title="盤點小幫手" icon="o-light-bulb" class="bg-yellow-50">
                <p class="text-sm text-gray-600">
                    當您發現現場貨品與電腦顯示不符時，請使用此功能。這會直接**強制覆蓋**現有的系統庫存數。
                </p>
            </x-card>
        </div>
    </div>
</div>