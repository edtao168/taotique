{{-- 檔案路徑：resources/views/livewire/customers/index.blade.php --}}
<div>
    <x-header title="客戶管理" subtitle="管理您的客戶與消費記錄" separator progress-indicator>
        <x-slot:actions>            
            <x-input placeholder="搜尋姓名、電話..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable class="max-sm:hidden" />
            <x-button icon="o-home" :link="route('dashboard')" class="btn-ghost sm:hidden" />
			<x-button label="去銷售系統" icon="o-list-bullet" :link="route('sales.index')" class="btn-outline" />
            <x-button label="新增客戶" wire:click="showCreate" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- 搜尋列 - 專屬手機端顯示 --}}
    <div class="mb-4 sm:hidden">
        <x-input placeholder="搜尋姓名、電話..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
    </div>

    {{-- 1. PC 端顯示：表格模式 --}}
    <x-card class="hidden sm:block">
        <x-table :headers="$headers" :rows="$customers" with-pagination @row-click="$wire.showDetails($event.detail.id)">
            @scope('cell_customer_paid_sum', $customer)
                <span class="font-mono text-gray-600">
                    {{ number_format($customer->customer_paid_sum ?? 0, 2) }}
                </span>
            @endscope

            @scope('cell_actual_received_sum', $customer)
                <span class="font-mono text-emerald-600 font-bold">
                    {{ number_format($customer->actual_received_sum ?? 0, 2) }}
                </span>
            @endscope

            @scope('actions', $customer)
                <div class="flex gap-2">
                    <x-button icon="o-pencil" wire:click="edit({{ $customer->id }})" @click.stop="" class="btn-ghost btn-sm text-blue-500" />                    
                    <x-button icon="o-shopping-cart" :link="route('sales.create', ['customer_id' => $customer->id])" @click.stop="" class="btn-ghost btn-sm text-emerald-600" />
                    <x-button icon="o-trash" wire:click="delete({{ $customer->id }})" wire:confirm="確定刪除？" @click.stop="" class="btn-ghost btn-sm text-red-400" />
                </div>
            @endscope
        </x-table>
    </x-card>

    {{-- 2. 手機端顯示：卡片清單模式 --}}
    <div class="sm:hidden space-y-4">
        @foreach($customers as $customer)
            <x-card class="shadow-sm border-l-4 border-l-primary" @click="$wire.showDetails({{ $customer->id }})">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-bold text-lg text-base-content">{{ $customer->name }}</div>
                        <div class="text-xs text-gray-400 flex items-center gap-1">
                            <x-icon name="o-phone" class="w-3 h-3" /> {{ $customer->phone ?? '無電話' }}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] uppercase text-gray-400">實收總額</div>
                        <div class="text-emerald-600 font-bold font-mono">
                            {{ number_format($customer->actual_received_sum ?? 0, 2) }}
                        </div>
                    </div>
                </div>

                <x-slot:figure>
                    {{-- 這裡可放置裝飾性小標籤或進度條 --}}
                </x-slot:figure>

                <x-slot:actions>
                    <x-button icon="o-pencil" wire:click="edit({{ $customer->id }})" @click.stop="" class="btn-ghost btn-sm text-blue-500" />
                    <x-button icon="o-shopping-cart" :link="route('sales.create', ['customer_id' => $customer->id])" @click.stop="" class="btn-ghost btn-sm text-emerald-600" />
                </x-slot:actions>
            </x-card>
        @endforeach

        <div class="mt-4">
            {{ $customers->links(data: ['scrollTo' => false]) }}
        </div>
    </div>

    {{-- 3. 優化後的 Drawer --}}
    <x-drawer wire:model="drawer" :title="$isReadOnly ? '客戶資料詳情' : (isset($formData['id']) ? '修改客戶資料' : '新增客戶')" right separator with-close-button class="w-full sm:w-11/12 lg:w-1/3">
        <x-form wire:submit="save" class="pb-20"> {{-- 增加底部內距避免被動作列遮擋 --}}
            <div class="grid grid-cols-1 gap-4">
                <div class="bg-base-200/50 p-4 rounded-xl space-y-4">
                    <x-input label="客戶姓名" wire:model="formData.name" icon="o-user" inline :readonly="$isReadOnly"/>
                    <x-input label="聯絡電話" wire:model="formData.phone" icon="o-phone" inline :readonly="$isReadOnly"/>
                    <x-input label="微信 (WeChat)" wire:model="formData.wechat" icon="o-chat-bubble-left-right" inline :readonly="$isReadOnly"/>
                </div>

                <x-textarea label="備註" wire:model="formData.notes" placeholder="紀錄喜好、成色要求等..." rows="3" :readonly="$isReadOnly" />
                
				@if($isReadOnly)
					<div class="divider mt-4 text-xs font-bold uppercase tracking-widest text-primary">最近消費紀錄</div>
					
					@forelse($customerSales as $sale)
						<div class="flex items-center justify-between p-3 border-b border-base-200 last:border-0 hover:bg-base-100 transition-colors">
							<div>
								{{-- 移除跳轉 show 的邏輯，改為純資訊顯示 --}}
								<p class="text-[10px] font-mono text-gray-400">{{ $sale['invoice_number'] }}</p>
								<p class="text-xs">{{ \Carbon\Carbon::parse($sale['sold_at'])->format('Y-m-d') }}</p>
							</div>
							<div class="text-right">
								<p class="text-sm font-bold text-emerald-600 font-mono">
									${{ number_format($sale['customer_total'], 2) }}
								</p>
								{{-- 這裡改為跳轉到銷售模組，並帶入單號過濾條件 --}}
								<x-button 
									:link="route('sales.index', ['search' => $sale['invoice_number']])" 
									icon="o-arrow-top-right-on-square" 
									tooltip="前往銷售單"
									class="btn-xs btn-ghost text-blue-400" 
								/>
							</div>
						</div>
					@empty
						<div class="text-center py-8">
							<x-icon name="o-archive-box" class="w-8 h-8 mx-auto text-base-300" />
							<p class="text-xs text-gray-400 mt-2 italic">尚無消費紀錄</p>
						</div>
					@endforelse
					
				@endif
            </div>

            <x-slot:actions>
                <x-button label="取消" @click="$wire.drawer = false" class="max-sm:btn-sm" />
                @if(!$isReadOnly)
                    <x-button label="儲存" type="submit" icon="o-check" class="btn-primary max-sm:btn-sm" spinner="save" />
                @endif
            </x-slot:actions>
        </x-form>
    </x-drawer>
</div>