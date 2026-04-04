{{-- resources/views/livewire/settings/users/user-management.blade.php --}}
<div>
    <x-header title="員工權限管理" subtitle="控管帳號、角色、據點、權限" separator>
        <x-slot:actions>
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
            @if(auth()->user()->role === 'owner')
                <x-button label="建立新帳號" wire:click="create" icon="o-plus" class="btn-primary" />
            @endif
        </x-slot:actions>
    </x-header>

    {{-- --- PC 端表格模式 (LG 以上顯示) --- --}}
    <div class="hidden lg:block">
        <x-card shadow>
            <x-table :rows="$users" :headers="$headers" with-pagination>
                @scope('cell_name', $user)
                    <div class="flex items-center gap-3">
                        <x-avatar 
                            :placeholder="mb_substr($user->name, 0, 1)" 
                            class="!w-10 bg-primary text-primary-content" 
                        />
                        <div>
                            <div class="font-bold">{{ $user->name }}</div>
                            <div class="text-xs opacity-50">{{ $user->email }}</div>
                        </div>
                    </div>
                @endscope

                @scope('cell_role', $user)
                    <x-badge :value="collect($this->roleOptions)->firstWhere('id', $user->role)['name'] ?? $user->role" class="badge-outline" />
                @endscope

                @scope('cell_is_active', $user)
                    <x-checkbox 
                        wire:click="toggleActive({{ $user->id }})" 
                        :checked="$user->is_active" 
                        :disabled="auth()->user()->role !== 'owner' || $user->id === auth()->id()"
                        tight 
                    />
                @endscope

                @scope('actions', $user)
                    <div class="flex gap-2">
                        <x-button icon="o-pencil" wire:click="edit({{ $user->id }})" class="btn-ghost btn-sm text-info" />
                    </div>
                @endscope
            </x-table>
        </x-card>
    </div>

    {{-- --- 手機端卡片模式 (LG 以下顯示) --- --}}
    <div class="block lg:hidden space-y-3">
        @foreach($users as $user)
            <x-card class="shadow-sm border border-base-200" @click="$wire.edit({{ $user->id }})">
                <div class="flex gap-4">
                    {{-- 左側：頭像與狀態 --}}
                    <div class="relative shrink-0">
                        <x-avatar 
                            :placeholder="mb_substr($user->name, 0, 1)" 
                            class="!w-14 bg-primary text-primary-content rounded-xl" 
                        />
                        @if(!$user->is_active)
                            <span class="absolute -top-1 -left-1 badge badge-error badge-xs p-1"></span>
                        @endif
                    </div>

                    {{-- 右側：資訊 --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start">
                            <x-badge :value="collect($this->roleOptions)->firstWhere('id', $user->role)['name'] ?? $user->role" class="badge-neutral badge-xs opacity-70" />
                            <div class="flex gap-1" onclick="event.stopPropagation();">
                                <x-button icon="o-pencil" wire:click="edit({{ $user->id }})" class="btn-ghost btn-xs text-blue-500" />
                            </div>
                        </div>
                        
                        <h3 class="font-bold truncate text-base mt-1 {{ !$user->is_active ? 'text-gray-400 line-through' : '' }}">
                            {{ $user->name }}
                        </h3>
                        <p class="text-xs text-gray-500 truncate mb-2">{{ $user->email }}</p>

                        <div class="flex justify-between items-end border-t border-base-100 pt-2">
                            <div class="flex flex-wrap gap-1">
                                <x-badge :value="$user->shop->name ?? '未分配據點'" icon="o-map-pin" class="badge-ghost badge-sm text-[10px]" />
                            </div>
                            <div onclick="event.stopPropagation();">
                                <x-checkbox 
                                    wire:click="toggleActive({{ $user->id }})" 
                                    :checked="$user->is_active" 
                                    :disabled="auth()->user()->role !== 'owner' || $user->id === auth()->id()"
                                    tight 
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </x-card>
        @endforeach

        {{-- 分頁器 (手機端顯示) --}}
        <div class="mt-4">
            {{ $users->links(data: ['scrollTo' => false]) }}
        </div>
    </div>
	
	{{-- 編輯用 Drawer --}}
    <x-drawer wire:model="userDrawer" title="{{ $selectedUser ? '編輯帳號' : '新建帳號' }}" right separator with-close-button class="w-full lg:w-1/3">
        <x-form wire:submit="save">
            <x-input label="顯示名稱" wire:model="name" icon="o-user" />
            <x-input label="Email (登入帳號)" wire:model="email" icon="o-envelope" />
            <x-input label="登入密碼" wire:model="password" type="password" icon="o-key" hint="{{ $selectedUser ? '若不修改請留空' : '至少 8 位字元' }}" />
            
            <x-select label="角色權限" :options="$roleOptions" wire:model="role" icon="o-shield-check" />
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-select label="所屬營業點" :options="$shops" wire:model="shop_id" icon="o-map-pin" placeholder="請選擇營業點" />
                <x-select label="預設出貨倉庫" :options="$warehouses" wire:model="warehouse_id" icon="o-building-office" placeholder="請選擇倉庫" />
            </div>
            
            <x-slot:actions>
                <x-button label="取消" @click="$wire.userDrawer = false" />
                <x-button label="儲存帳號" type="submit" icon="o-check" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-drawer>
</div>