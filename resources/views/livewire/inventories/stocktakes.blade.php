{{-- resources/views/livewire/inventories/stocktakes.blade.php --}}
<div>
    <x-header title="庫存盤點系統" separator>
		<x-slot:actions>
			<x-button label="返回庫存總覽" icon="o-arrow-left" link="/inventories" class="btn-ghost" />
		</x-slot:actions>
	</x-header>

    @if(!$stocktake_id)
        {{-- 第一階段：初始化盤點 --}}
        <x-card title="啟動新盤點" shadow class="max-w-2xl mx-auto">
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
            <div class="lg:col-span-1 space-y-4">
                <x-card title="清點錄入" shadow>
                    {{-- 這裡放掃描或搜尋產品的 Input --}}
                    <x-choices label="搜尋商品" wire:model.live="items.product_id" :options="$productOptions" search-function="search" single searchable />
                    <x-input label="實點數量" type="number" wire:model="actual_quantity" class="font-mono text-right" />
                    <x-slot:actions>
                        <x-button label="更新進度" wire:click="updateItem" class="btn-primary" />
                    </x-slot:actions>
                </x-card>

                <x-card title="盤點狀態" class="bg-base-200">
                    <div class="text-sm">盤點單編號: #{{ $stocktake_id }}</div>
                    <div class="text-sm font-bold">目標倉庫: {{ $this->currentStocktake?->warehouse?->name ?? '未指定' }}</div>
                    <x-button label="放棄盤點" class="btn-ghost btn-sm text-error mt-4" wire:click="cancelStocktake" />
                </x-card>
            </div>

			<div class="lg:col-span-2">
				<x-card title="盤點明細 (漏盤追蹤)" shadow>
					
					{{-- PC 端顯示：表格 (x-table) --}}
					<div class="hidden lg:block">
						<x-table :headers="$headers" :rows="$items">
							@scope('cell_system_quantity', $item)
								{{ number_format($item->system_quantity, 2) }}
							@endscope

							@scope('cell_actual_quantity', $item)
								@if(is_null($item->actual_quantity))
									<x-badge value="未清點" class="badge-error text-white" />
								@else
									<span class="font-bold text-success">{{ number_format($item->actual_quantity, 2) }}</span>
								@endif
							@endscope
						</x-table>
					</div>

					{{-- 手機端顯示：卡片清單 (div + foreach) --}}
					<div class="lg:hidden space-y-3">
						@forelse($items as $item)
							<div class="p-4 border rounded-lg bg-base-100 shadow-sm border-base-300">
								<div class="flex justify-between items-start mb-2">
									<div>
										<div class="font-bold text-lg">{{ $item->product->name }}</div>
										<div class="text-xs text-gray-500">SKU: {{ $item->product->sku }}</div>
									</div>
									@if(is_null($item->actual_quantity))
										<x-badge value="待點" class="badge-error" />
									@else
										<x-badge value="已點" class="badge-success" />
									@endif
								</div>
								
								<div class="grid grid-cols-2 gap-2 text-sm pt-2 border-t border-dashed">
									<div>
										<span class="text-gray-500">帳面：</span>
										<span class="font-mono">{{ number_format($item->system_quantity, 2) }}</span>
									</div>
									<div class="text-right">
										<span class="text-gray-500">實點：</span>
										<span class="font-bold {{ is_null($item->actual_quantity) ? 'text-error' : 'text-success' }}">
											{{ is_null($item->actual_quantity) ? '--' : number_format($item->actual_quantity, 2) }}
										</span>
									</div>
								</div>
								
								{{-- 手機端快速選取按鈕（選取該品項以便在上方錄入） --}}
								<div class="mt-3">
									<x-button 
										label="選取此項" 
										icon="o-cursor-arrow-rays" 
										class="btn-xs btn-outline btn-block" 
										wire:click="$set('product_id', {{ $item->product_id }})" 
									/>
								</div>
							</div>
						@empty
							<div class="text-center py-10 text-gray-400">
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
        <div class="text-lg">
            注意！目前尚有 <span class="text-error font-black text-2xl">{{ $missing_count }}</span> 件商品尚未清點。
        </div>
        <p class="py-4 text-gray-500">
            按下確認後，這些**漏盤品項**在系統中的庫存將被強制**歸零**並紀錄為盤損。此動作無法復原，確定要過帳嗎？
        </p>

        <x-slot:actions>
            <x-button label="取消" @click="$wire.confirmModal = false" />
            <x-button label="確定過帳（漏盤歸零）" wire:click="finalize" class="btn-primary" spinner="finalize" />
        </x-slot:actions>
    </x-modal>
</div>