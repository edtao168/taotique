<x-drawer wire:model="viewModal" title="夥伴檔案詳情" right separator with-close-button class="w-11/12 md:w-1/3">
    @if($viewingPartner)
        <div class="pb-10">
            {{-- 頂部個人名片區 --}}
            <div class="flex flex-col items-center py-6 bg-gradient-to-b from-primary/5 to-transparent rounded-xl mb-6">
                <div class="relative mb-4">
                    @if($viewingPartner->photo_path)
                        <img src="{{ asset('storage/' . $viewingPartner->photo_path) }}" class="w-32 h-32 rounded-full object-cover ring-4 ring-base-100 shadow-xl" />
                    @else
                        <div class="w-32 h-32 rounded-full border-2 border-dashed border-gray-300 flex flex-col items-center justify-center bg-gray-50 text-gray-400">
                            <x-icon name="o-user" class="w-12 h-12" />
                        </div>
                    @endif
                    <div class="absolute bottom-1 right-1">
                        <x-badge value="{{ $viewingPartner->is_active ? '在職中' : '停職' }}" class="{{ $viewingPartner->is_active ? 'badge-success' : 'badge-warning' }} badge-sm shadow" />
                    </div>
                </div>
                <h2 class="text-2xl font-black text-base-content">{{ $viewingPartner->name }}</h2>
                <span class="badge badge-ghost mt-1">{{ $viewingPartner->role ?? '未定義職稱' }}</span>
            </div>

            {{-- 詳細資訊區 --}}
            <div class="space-y-1">
                <x-list-item :item="$viewingPartner" no-separator no-hover>
                    <x-slot:avatar>
                        <x-icon name="o-phone" class="w-5 h-5 text-gray-400" />
                    </x-slot:avatar>
                    <x-slot:value>
                        {{ $viewingPartner->phone ?? '尚未填寫' }}
                    </x-slot:value>
                    <x-slot:sub-value>聯絡電話</x-slot:sub-value>
                </x-list-item>

                <div class="grid grid-cols-2 gap-0 border-y border-base-200">
                    <x-list-item :item="$viewingPartner" no-separator no-hover class="border-r border-base-200">
                        <x-slot:avatar><x-icon name="o-chat-bubble-left" class="w-5 h-5 text-success" /></x-slot:avatar>
                        <x-slot:value>{{ $viewingPartner->contacts['line'] ?? '-' }}</x-slot:value>
                        <x-slot:sub-value>Line ID</x-slot:sub-value>
                    </x-list-item>
                    <x-list-item :item="$viewingPartner" no-separator no-hover>
                        <x-slot:avatar><x-icon name="o-chat-bubble-bottom-center-text" class="w-5 h-5 text-info" /></x-slot:avatar>
                        <x-slot:value>{{ $viewingPartner->contacts['wechat'] ?? '-' }}</x-slot:value>
                        <x-slot:sub-value>微信 ID</x-slot:sub-value>
                    </x-list-item>
                </div>

                <x-list-item :item="$viewingPartner" no-separator no-hover>
                    <x-slot:avatar>
                        <x-icon name="o-calendar" class="w-5 h-5 text-gray-400" />
                    </x-slot:avatar>
                    <x-slot:value>
                        {{ $viewingPartner->joined_at?->format('Y 年 m 月 d 日') ?? '尚未設定' }}
                    </x-slot:value>
                    <x-slot:sub-value>入職日期</x-slot:sub-value>
                </x-list-item>
            </div>
        </div>
    @endif

    <x-slot:actions>
        <x-button label="關閉" @click="$wire.viewModal = false" class="btn-ghost" />
        <x-button label="立即修改" icon="o-pencil" wire:click="edit({{ $viewingPartner?->id }})" class="btn-primary shadow-lg" />
    </x-slot:actions>
</x-drawer>