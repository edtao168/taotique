<x-drawer wire:model="partnerModal" title="{{ $editingPartner ? '修改夥伴' : '歡迎新夥伴' }}" right separator with-close-button class="w-11/12 md:w-1/3">
    <div class="grid gap-4">
        {{-- 照片上傳區 --}}
        <div wire:key="upload-container-{{ now()->timestamp }}" class="flex justify-center mb-4">
            <x-file 
                wire:model="photo" 
                accept="image/*" 
                crop-after-change
                crop-save-text="確認裁剪"
                crop-cancel-text="取消"
                crop-title-text="調整照片"
                wire:key="upload-{{ $editingPartner?->id ?? 'new' }}"
            >
                <div class="h-32 w-32 rounded-full border-2 border-dashed border-primary flex flex-col items-center justify-center cursor-pointer hover:bg-base-200 overflow-hidden bg-base-100">
                    @if ($photo)
                        <img src="{{ $photo->temporaryUrl() }}" class="h-full w-full object-cover" />
                    @elseif ($photo_path)
                        <img src="{{ asset('storage/' . $photo_path) }}" class="h-full w-full object-cover" />
                    @else
                        <x-icon name="o-camera" class="w-8 h-8 text-primary mb-1" />
                        <span class="text-xs text-primary font-medium">點我上傳</span>
                    @endif
                </div>
            </x-file>
        </div>

        <x-input label="姓名" wire:model="name" icon="o-user" />
        <x-input label="職稱" wire:model="role" icon="o-briefcase" />
        <div class="grid grid-cols-2 gap-2">
            <x-input label="Line ID" wire:model="line_id" />
            <x-input label="WeChat ID" wire:model="wechat_id" />
        </div>
        <x-datepicker label="入職日期" wire:model="joined_at" icon="o-calendar" />
    </div>

	{{-- 增加一個固定高度或溢出處理的容器，確保 actions 容易被看見 --}}
    <div class="pb-20"> {{-- 增加底部填充防止被按鈕擋住 --}}
        <div class="grid gap-4">
            </div>
    </div>
	
    <x-slot:actions>
        <x-button label="取消" @click="$wire.cancel()" />
        <x-button label="儲存" wire:click="save" class="btn-primary" spinner="save" />
    </x-slot:actions>
</x-drawer>