{{-- resources/views/livewire/settings/accounts/index.blade.php --}}
<div>
    <x-header title="會計科目表" subtitle="管理所有會計科目" separator>
        <x-slot:actions>
            <x-input wire:model.live="search" placeholder="搜尋..." icon="o-magnifying-glass" />
            {{-- 修正：指定 option-label 與 option-value --}}
            <x-select wire:model.live="type" placeholder="科目類型" :options="$typeOptions" option-label="name" option-value="id" :clearable="true" class="w-36" />
            <x-button label="新增科目" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <x-card padding="none">
        {{-- PC 端表格 --}}
        <div class="hidden md:block">
            {{-- 修正：加入 row-click 邏輯 --}}
            <x-table :headers="$headers" :rows="$rows" @row-click="$wire.edit($event.detail.id)">
                @scope('cell_name', $item)
                    <span class="cursor-pointer">{!! $item->display_name !!}</span>
                @endscope
                
                @scope('cell_type_label', $item)
                    {{-- 修正：從 Model 取得 label 避免依賴 render 的 typeOptions 格式 --}}
                    <x-badge :value="$item->type_label" class="badge-outline" />
                @endscope
                
                @scope('cell_currency', $item)
                    {{ $item->currency ?: '-' }}
                @endscope                
                
                @scope('cell_status', $item)
                    {{-- 修正：阻止事件冒泡以免觸發整行點擊 --}}
                    <div @click.stop>
                        <x-checkbox wire:click="toggleActive({{ $item->id }})" :checked="$item->is_active" class="checkbox-primary checkbox-sm" tight />
                    </div>
                @endscope

                @scope('cell_actions', $item)
                    <div class="flex gap-2 justify-end">
                        <x-button icon="o-pencil" wire:click.stop="edit({{ $item->id }})" class="btn-sm btn-ghost text-primary" />
                    </div>
                @endscope
            </x-table>
        </div>

        {{-- 手機端卡片 --}}
        <div class="md:hidden">
            @foreach($rows as $item)
                {{-- 修正：確保整個區域可點擊 --}}
                <div wire:click="edit({{ $item->id }})" class="p-4 border-b border-base-200 last:border-none hover:bg-base-100 cursor-pointer transition-colors">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="badge badge-primary font-mono font-bold">{{ $item->code }}</span>
                                <span class="font-bold">{{ $item->name }}</span>
                            </div>
                            <div class="flex gap-2 mt-1 text-xs text-gray-400">
                                <span>{{ $item->type_label }}</span>
                                <span>{{ $item->currency ?: '-' }}</span>
                            </div>
                        </div>
                        <div class="flex gap-1" @click.stop>
                            <x-button icon="o-pencil" wire:click="edit({{ $item->id }})" class="btn-sm btn-ghost text-primary" />
                        </div>
                    </div>
                </div>
            @endforeach

            @if($rows->isEmpty())
                <div class="p-8 text-center text-gray-400">目前沒有符合的會計科目</div>
            @endif
        </div>
    </x-card>

    {{-- 新增/編輯 Modal --}}
    <x-modal wire:model="myModal" separator size="lg">
        <x-slot:title>
            {{ $editingItem ? '編輯科目 - ' . $editingItem->code : '新增科目' }}
        </x-slot:title>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-input label="科目代碼" wire:model="code" :readonly="$editingItem !== null" :class="$editingItem ? 'bg-base-200' : ''" required hint="建立後不可修改代碼" />
            <x-input label="科目名稱" wire:model="name" required />
            {{-- 修正：Modal 內的 select 也需對應格式 --}}
            <x-select label="科目類型" wire:model="type_value" :options="$typeOptions" option-label="name" option-value="id" required />
            <x-select label="上層科目" wire:model="parent_id" :options="$parentAccounts" option-label="name" option-value="id" placeholder="無 (此為一級科目)" :clearable="true" />
            
            <div class="col-span-1 md:col-span-2 flex gap-6 items-end border-t border-base-200 pt-4 mt-2">
                <x-checkbox label="貨幣資金科目" wire:model.live="is_monetary" tight />
                <x-checkbox label="啟用狀態" wire:model="is_active" tight />
            </div>

            @if($is_monetary)
				<div class="col-span-1 md:col-span-2">
					<x-select 
						label="指定幣別" 
						wire:model="currency" 
						:options="$currencyOptions" 
						option-label="name" 
						option-value="id" 
						required 
						icon="o-currency-dollar" 
						placeholder="請選擇幣別"
					/>
				</div>
			@endif
        </div>

        <x-slot:actions>
            <x-button label="取消" @click="$wire.myModal = false" />
            <x-button label="儲存科目" wire:click="save" class="btn-primary" spinner="save" icon="o-check" />
        </x-slot:actions>
    </x-modal>
</div>