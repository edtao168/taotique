{{-- resources/views/livewire/settings/users/user-management.blade.php --}}
<div>
    <x-header title="員工權限管理" subtitle="控管帳號、角色、據點、權限" separator>
		<x-slot:actions>
			<x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
			{{-- 只有 Owner 能看到新增按鈕 --}}
			@if(auth()->user()->role === 'owner')
				<x-button label="建立新帳號" wire:click="create" icon="o-plus" class="btn-primary" />
			@endif
        </x-slot:actions>
    </x-header>
    <x-card>
        <x-table :rows="$users" :headers="$headers">
            @scope('cell_name', $user)
                <div class="flex items-center gap-3">
                    <x-avatar 
						:placeholder="mb_substr($user->name, 0, 1)" 
						class="!w-10 bg-primary text-primary-content" 
					/>
                        <div class="font-bold">{{ $user->name }}</div>
                        <div class="text-xs opacity-50">{{ $user->email }}</div>
                    </div>
                </div>
            @endscope

            @scope('cell_role', $user)
                <x-select 
					:options="$this->roleOptions" 
					value="{{ $user->role }}" {{-- 直接給值，不使用 wire:model 避免屬性綁定錯誤 --}}
					wire:change="updateRole({{ $user->id }}, $event.target.value)"
					inline
					class="select-sm"
				/>
            @endscope

            @scope('cell_is_active', $user)
                <x-checkbox wire:click="toggleActive({{ $user->id }})" :checked="$user->is_active" tight />
            @endscope

            @scope('actions', $user)
                {{-- 預留多店分配按鈕 --}}
                <x-button icon="o-home" class="btn-ghost btn-sm" tooltip="分配店鋪/倉庫" />
            @endscope
			
			@scope('actions', $user)
                <div class="flex gap-2">
                    <x-button icon="o-pencil" wire:click="edit({{ $user->id }})" class="btn-ghost btn-sm text-info" />
                    <x-button icon="o-home" class="btn-ghost btn-sm" tooltip="分配據點" />
                </div>
            @endscope
			
			@scope('cell_role', $user)
                <x-badge :value="$user->role" class="badge-outline" />
            @endscope

            @scope('cell_is_active', $user)
                <x-checkbox wire:click="toggleActive({{ $user->id }})" :checked="$user->is_active" tight 
                    :disabled="auth()->user()->role !== 'owner'" />
            @endscope

            @scope('actions', $user)
                <x-button icon="o-pencil" wire:click="edit({{ $user->id }})" class="btn-ghost btn-sm text-info" />
            @endscope
        </x-table>
    </x-card>
	
	{{-- 編輯用 Drawer --}}
    <x-drawer wire:model="userDrawer" title="{{ $selectedUser ? '編輯帳號' : '新建帳號' }}" right separator with-close-button class="w-1/3">
        <x-form wire:submit="save">
            <x-input label="顯示名稱" wire:model="name" icon="o-user" />
            <x-input label="Email (登入帳號)" wire:model="email" icon="o-envelope" />
            <x-input label="登入密碼" wire:model="password" type="password" icon="o-key" hint="{{ $selectedUser ? '若不修改請留空' : '至少 8 位字元' }}" />
            
            <x-select label="角色權限" :options="$roleOptions" wire:model="role" icon="o-shield-check" />
            
            <div class="grid grid-cols-2 gap-4">
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