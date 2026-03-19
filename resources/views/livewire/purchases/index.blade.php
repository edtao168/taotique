{{-- 檔案路徑：resources/views/livewire/purchases/index.blade.php --}}
<div>
    <x-header title="採購清單" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋單號或供應商..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
            <x-button label="新增採購" icon="o-plus" link="/purchases/create" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- --- PC 端表格模式 (LG 以上顯示) --- --}}
    <div class="hidden lg:block">
        <x-card shadow>
            <x-table :headers="$headers" :rows="$purchases" with-pagination>
                @scope('cell_purchase_number', $purchase)
                    <span class="font-mono font-bold">{{ $purchase->purchase_number }}</span>
                @endscope

                @scope('cell_supplier_name', $purchase)
                    {{ $purchase->supplier?->name ?? 'N/A' }}
                @endscope

                @scope('cell_total_foreign', $purchase)
                    <div class="text-right">
                        {{ $purchase->currency }} {{ number_format($purchase->total_foreign, 2) }}
                        <div class="text-[10px] text-gray-400">匯率: {{ $purchase->exchange_rate }}</div>
                    </div>
                @endscope

                @scope('cell_total_twd', $purchase)
                    <div class="text-right font-bold text-blue-700">
                        NT$ {{ number_format($purchase->total_twd, 0) }}
                    </div>
                @endscope

                @scope('actions', $purchase)
                    <div class="flex gap-2 justify-end">
                        <x-button icon="o-eye" link="{{ route('purchases.show', $purchase->id) }}" class="btn-ghost btn-sm text-blue-500" />
                        <x-button icon="o-trash" wire:click="confirmDelete({{ $purchase->id }})" class="btn-ghost btn-sm text-red-500" />
                    </div>
                @endscope
            </x-table>
        </x-card>
    </div>

    {{-- --- 手機端卡片模式 (LG 以下顯示) --- --}}
    <div class="block lg:hidden space-y-3">
        @foreach($purchases as $purchase)
            <x-card class="shadow-sm border border-base-200">
                <div class="flex flex-col gap-2">
                    {{-- 頂部：單號與日期 --}}
                    <div class="flex justify-between items-start border-b border-base-200 pb-2">
                        <div>
                            <p class="text-[10px] text-gray-500 uppercase tracking-tighter">Purchase No.</p>
                            <p class="font-mono font-bold text-sm">{{ $purchase->purchase_number }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] text-gray-500">採購日期</p>
                            <p class="text-xs">{{ $purchase->purchased_at->format('Y-m-d') }}</p>
                        </div>
                    </div>

                    {{-- 中部：供應商與金額 --}}
                    <div class="flex justify-between items-center py-1">
                        <div class="flex items-center gap-2">
                            <x-icon name="o-user" class="w-4 h-4 text-gray-400" />
                            <span class="font-medium">{{ $purchase->supplier?->name ?? '未知供應商' }}</span>
                        </div>
                        <div class="text-right">
                            <span class="text-blue-700 font-extrabold text-lg">
                                NT$ {{ number_format($purchase->total_twd, 0) }}
                            </span>
                        </div>
                    </div>

                    {{-- 底部：外幣詳細與操作 --}}
                    <div class="flex justify-between items-end bg-base-50 p-2 rounded-lg">
                        <div class="text-[10px] text-gray-500">
                            外幣總計: {{ $purchase->currency }} {{ number_format($purchase->total_foreign, 2) }} <br>
                            匯率快照: {{ $purchase->exchange_rate }}
                        </div>
                        <div class="flex gap-1">
                            <x-button icon="o-eye" label="查看" link="{{ route('purchases.show', $purchase->id) }}" class="btn-ghost btn-xs text-blue-500" />
                            <x-button icon="o-trash" label="刪除" wire:click="confirmDelete({{ $purchase->id }})" class="btn-ghost btn-xs text-red-500" />
                        </div>
                    </div>
                </div>
            </x-card>
        @endforeach

        {{-- 手機端分頁 --}}
        <div class="py-4">
            {{ $purchases->links(data: ['scrollTo' => false]) }}
        </div>
    </div>

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
            <x-button label="取消" @click="$wire.deleteModal = false" />
            <x-button label="確認刪除" icon="o-trash" class="btn-error" wire:click="delete" spinner />
        </x-slot:actions>
    </x-modal>
</div>