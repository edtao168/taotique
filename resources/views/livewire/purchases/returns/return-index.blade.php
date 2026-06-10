{{-- 檔案路徑：resources/views/livewire/purchases/returns/return-index.blade.php --}}
<div>
    <x-header title="採購退回管理" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋退單號、原單號或供應商..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="回採購總覽" icon="o-arrow-left" :link="route('purchases.index')" />
            <x-button label="開始退貨" icon="o-plus" class="btn-primary" :link="route('purchases.index')" tooltip="請從採購紀錄中選擇單據進行退貨" />
        </x-slot:actions>
    </x-header>

    <x-card shadow separator>
        {{-- PC 端表格 --}}
        <div class="hidden lg:block">
            <x-table :headers="$headers" :rows="$returns" @row-click="$wire.showDetail($event.detail.id)" class="cursor-pointer" with-pagination>
                @scope('cell_return_no', $return) 
                    <x-badge :value="$return->return_no" 
                             :class="$return->status === 'completed' ? 'badge-success' : ($return->status === 'approved' ? 'badge-warning' : 'badge-error')"
                             class="badge-outline font-mono" />
                @endscope
                
                @scope('cell_purchase.purchase_number', $return) 
                    <span class="text-gray-400 italic font-mono">{{ $return->purchase->purchase_number ?? '-' }}</span>
                @endscope

                @scope('cell_total_return_amount', $return) 
                    <span class="font-bold text-red-600 font-mono">NT$ {{ number_format($return->total_return_amount, 2) }}</span>
                @endscope
                
                @scope('cell_status', $return)
                    @php
                        $statusColor = match($return->status) {
                            'pending' => 'badge-warning',
                            'approved' => 'badge-info',
                            'completed' => 'badge-success',
                            'cancelled' => 'badge-error',
                            default => 'badge-ghost'
                        };
                        $statusText = match($return->status) {
                            'pending' => '待審核',
                            'approved' => '已審核',
                            'completed' => '已結案',
                            'cancelled' => '已取消',
                            default => $return->status
                        };
                    @endphp
                    <x-badge :value="$statusText" :class="$statusColor" />
                @endscope
            </x-table>
        </div>

        {{-- 手機端卡片 --}}
        <div class="block lg:hidden space-y-3">
            @foreach($returns as $return)
                <div class="border rounded-xl p-4 bg-base-50 active:bg-base-200 transition-colors" @click="$wire.showDetail({{ $return->id }})">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex gap-2">
                            <x-badge :value="$return->return_no" 
                                     :class="$return->status === 'completed' ? 'badge-success' : ($return->status === 'approved' ? 'badge-warning' : 'badge-error')"
                                     class="badge-sm font-mono" />
                            @php
                                $statusText = match($return->status) {
                                    'pending' => '待審核',
                                    'approved' => '已審核',
                                    'completed' => '已結案',
                                    'cancelled' => '已取消',
                                    default => $return->status
                                };
                            @endphp
                            <x-badge :value="$statusText" class="badge-ghost badge-xs" />
                        </div>
                        <span class="text-[10px] text-gray-500">{{ $return->created_at->format('m/d H:i') }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-bold text-base">{{ $return->purchase->supplier?->name ?? '未知供應商' }}</p>
                            <p class="text-[10px] text-gray-400 font-mono">原單: {{ $return->purchase->purchase_number ?? '-' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-red-600 font-black text-lg font-mono">NT$ {{ number_format($return->total_return_amount, 2) }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="mt-4">
                {{ $returns->links(data: ['scrollTo' => false]) }}
            </div>
        </div>
    </x-card>

    {{-- 退貨詳情抽屜 --}}
    <x-drawer wire:model="drawer" title="退貨單據詳情" right separator with-close-button class="w-11/12 lg:w-1/3">
        
        @if($selectedReturn && $selectedReturn->status === 'completed')
            {{-- 已完成浮水印 --}}
            <div class="absolute inset-0 flex items-center justify-center overflow-hidden pointer-events-none select-none z-50">
                <div class="border-8 border-success/30 text-success/30 text-7xl font-black uppercase tracking-widest px-8 py-4 rounded-xl border-dashed -rotate-12 transform">
                    已結案
                </div>
            </div>
        @endif
        
        @if($selectedReturn)
            @php
                $canModify = $selectedReturn->status !== 'completed';
            @endphp
            <div class="space-y-6 pb-20">
                
                {{-- 基礎單據資訊 --}}
                <div class="bg-base-100 border rounded-xl p-4 shadow-sm">                    
                    <div class="grid grid-cols-2 gap-y-4 text-sm">
                        <div>
                            <p class="text-[10px] text-gray-400">退貨單號</p>
                            <p class="font-mono font-medium text-gray-700">{{ $selectedReturn->return_no }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400">狀態</p>
                            @php
                                $statusColor = match($selectedReturn->status) {
                                    'pending' => 'badge-warning',
                                    'approved' => 'badge-info',
                                    'completed' => 'badge-success',
                                    'cancelled' => 'badge-error',
                                    default => 'badge-ghost'
                                };
                                $statusText = match($selectedReturn->status) {
                                    'pending' => '待審核',
                                    'approved' => '已審核',
                                    'completed' => '已結案',
                                    'cancelled' => '已取消',
                                    default => $selectedReturn->status
                                };
                            @endphp
                            <x-badge :value="$statusText" :class="$statusColor" />
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400">原採購單號</p>
                            <p class="font-mono text-blue-700">{{ $selectedReturn->purchase->purchase_number ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400">供應商</p>
                            <p class="font-medium">{{ $selectedReturn->purchase->supplier?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400">建立人員</p>
                            <p class="text-xs">{{ $selectedReturn->user?->name ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400">審核人員</p>
                            <p class="text-xs">{{ $selectedReturn->approver?->name ?? '未審核' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400">申請日期</p>
                            <p class="text-xs">{{ $selectedReturn->created_at?->format('Y-m-d H:i') ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400">審核日期</p>
                            <p class="text-xs">{{ $selectedReturn->approved_at?->format('Y-m-d H:i') ?? '-' }}</p>
                        </div>
                    </div>
                </div>
                
                {{-- 核心指標 --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-3 border rounded-xl bg-red-50/50">
                        <p class="text-[10px] text-red-600 mb-1 font-bold">預計退款總額</p>
                        <p class="text-xl font-black text-red-800 font-mono">NT$ {{ number_format($selectedReturn->total_return_amount, 2) }}</p>
                    </div>
                    <div class="p-3 border rounded-xl bg-base-100">
                        <p class="text-[10px] text-gray-500 mb-1 font-bold">商品退回件數</p>
                        <p class="text-xl font-black font-mono">{{ (int)$selectedReturn->items->sum('quantity') }}</p>
                    </div>
                </div>

                {{-- 費用扣除明細 --}}
                @if(($selectedReturn->fees_total_amount ?? 0) > 0)
                <div class="bg-base-200/50 rounded-xl p-4">
                    <p class="text-xs font-bold text-gray-500 mb-3 flex items-center gap-1">
                        <x-icon name="o-minus-circle" class="w-3 h-3 text-error" /> 費用扣除明細
                    </p>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">退貨相關費用</span>
                        <span class="font-mono text-error">-{{ number_format($selectedReturn->fees_total_amount, 2) }}</span>
                    </div>
                </div>
                @endif

                {{-- 商品退回細目 --}}
                <div>
                    <p class="text-sm font-bold border-l-4 border-error pl-2 mb-4">商品退回明細</p>
                    <div class="space-y-3">
                        @foreach($selectedReturn->items as $item)
                            <div class="p-3 border rounded-lg bg-base-50">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                         <p class="font-medium text-sm">
                    {{ ($item->product->sku ?: '') }} - {{ $item->product->name ?? '未知商品' }}
                </p>
                                        <p class="text-[10px] text-gray-400 font-mono">單價: {{ number_format($item->unit_price, 2) }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-500">x {{ (int)$item->quantity }}</p>
                                        <p class="text-red-600 font-bold font-mono text-sm">NT$ {{ number_format($item->subtotal, 2) }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- 底部固定動作欄 --}}
            <x-slot:actions>            
                <div class="flex gap-3 w-full border-t pt-4 bg-base-100">
                    <x-button 
                        label="返回" 
                        icon="o-arrow-uturn-left" 
                        :link="route('purchases.returns.index')" 
                        class="btn-success flex-1" 
                    />
                    
                    @if($selectedReturn->status === 'pending')
                        {{-- 待審核：顯示審核過帳按鈕 --}}
                        <x-button 
                            label="出庫過帳" 
                            icon="o-archive-box" 
                            class="btn-warning flex-1"
                            wire:click="submitReturnPost({{ $selectedReturn->id }})"
							tooltip="完成出庫並產生日記賬"
                            wire:confirm="確定要審核此退貨單並執行財務過帳嗎？此操作將會減少庫存並產生會計分錄！"
                            spinner 
                        />
                    @elseif($selectedReturn->status === 'approved')
                        {{-- 已審核但未過帳（備用） --}}
                        <x-button 
                            label="執行過帳" 
                            icon="o-archive-box-arrow-down" 
                            class="btn-warning flex-1"
                            wire:click="submitReturnPost({{ $selectedReturn->id }})"
                            wire:confirm="確定要執行過帳嗎？"
                            spinner 
                        />
                    @endif
                    
                    @if($selectedReturn->status !== 'completed')
                        <x-button 
                            label="刪除紀錄" 
                            icon="o-trash" 
                            wire:click="delete({{ $selectedReturn->id }})" 
                            wire:confirm="警告：刪除此退貨紀錄將導致庫存回滾至退貨前狀態，確定執行？" 
                            class="btn-error btn-outline flex-1" 
                        />
                    @endif
                </div>                
            </x-slot:actions>
        @endif
    </x-drawer>
</div>