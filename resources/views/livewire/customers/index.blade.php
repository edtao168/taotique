<div>
    <x-header title="客戶管理" subtitle="管理您的零售客戶與消費記錄" separator progress-indicator>
        <x-slot:actions>
            <x-input placeholder="搜尋姓名、電話..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
			<x-button label="新增客戶" wire:click="showCreate" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :headers="$headers" :rows="$customers" with-pagination @row-click="$wire.showDetails($event.detail.id)">
            {{-- 顧客實付 --}}
			@scope('cell_customer_paid_sum', $customer)
				<span class="font-mono text-gray-600">
					{{ number_format($customer->customer_paid_sum ?? 0, 2) }}
				</span>
			@endscope

			{{-- 我方實收 --}}
			@scope('cell_actual_received_sum', $customer)
				<span class="font-mono text-emerald-600 font-bold">
					{{ number_format($customer->actual_received_sum ?? 0, 2) }}
				</span>
			@endscope

            {{-- 操作欄位 --}}
            @scope('actions', $customer)
                <div class="flex gap-2">
                    <x-button icon="o-pencil" wire:click="edit({{ $customer->id }})" @click.stop="" class="btn-ghost btn-sm text-blue-500" />
                    {{-- 預留銷售跳轉 --}}
                    <x-button icon="o-shopping-cart" :link="route('sales.index', ['customer_id' => $customer->id])" class="btn-ghost btn-sm" />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-drawer wire:model="drawer" title="客戶詳細資料" right separator with-close-button class="w-11/12 lg:w-1/3">
        <x-form wire:submit="save">
            <x-input label="客戶姓名" wire:model="formData.name" icon="o-user" :readonly="$isReadOnly"/>
            <x-input label="聯絡電話" wire:model="formData.phone" icon="o-phone" :readonly="$isReadOnly"/>
            <x-input label="微信 (WeChat)" wire:model="formData.wechat" icon="o-chat-bubble-left-right" :readonly="$isReadOnly"/>
            <x-textarea label="備註" wire:model="formData.notes" placeholder="紀錄喜好、成色要求等..." rows="3" :readonly="$isReadOnly"/>

            <x-slot:actions>
                <x-button label="取消" @click="$wire.drawer = false" />
                @if(!$isReadOnly)
					<x-button label="儲存客戶" type="submit" icon="o-check" class="btn-primary" spinner="save" />
				@endif
            </x-slot:actions>
        </x-form>
    </x-drawer>
</div>