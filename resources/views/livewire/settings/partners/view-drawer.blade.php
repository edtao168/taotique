<x-drawer wire:model="viewModal" title="夥伴詳細資料" right separator with-close-button class="w-11/12 md:w-1/3">
    @if($viewingPartner)
        <div class="space-y-6">
            <div class="flex justify-center">
                @if($viewingPartner->photo_path)
                    <img src="{{ asset('storage/' . $viewingPartner->photo_path) }}" class="w-32 h-32 rounded-full object-cover ring ring-primary ring-offset-2" />
                @else
                    <div class="w-32 h-32 rounded-full border-2 border-dashed border-gray-300 flex flex-col items-center justify-center bg-gray-50 text-gray-400">
                        <x-icon name="o-user" class="w-12 h-12" />
                        <span class="text-xs">暫無照片</span>
                    </div>
                @endif
            </div>
            <div>
                <h2 class="text-xl font-bold">{{ $viewingPartner->name }}</h2>
                <p class="text-gray-500 text-sm">{{ $viewingPartner->role ?? '一般夥伴' }}</p>
            </div>

            <x-list-item :item="$viewingPartner" no-separator no-hover>
                <x-slot:actions>
                    <x-badge value="{{ $viewingPartner->is_active ? '在職' : '停職' }}" class="{{ $viewingPartner->is_active ? 'badge-success' : 'badge-warning' }}" />
                </x-slot:actions>
            </x-list-item>

            <div class="grid gap-4 border-t pt-4 text-sm">
                <x-input label="電話" value="{{ $viewingPartner->phone }}" readonly icon="o-phone" />
                <x-input label="Line" value="{{ $viewingPartner->contacts['line'] ?? '未提供' }}" readonly icon="o-chat-bubble-left" />
                <x-input label="入職日期" value="{{ $viewingPartner->joined_at?->format('Y-m-d') ?? '未設定' }}" readonly icon="o-calendar" />
            </div>
        </div>
    @endif

    <x-slot:actions>
        <x-button label="關閉" @click="$wire.viewModal = false" />
        <x-button label="修改資料" icon="o-pencil" wire:click="edit({{ $viewingPartner?->id }})" class="btn-primary" />
    </x-slot:actions>
</x-drawer>