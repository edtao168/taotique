{{-- resources/views/livewire/accountings/period-management.blade.php --}}
{{-- 注意目錄是 accountings (有 s) --}}

<div>
    <x-header title="會計期間管理" subtitle="關帳 / 反關帳管理" separator />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- 左側：關帳表單 --}}
        <div class="lg:col-span-1">
            <x-card title="關閉月份" subtitle="選擇要關帳的月份">
                <x-form wire:submit="close">
                    <x-input 
                        label="會計期間" 
                        type="month" 
                        wire:model="yearMonth" 
                        required 
                    />
                    <x-textarea 
                        label="備註" 
                        wire:model="note" 
                        placeholder="例如：月底結帳" 
                        rows="2"
                    />
                    <x-slot:actions>
                        <x-button 
                            label="執行關帳" 
                            icon="o-check-circle" 
                            class="btn-primary" 
                            type="submit" 
                            spinner
                        />
                    </x-slot:actions>
                </x-form>

                <x-alert 
                    title="⚠️ 關帳前注意事項" 
                    icon="o-exclamation-triangle" 
                    class="mt-4 alert-warning"
                >
                    <ul class="list-disc list-inside text-sm">
                        <li>所有銷售單必須已完成（completed / settled）</li>
                        <li>非現金訂單必須已結算（settled）</li>
                        <li>關帳後該月份資料無法修改</li>
                    </ul>
                </x-alert>
            </x-card>
        </div>

        {{-- 右側：關帳狀態列表 --}}
        <div class="lg:col-span-2">
            <x-card title="關帳狀態" subtitle="最近 12 個月">
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>期間</th>
                                <th>狀態</th>
                                <th>關帳時間</th>
                                <th>關帳人員</th>
                                <th>重開次數</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($periods as $period)
                                <tr>
                                    <td class="font-mono">{{ $period['period'] }}</td>
                                    <td>
                                        @if($period['is_closed'])
                                            <x-badge value="已關帳" class="badge-success" />
                                        @else
                                            <x-badge value="開啟中" class="badge-warning" />
                                        @endif
                                    </td>
                                    <td>
                                        @if($period['closed_at'])
                                            {{ $period['closed_at']->format('Y-m-d H:i') }}
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td>{{ $period['closed_by'] ?? '-' }}</td>
                                    <td>
                                        @if($period['reopen_count'] > 0)
                                            <x-badge value="{{ $period['reopen_count'] }}" class="badge-info" />
                                        @else
                                            <span class="text-gray-400">0</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($period['is_closed'])
                                            <x-button 
                                                label="反關帳" 
                                                icon="o-lock-open" 
                                                class="btn-ghost btn-xs text-warning" 
                                                wire:click="reopen('{{ $period['period'] }}')"
                                                wire:confirm="確定要重新開啟 {{ $period['period'] }} 嗎？"
                                            />
                                        @else
                                            <span class="text-gray-400 text-xs">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-gray-400">暫無資料</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    </div>
</div>