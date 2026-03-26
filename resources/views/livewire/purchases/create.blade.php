{{-- 檔案路徑：resources/views/livewire/purchases/create.blade.php --}}
<div>
    <x-header title="新增採購單" separator progress-indicator>
        <x-slot:actions>
            <x-button label="返回列表" icon="o-arrow-left" link="/purchases" />
            <x-button label="儲存入庫" icon="o-check" class="btn-primary" wire:click="save" spinner />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {{-- 左側：主表資訊 --}}
        <div class="lg:col-span-1">
            <x-card title="基本資訊" shadow separator>
                <x-select label="供應商" icon="o-user" :options="$suppliers" wire:model="supplier_id" placeholder="請選擇" />
                <x-datetime label="採購日期" wire:model="purchased_at" icon="o-calendar" class="mt-4" />
                <x-input label="匯率 (TWD)" wire:model.live="exchange_rate" icon="o-currency-dollar" class="mt-4" />
                <x-input label="備註" wire:model.live="remark" icon="o-pencil-square" class="mt-4" />
            </x-card>
        </div>

        {{-- 右側：採購明細 --}}
        <div class="lg:col-span-3">
            <x-card title="商品明細" shadow separator>
                {{-- 🔧 移除原本的條碼掃描按鈕（已整合到每行的輸入框） --}}

                <div class="hidden lg:grid grid-cols-12 gap-4 mb-2 px-4 text-sm font-bold opacity-60">
                    <div class="col-span-6">商品選擇與確認</div>
                    <div class="col-span-2">數量</div>
                    <div class="col-span-2 text-right">外幣單價</div>
                    <div class="col-span-2 text-right">TWD 預估</div>
                </div>

                <div class="space-y-3">
                    @foreach($items as $index => $item)
                        <div wire:key="purchase-row-{{ $index }}" class="p-4 border rounded-xl bg-base-50 relative">
                    
                            {{-- 刪除按鈕 --}}
                            <x-button icon="o-trash" class="btn-error btn-xs absolute -top-2 -right-2 rounded-full" 
                                wire:click="removeRow({{ $index }})" />

                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-center">
                                
                                {{-- 1. 商品搜尋 (佔 6/12) --}}
                                <div class="lg:col-span-6">
                                    <x-choices 
                                        wire:model.live="items.{{ $index }}.product_id" 
                                        :options="$productOptions"
                                        search-function="search"
                                        option-label="name"
                                        option-sub-label="sku"
                                        searchable
                                        single
                                        debounce="300ms"
                                        placeholder="輸入 SKU 或 商品名稱 搜尋..."
                                    >
                                        {{-- 🔧 新增：在輸入框後方加入掃描圖示 --}}
                                        <x-slot:append>
                                            <x-button 
                                                icon="o-qr-code" 
                                                class="btn-ghost btn-sm" 
                                                wire:click="scanForRow({{ $index }})"
                                                title="掃描條碼"
                                            />
                                        </x-slot:append>
                                    </x-choices>
                                    
                                    {{-- 顯示已選商品的 SKU --}}
                                    @if($items[$index]['product_id'])
                                        @php
                                            $selectedProduct = collect($productOptions)->firstWhere('id', $items[$index]['product_id']);
                                        @endphp
                                        @if($selectedProduct)
                                            <div class="text-xs text-gray-500 mt-1">
                                                SKU: {{ $selectedProduct['sku'] ?? 'N/A' }}
                                            </div>
                                        @endif
                                    @endif
                                </div>

                                {{-- 2. 數量 (佔 2/12) --}}
                                <div class="lg:col-span-2">
                                    <x-input type="number" wire:model.live="items.{{ $index }}.quantity" class="text-center" />
                                </div>

                                {{-- 3. 外幣單價 (佔 2/12) --}}
                                <div class="lg:col-span-2">
                                    <x-input wire:model.live="items.{{ $index }}.foreign_price" class="text-right" />
                                </div>

                                {{-- 4. TWD 預估 (佔 2/12) --}}
                                <div class="lg:col-span-2 text-right">
                                    <span class="font-mono font-bold text-blue-600">
                                        {{ number_format(bcmul($items[$index]['foreign_price'] ?? 0, $exchange_rate ?? 0, 4), 2) }}
                                    </span>
                                </div>

                            </div>
                        </div>
                    @endforeach
                </div>

                <x-slot:actions>
                    <x-button label="追加商品列" icon="o-plus" class="btn-outline btn-sm w-full" wire:click="addRow" />
                </x-slot:actions>
            </x-card>
        </div>
    </div>

    {{-- 🔧 修改：掃描 Modal 支援手機掃描 --}}
    <x-modal wire:model="showScanner" title="掃描條碼" separator>
        <div class="space-y-4">
            <div class="text-sm text-gray-600">
                請使用掃描槍掃描條碼，或手動輸入 SKU 後按確認
            </div>
            
            <x-input 
                id="scanner-input"
                placeholder="請掃描或輸入條碼..." 
                wire:model.live="scannedBarcode"
                wire:keydown.enter="handleScannedBarcode(scannedBarcode)"
                class="font-mono text-lg"
                autofocus
            />
            
            {{-- 手動確認按鈕（手機使用者用） --}}
            <x-button 
                label="確認" 
                icon="o-check" 
                class="btn-primary w-full" 
                wire:click="handleScannedBarcode(scannedBarcode)"
                :disabled="empty($scannedBarcode)"
            />
        </div>
        
        <x-slot:actions>
            <x-button label="關閉" @click="$wire.showScanner = false; $wire.scannedBarcode = ''" />
        </x-slot:actions>
    </x-modal>

    {{-- 🔧 新增：自動聚焦掃描輸入框 --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('focus-scanner-input', () => {
                setTimeout(() => {
                    const input = document.getElementById('scanner-input');
                    if (input) {
                        input.focus();
                        input.select();
                    }
                }, 100);
            });
        });
    </script>
</div>