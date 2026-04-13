{{-- 檔案路徑：resources/views/livewire/sales/create.blade.php --}}
<div>
    <x-header title="{{ $sale->exists ? '編輯銷售單' : '建立銷售單' }}" separator progress-indicator>
		<x-slot:middle class="hidden md:flex">
            <div class="flex gap-2 px-4 py-2 bg-base-200 rounded-lg">
                <span class="text-sm opacity-70">預計單號:</span>
                <span class="font-mono font-bold text-primary">{{ $invoice_number }}</span>
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="取消" icon="o-x-mark" link="/sales" />
            <x-button label="確認過帳" icon="o-paper-airplane" class="btn-primary" wire:click="save" spinner />
        </x-slot:actions>
    </x-header>
	
	{{-- 手機端專用的單號顯示 (因為 header middle 在手機端通常會隱藏) --}}
    <div class="md:hidden mb-4 p-3 bg-base-200 rounded-lg flex justify-between items-center">
        <span class="text-sm font-bold">預計單號</span>
        <span class="font-mono text-primary font-bold">{{ $invoice_number }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">
        
        {{-- 1. 左側：單據屬性 (1/4) --}}
        <div class="lg:col-span-3 space-y-4">
            <x-card title="單據資訊" shadow separator>
                <div class="space-y-4">
                    <x-choices label="客戶" wire:model="form.customer_id" :options="$customers" single />
                    <x-datetime label="銷售日期" wire:model="form.sold_at" type="datetime-local" icon="o-calendar" />
                    <x-select label="管道" wire:model="form.channel" :options="$channels" />
                    <x-select label="付款方式" wire:model="form.payment_method" :options="config('business.payment_methods')" />
					<x-select label="業務歸屬倉庫" wire:model="form.warehouse_id" :options="$warehouses" placeholder="請選擇倉庫" />

                    <x-textarea label="備註" wire:model="form.remark" rows="2" />
                </div>
            </x-card>
        </div>

        {{-- 2. 中間：商品明細 (2/4) --}}
		<div class="lg:col-span-6">
			<x-card shadow separator>
				<x-slot:title>
					<div class="flex justify-between items-center w-full">
						<span class="font-bold">商品明細</span>
						<div class="flex items-center gap-2">
							<span class="text-xs opacity-50">連續掃描模式</span>
							<x-scanner.button mode="continuous" class="btn-xs btn-outline flex flex-row items-center gap-1" />
						</div>
					</div>
				</x-slot:title>

				{{-- PC 端表格 - 調整寬度分配 --}}
				<div class="hidden md:block overflow-x-auto">
					<table class="table table-compact w-full min-w-[800px]">
						<thead>
							<tr class="bg-base-200">
								<th class="w-2/5">商品名稱(搜尋或掃描)</th>
								<th class="w-1/6 text-right">單價</th>
								<th class="w-1/6">實際發貨倉庫</th>
								<th class="w-1/6 text-right">數量</th>
								<th class="w-1/6 text-right">小計</th>
								<th class="w-12 text-center">操作</th>
							</tr>
						</thead>
						<tbody>
							@foreach($items as $index => $item)
								<tr wire:key="pc-item-{{ $index }}">
									<td class="min-w-[200px]">
										<x-choices 
											wire:model.live="items.{{ $index }}.product_id" 
											:options="$productOptions"
											search-function="search"
											option-label="name"
											searchable 
											single
										/>
									</td>
									<td class="text-right">
										<x-input 
											wire:model.live.debounce.500ms="items.{{ $index }}.price" 
											class="font-mono text-right w-28"
											placeholder="0"
										/>
									</td>
									<td>
										<x-select 
											wire:model.live="items.{{ $index }}.warehouse_id" 
											:options="$warehouses"
											placeholder="選擇"
											class="w-32"
										/>
									</td>
									<td class="text-right">
										<x-input 
											type="number" 
											wire:model.live.debounce.500ms="items.{{ $index }}.quantity" 
											class="font-mono text-right w-24"
											step="0.0001"
											placeholder="1"
										/>
									</td>
									<td class="font-mono text-right whitespace-nowrap">
										<span class="font-bold">
											NT$ {{ number_format(bcmul($item['price'] ?? 0, $item['quantity'] ?? 0, 4), 0) }}
										</span>
									</td>
									<td class="text-center">
										<x-button icon="o-trash" class="btn-ghost btn-xs text-error" wire:click="removeRow({{ $index }})" />
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>

				{{-- 手機端同步移除單行掃描按鈕 --}}
				<div class="md:hidden space-y-3">
					@foreach($items as $index => $item)
						<div wire:key="mobile-item-{{ $index }}" class="p-3 border rounded-lg bg-base-100 relative">
							<x-button icon="o-trash" class="btn-error btn-xs absolute top-1 right-1 rounded-full" wire:click="removeRow({{ $index }})" />
							<x-choices 
								label="商品" 
								wire:model.live="items.{{ $index }}.product_id" 
								:options="$productOptions" 
								search-function="search"
								option-label="name"
								searchable 
								single 
							/>
							<div class="grid grid-cols-2 gap-2 mt-2">
								<x-input label="單價" wire:model.live.debounce.500ms="items.{{ $index }}.price" />
								<x-select 
									label="實際發貨倉庫"
									wire:model.live="items.{{ $index }}.warehouse_id" 
									:options="$warehouses"
									placeholder="選擇倉庫"
									class="font-mono"
								/>
								<x-input label="數量" type="number" wire:model.live.debounce.500ms="items.{{ $index }}.quantity" />
							</div>
						</div>
					@endforeach
				</div>

				<x-slot:actions>
					<x-button label="手動新增行" icon="o-plus" class="btn-ghost btn-sm w-full border-dashed border-2" wire:click="addRow" />
				</x-slot:actions>
			</x-card>
		</div>

        {{-- 3. 右側：結算結帳 (1/4) --}}
        <div class="lg:col-span-3 space-y-4">
            <x-card title="結算" shadow class="bg-base-100 border-t-4 border-primary">
                <div class="space-y-4">
                    {{-- 第一列：小計 --}}
                    <div class="flex justify-between items-center p-2 bg-base-200/50 rounded-lg">
                        <span class="font-bold opacity-70">小計</span>
                        <span class="font-mono text-right">NT$ {{ number_format($form['subtotal'], 0) }}</span>
                    </div>

                    {{-- 第二列：雙欄對照 --}}                  
					<div class="grid grid-cols-2 gap-4 text-xs">
						{{-- 左側：買家區塊 --}}
						<div class="space-y-3">
							<div class="badge badge-info badge-outline font-bold px-4 py-3">買家</div>
							
							@foreach(collect(config('business.fee_types'))->where('target', 'customer') as $field => $config)
								<x-input 
									label="{{ $config['name'] }}" 
									wire:model.live.debounce.500ms="form.{{ $field }}" 
									prefix="{{ $config['operator'] === 'add' ? '+' : '-' }}"
									icon="{{ $config['icon'] ?? '' }}"
									class="input-sm text-right font-mono {{ $config['operator'] === 'sub' ? 'text-error' : '' }}"
									type="number"
									step="0.01"
								/>
							@endforeach

							<div class="pt-2 border-t border-dashed">
								<div class="text-[10px] opacity-50">買家實付</div>
								<div class="text-lg font-bold text-blue-600 font-mono">
									NT$ {{ number_format($form['customer_total'], 2) }}
								</div>
							</div>
						</div>

						{{-- 右側：賣家區塊 --}}
						<div class="space-y-3">
							<div class="badge badge-success badge-outline font-bold px-4 py-3">賣家</div>
							
							@foreach(collect(config('business.fee_types'))->where('target', 'seller') as $field => $config)
								<x-input 
									label="{{ $config['name'] }}" 
									wire:model.live.debounce.500ms="form.{{ $field }}" 
									prefix="{{ $config['operator'] === 'add' ? '+' : '-' }}"
									icon="{{ $config['icon'] ?? '' }}"
									{{-- 針對賣家支出（sub）標註警告顏色 --}}
									class="input-sm text-right font-mono {{ $config['operator'] === 'sub' ? 'text-warning' : '' }}"
									type="number"
									step="0.01"
								/>
							@endforeach
							
							{{-- 如果有特殊的 order_adjustment 欄位不在 config 中，可手動加回 --}}
							@if(!isset(config('business.fee_types')['order_adjustment']))
								<x-input label="帳款調整" wire:model.live.debounce.500ms="form.order_adjustment" prefix="±" class="input-sm text-right font-mono" />
							@endif
						</div>
					</div>

                    <div class="divider my-0"></div>

                    {{-- 第三列：賣家實收 --}}
                    <div class="p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-100">
                        <div class="text-[15px] text-emerald-600 font-bold tracking-widest uppercase mb-1">最終訂單進帳</div>
                        <div class="text-4xl font-black text-emerald-600 font-mono">
                            NT$ {{ number_format($form['final_net_amount'], 0) }}
                        </div>
                    </div>

                    <x-button label="確認收銀 / 過帳" icon="o-check" class="btn-primary w-full btn-lg" wire:click="save" spinner />
                </div>
            </x-card>
        </div>
    </div>

    <x-scanner.modal />
    <x-scanner.scripts />
</div>