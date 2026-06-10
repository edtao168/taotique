{{-- 檔案路徑：resources/views/livewire/sales/returns/return-index.blade.php --}}
<div>
    <x-header title="銷貨退回管理" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="搜尋退單號、原單號或客戶..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="回銷售總覽" icon="o-arrow-left" :link="route('sales.index')" />
            <x-button label="開始退貨" icon="o-plus" class="btn-primary" :link="route('sales.index')" tooltip="請從銷售紀錄中選擇單據進行退貨" />
        </x-slot:actions>
    </x-header>

    <x-card shadow separator>
        {{-- PC 端表格 --}}
        <div class="hidden lg:block">
            <x-table :headers="$headers" :rows="$returns" @row-click="$wire.showDetail($event.detail.id)" striped>
                @scope('cell_return_no', $return)
                    <x-badge :value="$return->return_no" class="badge-neutral font-mono text-xs" />
                @endscope
				
                @scope('cell_sale.invoice_number', $return)
                    <span class="text-gray-400 italic font-mono">{{ $return->sale->invoice_number }}</span>
                @endscope

                @scope('cell_sale.customer.name', $return)
                    <span class="font-medium">{{ $return->sale->customer->name ?? '散客' }}</span>
                @endscope

                @scope('cell_total_refund_amount', $return)
                    <span class="text-red-600 font-bold font-mono">NT$ {{ number_format($return->total_refund_amount, 2) }}</span>
                @endscope

                @scope('cell_status', $return)
                    @if($return->status === 'pending')
                        <x-badge value="待入庫" class="badge-warning badge-sm" />
                    @elseif($return->status === 'approved')
                        <x-badge value="已入庫/待過帳" class="badge-info badge-sm" />
                    @elseif($return->status === 'completed')
                        <x-badge value="已結案" class="badge-success badge-sm text-white" />
                    @else
                        <x-badge value="已取消" class="badge-ghost badge-sm" />
                    @endif
                @endscope

                @scope('cell_created_at', $return)
                    <span class="text-xs text-gray-500">{{ $return->created_at->format('Y-m-d H:i') }}</span>
                @endscope
            </x-table>
        </div>

        {{-- 手機端卡片 --}}
        <div class="block lg:hidden space-y-3">
            @foreach($returns as $return)
                <div wire:click="showDetail({{ $return->id }})" class="border rounded-xl p-4 bg-base-100 shadow-sm active:scale-[0.99] transition cursor-pointer">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <div class="font-mono font-bold text-sm text-base-content">{{ $return->return_no }}</div>
                            <div class="text-xs text-gray-400 font-mono mt-0.5">原單: {{ $return->sale->invoice_number }}</div>
                        </div>
                        <div>
                            @if($return->status === 'pending')
                                <x-badge value="待入庫" class="badge-warning badge-sm" />
                            @elseif($return->status === 'approved')
                                <x-badge value="待過帳" class="badge-info badge-sm" />
                            @elseif($return->status === 'completed')
                                <x-badge value="已結案" class="badge-success badge-sm text-white" />
                            @else
                                <x-badge value="已取消" class="badge-ghost badge-sm" />
                            @endif
                        </div>
                    </div>
                    <div class="flex justify-between items-center text-xs mt-3 border-t pt-2 border-dashed">
                        <span class="text-gray-500">{{ $return->sale->customer->name ?? '散客' }}</span>
                        <span class="text-red-600 font-bold font-mono text-sm">NT$ {{ number_format($return->total_refund_amount, 2) }}</span>
                    </div>
                </div>
            @endforeach
            <div class="mt-4">
                {{ $returns->links() }}
            </div>
        </div>
    </x-card>

    {{-- 右側詳情抽屜控制面板 --}}
    <x-drawer wire:model="drawer" title="退貨單詳細審查" right separator class="w-full max-w-md md:max-w-xl">
        @if($selectedReturn)
            <div class="space-y-6 pb-20">
                {{-- 狀態進度提示條 --}}
                <div class="stats shadow w-full bg-base-200/50">
                    <div class="stat p-4">
                        <div class="stat-title text-xs">單據狀態</div>
                        <div class="stat-value text-lg mt-1 flex items-center gap-2">
                            @if($selectedReturn->status === 'pending')
                                <span class="text-warning">● 待處理入庫</span>
                            @elseif($selectedReturn->status === 'approved')
                                <span class="text-info">● 已入庫 / 待財務過帳</span>
                            @elseif($selectedReturn->status === 'completed')
                                <span class="text-success">● 已完成結案</span>
                            @else
                                <span class="text-gray-400">● 已取消</span>
                            @endif
                        </div>
                        <div class="stat-desc text-xs mt-1">經辦人: {{ $selectedReturn->user->name ?? '系統' }}</div>
                    </div>
                </div>

                {{-- 基礎單據摘要資訊 --}}
                <div class="grid grid-cols-2 gap-4 text-sm bg-base-100 border p-4 rounded-xl">
                    <div>
                        <span class="text-gray-400 text-xs block">退貨單號</span>
                        <span class="font-mono font-bold">{{ $selectedReturn->return_no }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs block">原銷售單號</span>
                        <span class="font-mono text-gray-600">{{ $selectedReturn->sale->invoice_number }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs block">所屬客戶</span>
                        <span class="font-medium">{{ $selectedReturn->sale->customer->name ?? '散客' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 text-xs block">快照匯率</span>
                        <span class="font-mono text-xs">{{ number_format($selectedReturn->exchange_rate, 4) }}</span>
                    </div>
                </div>

                {{-- 退貨實體商品清單 --}}
                <div>
                    <h4 class="text-sm font-bold mb-2 flex items-center gap-1">
                        <x-icon name="o-shopping-bag" class="w-4 h-4" /> 退回商品明細
                    </h4>
                    <div class="border rounded-xl overflow-hidden bg-base-100">
                        <x-table :headers="[['key' => 'product.name', 'label' => '水晶品名'], ['key' => 'quantity', 'label' => '數量', 'textAlign' => 'text-center'], ['key' => 'subtotal', 'label' => '退款小計', 'textAlign' => 'text-right']]" :rows="$selectedReturn->items" no-hover>
                            @scope('cell_product.name', $item)
                                <div class="flex flex-col">
                                    <span class="text-xs font-medium">{{ $item->product->name }}</span>
                                    @if($item->is_restock)
                                        <span class="text-[10px] text-success flex items-center gap-0.5 mt-0.5">✔ 勾選回庫</span>
                                    @else
                                        <span class="text-[10px] text-error flex items-center gap-0.5 mt-0.5">❌ 報廢不回庫</span>
                                    @endif
                                </div>
                            @endscope
                            @scope('cell_quantity', $item)
                                <span class="font-mono text-xs">{{ number_format($item->quantity, 0) }}</span>
                            @endscope
                            @scope('cell_subtotal', $item)
                                <span class="text-base-content font-semibold font-mono text-xs">NT$ {{ number_format($item->subtotal, 2) }}</span>
                            @endscope
                        </x-table>
                    </div>
                </div>

                {{-- 財務金額匯總區塊 --}}
                <div class="space-y-2 border-t pt-4">
                    <div class="flex justify-between text-xs text-gray-500">
                        <span>商品退款總額：</span>
                        <span class="font-mono">NT$ {{ number_format($selectedReturn->items_total_amount, 2) }}</span>
                    </div>
                    @foreach($selectedReturn->fees as $fee)
                        <div class="flex justify-between text-xs text-error">
                            <span>扣除：{{ config("business.return_fee_types.{$fee->fee_type}.name", $fee->fee_type) }}：</span>
                            <span class="font-mono">- NT$ {{ number_format($fee->amount, 2) }}</span>
                        </div>
                    @endforeach
                    <div class="flex justify-between text-sm font-bold border-t border-dashed pt-2">
                        <span class="text-base-content">最終應退淨額：</span>
                        <span class="text-red-600 font-mono text-base">NT$ {{ number_format($selectedReturn->total_refund_amount, 2) }}</span>
                    </div>
                </div>

                {{-- 備註說明 --}}
				@if(!empty($selectedReturn->return_reason))
					<div class="bg-amber-50/50 dark:bg-amber-950/20 p-3 rounded-xl border border-amber-200/50 text-xs text-amber-800 dark:text-amber-300">
						<strong>退貨原因：</strong>
						<span class="text-red-600 font-mono text-base">{{ $selectedReturn->return_reason }}</span>
                    </div>
				@endif
            </div>

            {{-- 底部動態操作功能列 --}}
            <x-slot:actions>
                <div class="flex gap-3 w-full border-t pt-4 bg-base-100">
                    <x-button label="返回"
						icon="o-arrow-uturn-left"
						:link="route('sales.returns.index')"
						class="btn-success flex-1 text-white"
					/>
				
					{{-- 待處理 -> 觸發退貨入庫 --}}
					@if($selectedReturn->status === 'pending')

					<x-button 
						label="入庫過帳" 
						icon="o-archive-box" 
						wire:click="submitReturnPost({{ $selectedReturn->id }})" 
						wire:loading.attr="disabled"
						tooltip="完成入庫並產生日記賬"
						class="btn-warning flex-1 text-base-100 font-bold tooltip-bottom"
						wire:confirm="確定要執行入庫回補庫存嗎？這將會同步產生會計日記帳與主營成本結轉！"
						spinner 
						/>
					@endif
					
					{{-- 僅在未結案完成前，允許刪除/駁回退貨單 --}}
					@if($selectedReturn->status !== 'completed')
						<x-button 
							label="刪除" 
							icon="o-trash" 
							wire:click="delete({{ $selectedReturn->id }})" 
							wire:confirm="警告：刪除此退貨紀錄將導致庫存回滾至退貨前狀態，確定執行？" 
							wire:loading.attr="disabled"
							class="btn-error btn-outline flex-1" 
						/>
					@else
						<div class="text-center py-2 bg-warning/10 border border-warning/30 text-warning rounded-lg text-xs flex-1">
							<x-icon name="o-check-circle"/> 此單已與傳票連動鎖定，不可撤銷！
						</div>
					@endif                    
                </div>
            </x-slot:actions>
        @endif
    </x-drawer>
</div>