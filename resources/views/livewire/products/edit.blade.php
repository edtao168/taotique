<div class="p-6">
    <x-header title="修改商品資料" subtitle="正在編輯：{{ $product->sku }}" separator>
        <x-slot:actions>
            <x-button label="返回列表" icon="o-arrow-left" link="{{ route('products.index') }}" />
        </x-slot:actions>
    </x-header>

    {{-- 使用 grid 將頁面分為兩大部分 --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {{-- 左側：基本資料表單 --}}
        <div class="lg:col-span-1">
            <x-card title="基本資訊" shadow border>
                <x-form wire:submit="save">
                    <x-input label="商品 SKU" value="{{ $product->sku }}" icon="o-finger-print" readonly class="bg-base-200 font-mono" />
                    <x-input label="商品名稱" wire:model="name" icon="o-pencil" />

                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <x-input label="零售價" wire:model="price" prefix="$" type="number" />
                        <x-input label="單位" wire:model="unit" />
                    </div>

                    <div class="grid grid-cols-2 gap-4 items-center mt-4">
                        <x-input label="警戒庫存" wire:model="min_stock" type="number" />
                        <div class="pt-6">
                            <x-checkbox label="啟用銷售" wire:model="is_active" tight />
                        </div>
                    </div>

                    <x-textarea label="備註說明" wire:model="remark" rows="3" class="mt-4" />

                    <x-slot:actions>
                        <x-button label="取消" link="{{ route('products.index') }}" />
                        <x-button label="儲存變更" class="btn-primary" type="submit" spinner="save" icon="o-check" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </div>

        {{-- 右側：媒體管理 (相簿與影片) --}}
        <x-media-manager :product="$product" :new_photos="$new_photos" />
    </div>
</div>