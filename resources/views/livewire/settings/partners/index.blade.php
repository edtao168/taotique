<div>
    <x-header title="夥伴管理" subtitle="我們並肩作戰的每一位夥伴" separator>
        <x-slot:actions>
            <x-input 
                wire:model.live.debounce.500ms="search" 
                icon="o-magnifying-glass" 
                placeholder="搜尋夥伴姓名..." 
                clearable 
                class="max-w-xs" 
            />
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
            <x-button 
                label="歡迎新夥伴" 
                wire:click="$set('partnerModal', true)" 
                icon="o-heart" 
                class="btn-primary" 
            />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-stat 
            title="總夥伴數" 
            value="{{ $partners->count() }}" 
            icon="o-users" 
            description="所有登錄的夥伴" 
        />
        <x-stat 
            title="在職夥伴" 
            value="{{ $partners->where('is_active', true)->whereNull('deleted_at')->count() }}" 
            icon="o-user-plus" 
            description="目前活躍的夥伴" 
            class="bg-base-100" 
        />
        <x-stat 
            title="離職夥伴" 
            value="{{ $partners->whereNotNull('deleted_at')->count() }}" 
            icon="o-user-minus" 
            description="已離職的夥伴" 
            class="bg-base-100" 
        />
    </div>

    {{-- PC 端：顯示表格 --}}
    <x-card class="hidden md:block">
		<x-table 
			:headers="$headers" 
			:rows="$partners" 
			@row-click="$wire.view($event.detail.id)" {{-- 點擊整列進入檢視 --}}
			class="cursor-pointer hover:bg-base-200 transition-colors"
			striped
		>
			{{-- 姓名欄位 --}}
			@scope('cell_name', $partner)
				<div class="flex items-center gap-3">
					<x-avatar image="{{ $partner->photo_path ? asset('storage/' . $partner->photo_path) : '/default-avatar.png' }}" class="!w-10 !h-10" />
					<div class="font-medium">{{ $partner->name }}</div>
				</div>
			@endscope
			
			@scope('cell_photo_path', $partner)
		<x-avatar image="{{ $partner->photo_path ? asset('storage/' . $partner->photo_path) : '/default-avatar.png' }}" class="!w-10 !h-10" />
	@endscope

			{{-- 關聯帳號欄位 --}}
			@scope('cell_user.email', $partner)
				@if($partner->user)
					<div class="flex items-center gap-2">
						<x-icon name="o-envelope" class="w-4 h-4 text-gray-400" />
						<span class="badge badge-outline">{{ $partner->user->email }}</span>
					</div>
				@else
					<div class="flex items-center gap-2 text-gray-400">
						<x-icon name="o-x-circle" class="w-4 h-4" />
						<span class="italic">未關聯帳號</span>
					</div>
				@endif
			@endscope

			{{-- 通訊工具欄位 --}}
			@scope('cell_contacts', $partner)
				@php
					$contacts = $partner->contacts ?? [];
					$hasLine = !empty(data_get($contacts, 'line'));
					$hasWechat = !empty(data_get($contacts, 'wechat'));
				@endphp
				
				<div class="flex flex-wrap gap-2">
					@if($hasLine)
						<div class="badge badge-success gap-1">
							<x-icon name="o-chat-bubble-left-right" class="w-3 h-3" />
							LINE
						</div>
					@endif
					
					@if($hasWechat)
						<div class="badge badge-info gap-1">
							<x-icon name="o-chat-bubble-bottom-center-text" class="w-3 h-3" />
							微信
						</div>
					@endif
					
					@if(!$hasLine && !$hasWechat)
						<span class="text-gray-400 italic text-sm">未設定</span>
					@endif
				</div>
			@endscope

			{{-- 職稱欄位 --}}
			@scope('cell_role', $partner)
				@if($partner->role)
					<span class="badge badge-ghost">{{ $partner->role }}</span>
				@else
					<span class="text-gray-400 italic text-sm">未設定</span>
				@endif
			@endscope

			{{-- 狀態欄位 --}}
			@scope('cell_status', $partner)
				@if($partner->trashed())
					<div class="flex items-center gap-2">
						<x-badge value="已離職" class="badge-error" />
						<span class="text-xs text-gray-500">已軟刪除</span>
					</div>
				@elseif($partner->is_active)
					<x-badge value="在職" class="badge-success" />
				@else
					<x-badge value="停職" class="badge-warning" />
				@endif
			@endscope

			{{-- 操作欄位（使用 stop 阻止事件冒泡） --}}
			@scope('cell_actions', $partner)
				<div class="flex gap-2">
					{{-- 小鉛筆圖標（編輯） --}}
					<x-button 
						icon="o-pencil" 
						wire:click.stop="edit({{ $partner->id }})" {{-- 重點 --}}
						class="btn-sm btn-ghost"
						title="編輯資料"
						spinner
					/>
					
					{{-- 狀態控制按鈕 --}}
					@if($partner->trashed())
						<x-button 
							icon="o-arrow-uturn-right" 
							wire:click.stop="restore({{ $partner->id }})" {{-- 重點 --}}
							class="btn-sm btn-ghost text-success"
							wire:confirm="確定要恢復這位夥伴嗎？"
							title="恢復夥伴"
							spinner
						/>
					@else
						<x-button 
							icon="o-arrow-left-on-rectangle" 
							wire:click.stop="deactivate({{ $partner->id }})" {{-- 重點 --}}
							class="btn-sm btn-ghost text-error"
							wire:confirm="確定要標記為離職嗎？"
							title="標記為離職"
							spinner
						/>
					@endif
				</div>
			@endscope
		</x-table>
	</x-card>
	
	{{-- 手機端：顯示卡片列表 --}}
    <div class="block md:hidden space-y-3">
        @foreach($partners as $partner)
            <x-card @click="$wire.view({{ $partner->id }})" class="cursor-pointer active:bg-base-200">
                <div class="flex items-center gap-4">
                    <x-avatar image="{{ $partner->photo_path ? asset('storage/' . $partner->photo_path) : '/default-avatar.png' }}" class="!w-14 !h-14" />
                    <div class="flex-1">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-bold text-lg text-primary">{{ $partner->name }}</div>
                                <div class="text-sm opacity-70">{{ $partner->role ?? '一般夥伴' }}</div>
                            </div>
                            @if($partner->trashed())
                                <x-badge value="已離職" class="badge-error badge-sm" />
                            @else
                                <x-badge value="在職" class="{{ $partner->is_active ? 'badge-success' : 'badge-warning' }} badge-sm" />
                            @endif
                        </div>
                        
                        <div class="mt-2 flex flex-wrap gap-2">
                            @if(!empty(data_get($partner->contacts, 'line')))
                                <x-badge value="LINE" icon="o-chat-bubble-left-right" class="badge-outline badge-xs text-success" />
                            @endif
                            @if(!empty(data_get($partner->contacts, 'wechat')))
                                <x-badge value="微信" icon="o-chat-bubble-bottom-center-text" class="badge-outline badge-xs text-info" />
                            @endif
                        </div>
                    </div>
                    <x-icon name="o-chevron-right" class="w-5 h-5 opacity-30" />
                </div>
                
                {{-- 手機端獨立操作按鈕 --}}
                <x-slot:actions>
                    <x-button icon="o-pencil" wire:click.stop="edit({{ $partner->id }})" class="btn-sm btn-ghost" />
                </x-slot:actions>
            </x-card>
        @endforeach
    </div>
	
    @include('livewire.settings.partners.view-drawer')
    @include('livewire.settings.partners.form-drawer')
</div>