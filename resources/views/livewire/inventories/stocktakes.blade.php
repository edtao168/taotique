{{-- resources/views/livewire/inventories/stocktakes.blade.php --}}
<div>
    <x-header title="庫存盤點系統" separator>
        <x-slot:actions>
            <x-button label="返回庫存總覽" icon="o-arrow-left" link="/inventories" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    @if(!$stocktake_id)
        {{-- 第一階段：初始化盤點 --}}
        <x-card title="啟動新盤點" shadow class="max-w-2xl mx-auto bg-base-100 text-base-content">
            <div class="space-y-4">
                <x-select
                    label="請選擇盤點倉庫"
                    icon="o-building-office"
                    :options="$warehouses"
                    wire:model="warehouse_id"
                    placeholder="選擇庫別後將自動帶入所有現貨品項"
                />
                <x-alert icon="o-information-circle" class="alert-info">
                    系統將自動捕捉當下所有商品的庫存數作為「帳面快照」。
                </x-alert>
            </div>
            <x-slot:actions>
                <x-button label="生成盤點清單" icon="o-play" wire:click="createStocktake" class="btn-primary" spinner="createStocktake" />
            </x-slot:actions>
        </x-card>
    @else
        {{-- 第二階段：清點中 --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- 左側：清點錄入與狀態 --}}
            <div class="lg:col-span-1 space-y-4">
                <x-card title="清點錄入" shadow class="bg-base-100 text-base-content">
                    <x-product-picker
                        name="product_id"
                        :options="$productOptions"
                        :selected-name="$product_name"
                        search-property="productSearch"
                        select-method="fillProduct"
                        clear-method="resetProduct"
                    />
                    <x-input
                        label="實點數量"
                        type="number"
                        wire:model="actual_quantity"
                        class="font-bold text-lg text-base-content/80 text-right"
                    />
                    <x-slot:actions>
                        <x-button label="更新進度" wire:click="updateItem" class="btn-primary" spinner="updateItem" />
                    </x-slot:actions>
					<p class="text-xs text-base-content/80 mt-2">成本計算說明：系統之庫存調整分錄，一律採用商品主檔當前成本（products.cost）計算，非單據明細上的歷史成本。若商品成本為 0，庫存異動仍會記錄，但該筆調整將不會產生會計分錄。</p>
                </x-card>

                <x-card title="盤點狀態" class="bg-base-100 text-base-content border border-base-300">
                    <div class="text-sm">盤點單編號: #{{ $stocktake_id }}</div>
                    <div class="text-sm font-bold">目標倉庫: {{ $this->currentStocktake?->warehouse?->name ?? '未指定' }}</div>
                    <x-button label="放棄盤點" class="btn-ghost btn-sm text-error mt-4" wire:click="cancelStocktake" />
                </x-card>
            </div>

            {{-- 右側：盤點明細 --}}
            <div class="lg:col-span-2">
                <x-card title="盤點明細 (漏盤追蹤)" shadow class="bg-base-100 text-base-content">
                    {{-- 表頭：僅桌機顯示 --}}
                    <div class="hidden lg:grid lg:grid-cols-12 gap-4 px-4 py-3 border-b border-base-300 font-bold text-sm text-base-content/70">
                        <div class="col-span-4">品名 / SKU</div>
                        <div class="col-span-3 text-right">帳面數量</div>
                        <div class="col-span-3 text-right">實點數量</div>
                        <div class="col-span-2 text-center">操作</div>
                    </div>

                    {{-- 資料列：手機為卡片、桌機為橫列 --}}
                    <div class="divide-y divide-base-200">
                        @forelse($items as $item)
                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-2 lg:gap-4 p-4 lg:px-4 lg:py-3 items-center hover:bg-base-200/50 transition-colors text-base-content">

                                {{-- 品名 / SKU --}}
                                <div class="lg:col-span-4">
                                    <div class="font-bold text-base lg:text-sm">
                                        {{ $item->product?->name ?? '(商品已刪除)' }}
                                    </div>
                                    <div class="text-xs text-base-content/50 mt-0.5">
                                        SKU: {{ $item->product?->sku ?? '-' }}
                                    </div>
                                </div>

                                {{-- 帳面數量 --}}
                                <div class="flex lg:block justify-between lg:text-right lg:col-span-3 items-center">
                                    <span class="lg:hidden text-base-content/50 text-sm">帳面：</span>
                                    <span class="font-mono">{{ number_format($item->system_quantity, 2) }}</span>
                                </div>

                                {{-- 實點數量 --}}
                                <div class="flex lg:block justify-between lg:text-right lg:col-span-3 items-center">
                                    <span class="lg:hidden text-base-content/50 text-sm">實點：</span>
                                    @if(is_null($item->actual_quantity))
                                        <x-badge value="未清點" class="badge-error text-white" />
                                    @else
                                        <span class="font-bold text-success font-mono">
                                            {{ number_format($item->actual_quantity, 2) }}
                                        </span>
                                    @endif
                                </div>

                                {{-- 操作 --}}
                                <div class="lg:col-span-2 flex lg:justify-center mt-2 lg:mt-0">
                                    <x-button
                                        label="選取"
                                        icon="o-cursor-arrow-rays"
                                        class="btn-xs btn-outline btn-block lg:btn-block"
                                        wire:click="selectProduct({{ $item->product_id }})"
                                    />
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-10 text-base-content/40">
                                暫無盤點明細
                            </div>
                        @endforelse
                    </div>

                    <x-slot:actions>
                        <x-button label="完成盤點並過帳" icon="o-check" wire:click="showFinalizeConfirmation" class="btn-success" />
                    </x-slot:actions>
                </x-card>
            </div>
        </div>
    @endif

    {{-- 漏盤提醒彈窗 --}}
    <x-modal wire:model="confirmModal" title="盤點結案確認" separator>
        <div class="text-lg text-base-content">
            注意！目前尚有 <span class="text-error font-black text-2xl">{{ $missing_count }}</span> 件商品尚未清點。
        </div>
        <p class="py-4 text-base-content/60">
            按下確認後，這些<strong>漏盤品項</strong>在系統中的庫存將被強制<strong>歸零</strong>並紀錄為盤損。此動作無法復原，確定要過帳嗎？
        </p>

        <x-slot:actions>
            <x-button label="取消" @click="$wire.confirmModal = false" />
            <x-button label="確定過帳（漏盤歸零）" wire:click="finalize" class="btn-primary" spinner="finalize" />
        </x-slot:actions>
    </x-modal>
</div>