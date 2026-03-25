{{-- 檔案路徑：resources/views/livewire/suppliers/index.blade.php --}}
<div>
    <x-header title="供應商管理" subtitle="管理您的貨源與採購對象" separator progress-indicator>
        <x-slot:actions>            
            {{-- PC 端搜尋 --}}
            <x-input placeholder="搜尋名稱、電話..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable class="max-sm:hidden" />
            <x-button icon="o-home" :link="route('dashboard')" class="btn-ghost sm:hidden" />
            <x-button label="去採購系統" icon="o-archive-box" :link="route('purchases.index')" class="btn-outline" />
            <x-button label="新增供應商" wire:click="create" icon="o-plus" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- 手機端搜尋列 --}}
    <div class="mb-4 sm:hidden">
        <x-input placeholder="搜尋名稱、電話..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
    </div>

    {{-- 1. PC 端顯示：表格模式 --}}
    <x-card class="hidden sm:block">
        <x-table :headers="$headers" :rows="$suppliers" with-pagination @row-click="$wire.showDetails($event.detail.id)">
            @scope('actions', $supplier)
                <div class="flex gap-2">
                    <x-button icon="o-pencil" wire:click="edit({{ $supplier->id }})" @click.stop="" class="btn-sm btn-ghost text-blue-500" tooltip="編輯" />                    
                    <x-button icon="o-plus-circle" :link="route('purchases.create', ['supplier_id' => $supplier->id])" @click.stop="" class="btn-sm btn-ghost text-orange-600" tooltip="新增採購單" />
                    <x-button icon="o-trash" wire:click="delete({{ $supplier->id }})" wire:confirm="確定要刪除此供應商？" @click.stop="" class="btn-sm btn-ghost text-red-500" tooltip="刪除" />
                </div>
            @endscope
        </x-table>
    </x-card>

    {{-- 2. 手機端顯示：卡片清單模式 --}}
    <div class="sm:hidden space-y-4">
        @foreach($suppliers as $supplier)
            <x-card class="shadow-sm border-l-4 border-l-orange-500" @click="$wire.showDetails({{ $supplier->id }})">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-bold text-lg text-base-content">{{ $supplier->name }}</div>
                        <div class="text-xs text-gray-400 flex items-center gap-1 mt-1">
                            <x-icon name="o-user" class="w-3 h-3" /> {{ $supplier->contact_person ?? '無聯繫人' }}
                        </div>
                        <div class="text-xs text-gray-400 flex items-center gap-1">
                            <x-icon name="o-phone" class="w-3 h-3" /> {{ $supplier->phone ?? '無電話' }}
                        </div>
                    </div>
                    <div class="text-right">
                        {{-- 可依照需求放置供應商分類或標籤 --}}
                        <div class="badge badge-ghost badge-sm text-[10px]">供應商</div>
                    </div>
                </div>

                <x-slot:actions>
                    <x-button icon="o-pencil" wire:click="edit({{ $supplier->id }})" @click.stop="" class="btn-ghost btn-sm text-blue-500" />
                    <x-button icon="o-plus-circle" :link="route('purchases.create', ['supplier_id' => $supplier->id])" @click.stop="" class="btn-ghost btn-sm text-orange-600" />
                </x-slot:actions>
            </x-card>
        @endforeach

        <div class="mt-4">
            {{ $suppliers->links(data: ['scrollTo' => false]) }}
        </div>
    </div>

    {{-- 3. Drawer 詳情與編輯 --}}
    <x-drawer wire:model="drawer" title="{{ $isReadOnly ? '供應商詳情' : ($editingSupplier ? '編輯供應商' : '新增供應商') }}" right separator with-close-button class="w-full sm:w-11/12 lg:w-1/3">
        <x-form wire:submit="save" class="pb-20">
            <div class="grid grid-cols-1 gap-4">
                <div class="bg-base-200/50 p-4 rounded-xl space-y-4">
                    <x-input label="供應商名稱 *" wire:model="name" icon="o-building-office" inline :readonly="$isReadOnly" />
                    <x-input label="聯繫人" wire:model="contact_person" icon="o-user" inline :readonly="$isReadOnly" />
                    <x-input label="電話" wire:model="phone" icon="o-phone" inline :readonly="$isReadOnly" />
                </div>
                
                <div class="divider text-xs font-bold uppercase text-gray-400">社交聯繫方式</div>
                <div class="grid grid-cols-2 gap-2">
                    <x-input label="WeChat" wire:model="contact_json.wechat" icon="o-chat-bubble-left-right" :readonly="$isReadOnly" />
                    <x-input label="Line" wire:model="contact_json.line" icon="o-chat-bubble-bottom-center-text" :readonly="$isReadOnly" />
                </div>
                
                <x-input label="其他聯繫方式" wire:model="contact_json.other" icon="o-link" :readonly="$isReadOnly" />
                <x-textarea label="內部備註" wire:model="notes" rows="3" placeholder="紀錄供應商特色、配合細節等..." :readonly="$isReadOnly" />

                @if($isReadOnly)
                    <div class="divider mt-8 text-xs font-bold uppercase tracking-widest text-primary">最近採購紀錄</div>
                    
                    @forelse($purchaseRecords as $record)
                        <div class="flex items-center justify-between p-3 border-b border-base-200 last:border-0 hover:bg-base-100 transition-colors">
                            <div>
                                <p class="text-sm font-bold text-gray-700">{{ $record['product_name'] }}</p>
                                <p class="text-[10px] text-gray-400">{{ $record['date'] }} · 數量: {{ $record['quantity'] }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-blue-600 font-mono">
                                    ${{ number_format($record['cost'], 2) }}
                                </p>
                                <x-button 
                                    :link="route('inventory.index', ['supplier_id' => $editingSupplier?->id])" 
                                    icon="o-arrow-top-right-on-square" 
                                    class="btn-xs btn-ghost text-blue-400" 
                                />
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8">
                            <x-icon name="o-archive-box" class="w-8 h-8 mx-auto text-base-300" />
                            <p class="text-xs text-gray-400 mt-2 italic">尚無採購紀錄</p>
                        </div>
                    @endforelse
                @endif
            </div>

            <x-slot:actions>
                <x-button label="取消" @click="$wire.drawer = false" class="max-sm:btn-sm" />
                @if(!$isReadOnly)
                    <x-button label="儲存供應商" type="submit" icon="o-check" class="btn-primary max-sm:btn-sm" spinner="save" />
                @endif
            </x-slot:actions>
        </x-form>
    </x-drawer>
</div>