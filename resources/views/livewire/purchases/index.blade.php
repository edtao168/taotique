{{-- resources/views/livewire/purchases/index.blade.php --}}
<div>
    <x-header title="採購清單" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋單號或供應商..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
            <x-button label="新增採購" icon="o-plus" :link="route('purchases.create')" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    {{-- PC 端表格 --}}
    <div class="hidden lg:block">
        <x-card shadow>
            <x-table :headers="$headers" :rows="$purchases" @row-click="$wire.showDetail($event.detail.id)" class="cursor-pointer" with-pagination>
                {{-- 單號 Badge --}}
                @scope('cell_purchase_number', $purchase)
                    <div class="flex items-center gap-2">
                        <x-badge :value="$purchase->purchase_number" class="badge-neutral font-mono" />
                    </div>
                @endscope
				
				{{-- 狀態 Badge --}}
                @scope('cell_status', $purchase)
					<div class="flex items-center gap-2">
						<x-badge :value="$purchase->status_label" :class="$purchase->status_color . ' badge-sm font-semibold'" />
					</div>
				@endscope

                {{-- 分店 --}}
                @scope('cell_shop_name', $purchase)
                    <span class="text-xs font-semibold text-gray-600">{{ $purchase->shop?->name ?? '未指定' }}</span>
                @endscope

                {{-- 倉庫 --}}
                @scope('cell_warehouse_name', $purchase)
                    <span class="text-xs font-medium text-slate-700 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                        {{ $purchase->warehouse?->name ?? '未指定' }}
                    </span>
                @endscope

                {{-- 採購日期 --}}
                @scope('cell_purchased_at', $purchase)
                    {{ $purchase->purchased_at->format('Y-m-d') }}
                @endscope

                {{-- 供應商 --}}
                @scope('cell_supplier_name', $purchase)
                    {{ $purchase->supplier?->name ?? 'N/A' }}
                @endscope

                {{-- 付款方式 --}}
                @scope('cell_payment_method', $purchase)
                    @php
                        $method = config("business.purchase_methods.{$purchase->payment_method}");
                    @endphp
                    @if($method)
                        <span class="inline-flex items-center gap-1 text-xs font-medium">
                            <x-icon name="{{ $method['icon'] }}" class="w-4 h-4 text-gray-500" />
                            {{ $method['name'] }}
                        </span>
                    @else
                        <span class="text-gray-400 text-xs font-mono">{{ $purchase->payment_method }}</span>
                    @endif
                @endscope

                {{-- 原幣總額 --}}
                @scope('cell_total_amount', $purchase)
                    <span class="font-bold text-blue-700">{{ $purchase->currency }} {{ number_format($purchase->total_amount, 0) }}</span>
                @endscope

                {{-- 功能幣別總額 --}}
                @scope('cell_total_base', $purchase)
                    <span class="font-bold">NT$ {{ number_format($purchase->total_base, 0) }}</span>         
                @endscope
            </x-table>
        </x-card>
    </div>

    {{-- 手機端卡片 --}}
    <div class="block lg:hidden space-y-3">
        @foreach($purchases as $purchase)
            <div class="border rounded-xl p-4 bg-base-100 active:bg-base-200 transition-colors shadow-sm" @click="$wire.showDetail({{ $purchase->id }})">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <x-badge :value="$purchase->purchase_number" class="badge-neutral badge-sm font-mono" />
                        <x-badge :value="$purchase->status_label" :class="$purchase->status_color . ' badge-xs'" />
                        <span class="text-[11px] text-gray-400 font-medium">[{{ $purchase->shop?->name ?? '預設門市' }}]</span>
                    </div>
                    <span class="text-[10px] text-gray-500">{{ $purchase->purchased_at->format('Y-m-d') }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <div>
                        <p class="font-bold text-base">{{ $purchase->supplier?->name ?? '未知供應商' }}</p>
                        <div class="flex items-center gap-1 mt-1 text-[11px] text-gray-400">
                            <x-icon name="{{ config('business.purchase_methods.'.$purchase->payment_method.'.icon', 'o-wallet') }}" class="w-3 h-3" />
                            <span>{{ config('business.purchase_methods.'.$purchase->payment_method.'.name', $purchase->payment_method) }}</span>
                            <span class="mx-1">•</span>
                            <span>{{ $purchase->currency }} @ {{ number_format($purchase->exchange_rate, 2) }}</span>
                        </div>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @php
                                $warehouseNames = $purchase->items->map(fn($item) => $item->warehouse?->name)->filter()->unique();
                            @endphp
                            @foreach($warehouseNames as $wName)
                                <span class="px-1 text-[10px] bg-gray-100 text-gray-500 rounded font-mono">{{ $wName }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-blue-700 font-black text-lg">NT$ {{ number_format($purchase->total_base, 0) }}</p>
                    </div>
                </div>
            </div>
        @endforeach
        <div class="mt-4">
            {{ $purchases->links(data: ['scrollTo' => false]) }}
        </div>
    </div>

    {{-- 採購詳情 Drawer --}}
    <x-drawer wire:model="drawer" title="採購單據詳情" right separator with-close-button class="w-11/12 lg:w-1/3">

        {{-- 退貨浮水印 --}}
        @if($selectedPurchase?->hasReturnRecords())
            <div class="absolute inset-0 flex items-center justify-center overflow-hidden pointer-events-none select-none z-50">
                <div class="border-8 border-error/30 text-error/30 text-7xl font-black uppercase tracking-widest px-8 py-4 rounded-xl border-dashed -rotate-12 transform">
                    已退貨
                </div>
            </div>
        @endif

        @if($selectedPurchase)
            @php
                $isLocked = $selectedPurchase->isLocked();
                $canApprove = $selectedPurchase->status === \App\Enums\WorkflowStatus::DRAFT;
                $canStockIn = $selectedPurchase->status === \App\Enums\WorkflowStatus::APPROVED && !$selectedPurchase->stocked_in_at;
                $canCancel = in_array($selectedPurchase->status, [\App\Enums\WorkflowStatus::DRAFT, \App\Enums\WorkflowStatus::APPROVED]) 
                    && !$selectedPurchase->stocked_in_at 
                    && !$selectedPurchase->hasReturnRecords();
                $isCompleted = $selectedPurchase->status === \App\Enums\WorkflowStatus::COMPLETED;
                $isCancelled = $selectedPurchase->status === \App\Enums\WorkflowStatus::CANCELLED;
            @endphp

            {{-- 狀態標題列 --}}
            <div class="flex items-center justify-between mb-4">
                <p class="text-sm font-bold border-l-4 border-primary pl-2">
                    採購單號：{{ $selectedPurchase->purchase_number }}
                </p>
                <x-badge :value="$selectedPurchase->status_label" :class="$selectedPurchase->status_color . ' badge-md'" />
            </div>

            <div class="space-y-6 pb-20">
                {{-- 基本資訊 --}}
                <div class="bg-base-100 border rounded-xl p-4 shadow-sm">
                    <div class="grid grid-cols-2 gap-y-4 text-sm">
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold">採購日期</p>
                            <p class="font-medium">{{ $selectedPurchase->purchased_at->format('Y-m-d') }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold">供應商</p>
                            <p class="font-medium text-blue-700">{{ $selectedPurchase->supplier?->name }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold">幣別/匯率</p>
                            <p class="text-sm font-mono">{{ $selectedPurchase->currency }} / {{ $selectedPurchase->exchange_rate }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold">付款方式</p>
                            <span class="inline-flex items-center gap-1 font-semibold text-success text-xs">
                                <x-icon name="{{ config('business.purchase_methods.'.$selectedPurchase->payment_method.'.icon', 'o-wallet') }}" class="w-4 h-4" />
                                {{ config('business.purchase_methods.'.$selectedPurchase->payment_method.'.name', $selectedPurchase->payment_method) }}
                            </span>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold">分店</p>
                            <p class="badge badge-sm badge-neutral">{{ $selectedPurchase->shop?->name ?? '未指定' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-gray-400 font-bold">經辦人</p>
                            <p class="text-sm">{{ $selectedPurchase->user?->name }}</p>
                        </div>
                        @if($selectedPurchase->stocked_in_at)
                            <div class="col-span-2">
                                <p class="text-[10px] text-gray-400 font-bold">入庫時間</p>
                                <p class="text-sm text-success font-mono">{{ $selectedPurchase->stocked_in_at->format('Y-m-d H:i:s') }}</p>
                            </div>
                        @endif
                        @if($selectedPurchase->remark)
                            <div class="col-span-2">
                                <p class="text-[10px] text-gray-400 font-bold">備註</p>
                                <p class="text-sm">{{ $selectedPurchase->remark }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- 商品明細 --}}
                <div>
                    <p class="text-sm font-bold border-l-4 border-primary pl-2 mb-3">採購明細</p>
                    <div class="space-y-2">
                        @foreach($selectedPurchase->items as $item)
                            <div class="p-3 border rounded-lg bg-base-50 text-sm">
                                <div class="flex justify-between">
                                    <span class="font-bold">{{ $item->product->full_display_name }}</span>
                                    <span class="font-mono">x{{ (int)$item->quantity }}</span>
                                </div>
                                <div class="flex justify-between mt-1 text-[11px] text-gray-500">
                                    <span>外幣: {{ number_format($item->foreign_price, 2) }}</span>
                                    <span class="text-blue-600 font-bold">NT$ {{ number_format($item->subtotal_base, 0) }}</span>
                                </div>
                                <div class="flex justify-between mt-2 text-[10px] text-gray-400 border-t pt-1">
                                    <span>倉庫：{{ $item->warehouse?->name ?? '未指定' }}</span>
                                    <span>入庫單價：{{ number_format($item->cost_base, 2) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- 費用明細 --}}
                <div class="space-y-3">
                    <div class="flex items-center gap-2 mb-2">
                        <x-icon name="o-calculator" class="w-5 h-5 text-primary" />
                        <span class="font-bold text-sm">費用明細</span>
                    </div>
                    
                    <div class="bg-base-200/30 rounded-xl p-4 space-y-2 border border-dashed">
                        <div class="flex justify-between text-sm">
                            <span class="opacity-60">商品小計</span>
                            <span class="font-mono">{{ $selectedPurchase->currency }} {{ number_format($selectedPurchase->subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="opacity-60">運費</span>
                            <span class="font-mono">+ {{ number_format($selectedPurchase->shipping_fee ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="opacity-60">稅金</span>
                            <span class="font-mono">+ {{ number_format($selectedPurchase->tax ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="opacity-60">其他費用</span>
                            <span class="font-mono">+ {{ number_format($selectedPurchase->other_fees ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm text-error font-bold">
                            <span>折扣金額</span>
                            <span class="font-mono">- {{ number_format($selectedPurchase->discount ?? 0, 2) }}</span>
                        </div>
                        <div class="border-t pt-2 mt-2">
                            <div class="flex justify-between text-sm font-bold">
                                <span>應付總額 ({{ $selectedPurchase->currency }})</span>
                                <span class="text-primary">{{ number_format($selectedPurchase->total_amount, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 總額統計 --}}
                <div class="space-y-3 pt-4">
                    <div class="p-4 border-2 border-primary/20 rounded-2xl bg-primary/5 relative overflow-hidden">
                        <x-icon name="o-currency-dollar" class="absolute -right-2 -bottom-2 w-16 h-16 opacity-10 text-primary" />
                        
                        <div class="flex justify-between items-end relative z-10">
                            <div>
                                <p class="text-[10px] text-primary font-bold uppercase tracking-wider">採購總計 ({{ $selectedPurchase->currency }})</p>
                                <p class="text-2xl font-black text-primary font-mono leading-none">
                                    {{ number_format($selectedPurchase->total_amount, 2) }}
                                </p>
                            </div>
                            <div class="text-right border-l pl-4 border-primary/20">
                                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">功能幣別</p>
                                <p class="text-xl font-bold text-base-content font-mono leading-none">
                                    NT$ {{ number_format($selectedPurchase->total_base, 0) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 底部操作按鈕 --}}
            <x-slot:actions>
                <div class="flex flex-col gap-2 w-full border-t pt-4 bg-base-100">
                    {{-- 退貨狀態提示 --}}
                    @if($selectedPurchase->hasReturnRecords())
                        <div class="alert alert-warning text-sm py-2">
                            <x-icon name="o-exclamation-triangle" class="w-5 h-5" />
                            <span>此單據已有退貨紀錄，無法執行任何異動。</span>
                        </div>
                    @endif

                    {{-- 已取消狀態提示 --}}
                    @if($isCancelled)
                        <div class="alert alert-error text-sm py-2">
                            <x-icon name="o-x-circle" class="w-5 h-5" />
                            <span>此單據已取消，無法執行任何異動。</span>
                        </div>
                    @endif

                    {{-- 操作按鈕列 --}}
                    <div class="flex gap-3 w-full flex-wrap">
                        {{-- 關閉按鈕 --}}
                        <x-button label="關閉" icon="o-x-mark" wire:click="$set('drawer', false)" class="btn-ghost flex-1" />
                        
                        {{-- 編輯按鈕（草稿 & 已審核 & 無退貨） --}}
                        @if($selectedPurchase->isEditable() && !$selectedPurchase->hasReturnRecords())
                            <x-button 
                                label="修改" 
                                icon="o-pencil" 
                                :link="route('purchases.edit', $selectedPurchase->id)" 
                                class="btn-primary flex-1" 
                            />
                        @endif

                        {{-- 審核按鈕（草稿 → 已審核） --}}
                        @if($canApprove && !$selectedPurchase->hasReturnRecords())
                            <x-button 
                                label="審核" 
                                icon="o-check-circle" 
                                class="btn-info flex-1" 
                                wire:click="approvePurchase({{ $selectedPurchase->id }})"
                                wire:confirm="確定要審核此採購單嗎？"
                                spinner
                            />
                        @endif

                        {{-- 入庫按鈕（已審核 → 已完成） --}}
                        @if($canStockIn && !$selectedPurchase->hasReturnRecords())
                            <x-button 
                                label="入庫" 
                                icon="o-archive-box-arrow-down" 
                                class="btn-success flex-1" 
                                wire:click="processStockIn({{ $selectedPurchase->id }})"
                                wire:confirm="確定要執行入庫嗎？這將會同步產生日記帳傳票且無法撤回。"
                                spinner
                            />
                        @endif

                        {{-- 取消按鈕 --}}
                        @if($canCancel && !$selectedPurchase->hasReturnRecords())
                            <x-button 
                                label="取消" 
                                icon="o-x-circle" 
                                class="btn-error btn-outline flex-1" 
                                wire:click="cancelPurchase({{ $selectedPurchase->id }})"
                                wire:confirm="確定要取消此採購單嗎？"
                                spinner
                            />
                        @endif

                        {{-- 刪除按鈕（僅草稿） --}}
                        @if($selectedPurchase->isDeletable() && !$selectedPurchase->hasReturnRecords())
                            <x-button 
                                label="刪除" 
                                icon="o-trash" 
                                wire:click="delete({{ $selectedPurchase->id }})" 
                                wire:confirm="確定要刪除此單據嗎？"
                                class="btn-error flex-1" 
                            />
                        @endif

                        {{-- 退貨按鈕（已完成） --}}
                        @if($isCompleted && !$selectedPurchase->hasReturnRecords())
                            <x-button 
                                label="退貨" 
                                icon="o-arrow-path" 
                                :link="route('purchases.returns.create', ['purchase' => $selectedPurchase->id])"
                                class="btn-warning flex-1" 
                            />
                        @endif
                    </div>
                </div>
            </x-slot:actions>
        @endif
    </x-drawer>
</div>