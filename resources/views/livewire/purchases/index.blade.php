{{-- 檔案路徑：resources/views/livewire/purchases/index.blade.php --}}
<div>
    <x-header title="採購清單" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋單號或供應商..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
            <x-button label="新增採購" icon="o-plus" :link="route('purchases.create')" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- PC 端表格 --}}
    <div class="hidden lg:block">
        <x-card shadow>
            <x-table :headers="$headers" :rows="$purchases" @row-click="$wire.showDetail($event.detail.id)" class="cursor-pointer" with-pagination>
                @scope('cell_purchase_number', $purchase)
                    <x-badge :value="$purchase->purchase_number" class="badge-neutral font-mono" />
                @endscope

                @scope('cell_supplier_name', $purchase)
                    {{ $purchase->supplier?->name ?? 'N/A' }}
                @endscope

                @scope('cell_total_twd', $purchase)
                    <span class="font-bold text-blue-700">NT$ {{$purchase->total_twd }}</span>
                @endscope
            </x-table>
        </x-card>
    </div>

    {{-- 手機端卡片 --}}
    <div class="block lg:hidden space-y-3">
        @foreach($purchases as $purchase)
            <div class="border rounded-xl p-4 bg-base-100 active:bg-base-200 transition-colors shadow-sm" @click="$wire.showDetail({{ $purchase->id }})">
                <div class="flex justify-between items-start mb-2">
                    <x-badge :value="$purchase->purchase_number" class="badge-neutral badge-sm font-mono" />
                    <span class="text-[10px] text-gray-500">{{ $purchase->purchased_at->format('Y-m-d') }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <div>
                        <p class="font-bold text-base">{{ $purchase->supplier?->name ?? '未知供應商' }}</p>
                        <p class="text-xs text-gray-400">{{ $purchase->currency }} @ {{ $purchase->exchange_rate }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-blue-700 font-black text-lg">NT$ {{ number_format($purchase->total_twd, 0) }}</p>
                    </div>
                </div>
            </div>
        @endforeach
        <div class="mt-4">
            {{ $purchases->links(data: ['scrollTo' => false]) }}
        </div>
    </div>

    {{-- 採購詳情 Drawer --}}
    <x-drawer wire:model="drawer" title="採購單據詳情" right separator with-close-button class="w-11/12 lg:w-1/3">  
        @if($selectedPurchase)
			
			<p>採購單號：{{ $selectedPurchase->purchase_number }}</p>
				
			<div class="space-y-6 pb-20">				
				<div class="bg-base-100 border rounded-xl p-4 shadow-sm">
                    <div class="grid grid-cols-2 gap-y-4 text-sm">
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold">採購日期</p>
                            <p class="font-medium">{{ $selectedPurchase->purchased_at->format('Y-m-d') }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold">供應商</p>
                            <p class="font-medium text-blue-700">{{ $selectedPurchase->supplier?->name }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold">幣別/匯率</p>
                            <p class="text-sm font-mono">{{ $selectedPurchase->currency }} / {{ $selectedPurchase->exchange_rate }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold">經辦人</p>
                            <p class="text-sm">{{ $selectedPurchase->user?->name }}</p>
                        </div>
                    </div>
                </div>

                {{-- 總額統計 --}}
                <div class="p-4 border rounded-xl bg-blue-50/50">
                    <p class="text-[10px] text-blue-600 mb-1 font-bold">採購總金額 (TWD)</p>
                    <p class="text-2xl font-black text-blue-800 font-mono">NT$ {{ number_format($selectedPurchase->total_twd, 0) }}</p>
                </div>

                {{-- 商品明細 --}}
                <div>
                    <p class="text-sm font-bold border-l-4 border-primary pl-2 mb-3">入庫明細</p>
                    <div class="space-y-2">
                        @foreach($selectedPurchase->items as $item)
                            <div class="p-3 border rounded-lg bg-base-50 text-sm">
                                <div class="flex justify-between">
                                    <span class="font-bold">{{ $item->product->name }}</span>
                                    <span class="font-mono">x{{ (int)$item->quantity }}</span>
                                </div>
                                <div class="flex justify-between mt-1 text-[11px] text-gray-500">
                                    <span>外幣: {{ number_format($item->foreign_price, 2) }}</span>
                                    <span class="text-blue-600 font-bold">NT$ {{ number_format($item->subtotal_twd, 0) }}</span>
                                </div>
								<div class="flex justify-between mt-2 text-[10px] text-gray-400 border-t pt-1">
									<span>倉庫：{{ $item->warehouse?->name ?? '未指定' }}</span>
									<span>入庫單價：{{ number_format($item->unit_price, 2) }}</span>
								</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <x-slot:actions>
                <div class="flex gap-3 w-full border-t pt-4 bg-base-100">
                    <x-button label="刪除" icon="o-trash" wire:click="confirmDelete({{ $selectedPurchase->id }})" class="btn-error btn-outline flex-1" />
                    <x-button label="修改" icon="o-pencil" :link="route('purchases.edit', $selectedPurchase->id)" class="btn-primary flex-1 text-white" />
                    <x-button label="退貨" icon="o-arrow-path" :link="route('purchases.returns.create', ['purchase' => $selectedPurchase->id])" class="btn-outline flex-1" />
                </div>
            </x-slot:actions>
        @endif
    </x-drawer>	

    {{-- 刪除確認 Modal --}}
    <x-modal wire:model="deleteModal" title="確認刪除採購單？" separator>
        <div class="py-2">
            <p class="text-sm text-gray-600 mb-4">
                單號：<span class="font-mono font-bold">{{ $selectedPurchase?->purchase_number }}</span>
            </p>
            <x-checkbox 
                label="同步扣除關聯庫存" 
                wire:model="shouldSyncInventory" 
                hint="若勾選，系統將自動刪除此單產生的入庫紀錄（警告：若商品已賣出可能導致庫存數據異常）"
                class="checkbox-warning"
            />
        </div>
        <x-slot:actions>
        {{-- Modal 只放刪除相關操作 --}}
			<x-button label="取消" wire:click="$set('deleteModal', false)" class="btn-ghost" />
			<x-button 
				label="確認刪除" 
				wire:click="delete" 
				class="btn-error" 
				spinner="delete"
			/>
		</x-slot:actions>
    </x-modal>
</div>