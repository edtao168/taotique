<div>
    <x-header title="供應商管理" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
			<x-button label="去採購系統" icon="o-archive-box" :link="route('purchases.index')" class="btn-outline" />
			<x-button label="新增供應商" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :headers="$headers" :rows="$suppliers" with-pagination @row-click="$wire.showDetails($event.detail.id)">
			@scope('actions', $supplier)
                <div class="flex gap-2">
                    {{-- 編輯按鈕，加上 @click.stop 防止冒泡觸發 row-click --}}
                    <x-button 
                        icon="o-pencil" 
                        wire:click="edit({{ $supplier->id }})" 
                        @click.stop="" 
                        class="btn-sm btn-ghost text-blue-500" 
                        tooltip="編輯"
                    />                    
                    
					<x-button 
						icon="o-plus-circle" 
						tooltip="新增採購單"
						:link="route('purchases.create', ['supplier_id' => $supplier->id])" 
						@click.stop=""
						class="btn-sm btn-ghost text-orange-600" 
					/>
				
                    <x-button 
                        icon="o-trash" 
                        wire:click="delete({{ $supplier->id }})" 
                        wire:confirm="確定要刪除此供應商？"
                        @click.stop="" 
                        class="btn-sm btn-ghost text-red-500"
                        tooltip="刪除"
                    />

                </div>
            @endscope
        </x-table>
    </x-card>

    <x-drawer wire:model="drawer" title="{{ $isReadOnly ? '供應商詳情' : ($editingSupplier ? '編輯供應商' : '新增供應商') }}" right separator with-close-button class="w-11/12 lg:w-1/3">
        <x-form wire:submit="save">
            <x-input label="名稱 *" wire:model="name" :readonly="$isReadOnly" />
            <x-input label="聯繫人" wire:model="contact_person" :readonly="$isReadOnly" />
            <x-input label="電話" wire:model="phone" :readonly="$isReadOnly" />
            
            <div class="divider text-xs">社交聯繫方式</div>
            <div class="grid grid-cols-2 gap-2">
                <x-input label="WeChat" wire:model="contact_json.wechat" icon="o-chat-bubble-left-right" :readonly="$isReadOnly" />
                <x-input label="Line" wire:model="contact_json.line" icon="o-chat-bubble-bottom-center-text" :readonly="$isReadOnly" />
            </div>
            
            <x-input label="其他聯繫方式" wire:model="contact_json.other" :readonly="$isReadOnly" />
            <x-textarea label="內部備註" wire:model="notes" rows="4" :readonly="$isReadOnly" />

			@if($isReadOnly)
				<div class="divider mt-8 text-sm text-gray-400">最近採購（入庫）記錄</div>
				
				@if(empty($purchaseRecords))
					<div class="text-center py-4 text-gray-400 text-xs italic">尚無入庫紀錄</div>
				@else
					<div class="space-y-3">
						@foreach($purchaseRecords as $record)
							<div class="bg-base-200 p-3 rounded-lg border border-base-300">
								<div class="flex justify-between items-start">
									<span class="text-sm font-bold text-gray-700">{{ $record['product_name'] }}</span>
									<span class="badge badge-outline badge-sm text-xs">
										{{ $record['date'] }}
									</span>
								</div>
								<div class="flex justify-between mt-2">
									<span class="text-xs text-gray-500">入庫數量: {{ $record['quantity'] }}</span>
									<span class="font-mono text-sm text-blue-600">
										成本 (TWD): ${{ number_format($record['cost'], 2) }}
									</span>
								</div>
							</div>
						@endforeach
					</div>
					<div class="mt-4 text-center">
						{{-- 導向庫存管理頁面並自動過濾該供應商 --}}
						<x-button label="查看完整庫存清單" :link="route('inventory.index', ['supplier_id' => $editingSupplier?->id])" class="btn-sm btn-ghost" />
					</div>
				@endif
			@endif
		
            <x-slot:actions>
                <x-button label="關閉" @click="$wire.drawer = false" />
                @if(!$isReadOnly)
                    <x-button label="儲存供應商" type="submit" icon="o-check" class="btn-primary" spinner="save" />
                @endif
            </x-slot:actions>
        </x-form>
    </x-drawer>
</div>