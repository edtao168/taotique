<div>
    <x-header title="採購進貨管理" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋單號或供應商..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="新增採購單" :link="route('purchases.create')" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$purchases" :sort-by="$sortBy" with-pagination>
            
            {{-- 自定義供應商顯示 --}}
            @scope('cell_supplier_name', $purchase)
                {{ $purchase->supplier->name ?? '未指定' }}
            @endscope

            {{-- 自定義日期顯示 --}}
            @scope('cell_purchased_at', $purchase)
                <div class="badge badge-ghost badge-sm">
                    {{ $purchase->purchased_at->format('Y-m-d') }}
                </div>
            @endscope

            {{-- 自定義外幣顯示 --}}
            @scope('cell_total_foreign', $purchase)
                <span class="font-mono">
                    {{ number_format($purchase->total_foreign, 2) }} 
                    <small class="text-gray-400">{{ $purchase->currency }}</small>
                </span>
            @endscope

            {{-- 自定義本幣顯示 --}}
            @scope('cell_total_twd', $purchase)
                <span class="font-mono font-bold text-orange-600">
                    {{ number_format($purchase->total_twd, 0) }}
                </span>
            @endscope

            {{-- 操作按鈕 --}}
            @scope('actions', $purchase)
                <div class="flex space-x-2">
                    {{-- 這裡預留詳情頁面按鈕 --}}
                    <x-button icon="o-eye" class="btn-ghost btn-sm" />
                    {{-- 表格中的刪除按鈕 --}}
					<x-button icon="o-trash" wire:click="confirmDelete({{ $purchase->id }})" class="btn-ghost btn-sm text-error" />

					{{-- 彈出確認視窗 --}}
					<x-modal wire:model="deleteModal" title="確認刪除採購單？" separator>
						<div class="space-y-4">
							<p>確定要刪除單號 <span class="font-bold">{{ $selectedPurchase?->invoice_number }}</span> 嗎？此動作無法復原。</p>
							
							<x-card shadow class="bg-base-200">
								<x-checkbox 
									label="同步扣除已入庫的庫存" 
									description="勾選後，系統將自動刪除此單據產生的所有入庫紀錄。"
									wire:model="shouldSyncInventory" 
									tight 
								/>
							</x-card>
						</div>

						<x-slot:actions>
							<x-button label="取消" @click="$wire.deleteModal = false" />
							<x-button label="確定刪除" wire:click="delete" class="btn-error" icon="o-check" spinner="delete" />
						</x-slot:actions>
					</x-modal>
                </div>
            @endscope
        </x-table>
    </x-card>
</div>