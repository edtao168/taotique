<div>
    <x-header title="系統備份設定" subtitle="管理雲端同步之資料庫備份檔" separator progress-indicator>
        <x-slot:actions>
            {{-- 這裡可以串接你原本產出備份的 Logic --}}
            <x-button 
				label="立即備份" 
				icon="o-cpu-chip" 
				class="btn-primary" 
				wire:click="runBackup" 
				spinner="runBackup"
			/>
        </x-slot:actions>
    </x-header>

    {{-- PC 端表格：嚴謹呈現 --}}
    <div class="hidden md:block">
        <x-card>
            <x-table :headers="[
                ['key' => 'name', 'label' => '備份檔名'],
                ['key' => 'size', 'label' => '檔案大小'],
                ['key' => 'last_modified', 'label' => '最後更動日期'],
                ['key' => 'actions', 'label' => '操作', 'sortable' => false],
            ]" :rows="$backups">
                @scope('cell_actions', $file)
                    <x-button 
                        icon="o-arrow-down-tray" 
                        wire:click="download('{{ $file['name'] }}')" 
                        class="btn-ghost btn-sm text-info" 
                        spinner 
                    />
                @endscope
            </x-table>
        </x-card>
    </div>

    {{-- 手機端卡片：便於零售現場快速檢視 --}}
    <div class="grid grid-cols-1 gap-3 md:hidden">
        @forelse($backups as $file)
            <x-card class="bg-base-100 shadow-sm border-l-4 border-primary">
                <div class="flex justify-between items-center">
                    <div class="max-w-[70%]">
                        <div class="font-bold text-sm truncate text-base-content">{{ $file['name'] }}</div>
                        <div class="text-xs opacity-60 mt-1">
                            {{ $file['last_modified'] }} <span class="mx-1">|</span> {{ $file['size'] }}
                        </div>
                    </div>
                    <x-button 
                        icon="o-arrow-down-tray" 
                        wire:click="download('{{ $file['name'] }}')" 
                        class="btn-circle btn-outline btn-sm" 
                        spinner 
                    />
                </div>
            </x-card>
        @empty
            <x-alert title="目前尚無備份檔案" icon="o-exclamation-triangle" class="alert-warning" />
        @endforelse
    </div>
</div>