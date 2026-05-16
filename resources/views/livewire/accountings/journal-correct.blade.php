{{-- resources/views/livewire/accountings/journal-correct.blade.php --}}
{{-- [費曼註釋：此視圖只處理已過帳分錄的更正。顯示原始分錄 + 差額分錄的對照] --}}

<div>
    <x-header 
        title="🔧 更正日記帳憑證" 
        subtitle="已過帳分錄不可直接修改，僅可產生差額更正分錄" 
        class="text-warning"
        separator 
    >
        <x-slot:actions>
            <x-button label="返回列表" icon="o-arrow-left" link="{{ route('accountings.journals.index') }}" class="btn-outline" />
            
            <x-button 
                label="📋 查看原始憑證" 
                icon="o-document-text" 
                @click="$wire.showOriginalDrawer = true" 
                class="btn-ghost btn-sm" 
            />
        </x-slot:actions>
    </x-header>

    {{-- 原始分錄資訊（唯讀） --}}
    <div class="mt-4 p-4 bg-base-200 rounded-lg border-l-4 border-gray-400">
        <div class="text-sm text-gray-500 mb-2">原始憑證資訊（唯讀）</div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="text-gray-400">日期：</span>
                <span class="font-mono">{{ $originalJournal->entry_date->format('Y-m-d') }}</span>
            </div>
            <div>
                <span class="text-gray-400">摘要：</span>
                <span>{{ $originalJournal->description }}</span>
            </div>
            <div>
                <span class="text-gray-400">原始金額：</span>
                <span class="font-bold font-mono">{{ number_format($originalAmount, 4) }}</span>
            </div>
            <div>
                <span class="text-gray-400">狀態：</span>
                <x-badge value="已過帳" class="badge-success" />
            </div>
        </div>
    </div>

    <x-form wire:submit="save" id="correctForm" class="mt-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <x-datepicker 
                label="📅 更正日期" 
                wire:model="entry_date" 
                icon="o-calendar" 
                required 
            />

            <div class="lg:col-span-2">
                <x-input 
                    label="更正原因（會計法必填）" 
                    wire:model="correction_reason" 
                    placeholder="例如：金額誤植、科目誤用、發票金額調整..." 
                    icon="o-exclamation-circle" 
                    required 
                />
            </div>

            <div class="lg:col-span-2">
                <x-input 
                    label="更正後摘要" 
                    wire:model="description" 
                    icon="o-chat-bubble-left" 
                />
            </div>

            <x-select 
                label="科目（可變更）" 
                wire:model.live="selected_account" 
                :options="$accountOptions" 
                option-label="name" 
                option-value="id" 
                searchable 
            />

            <x-input 
                label="更正後金額" 
                wire:model.live="amount" 
                type="number" 
                step="0.0001" 
                icon="o-currency-dollar" 
                placeholder="0.0000" 
                required 
            />

            <x-select 
                label="資金帳戶（可變更）" 
                wire:model.live="payment_method" 
                :options="$paymentOptions" 
                option-label="name" 
                option-value="id" 
                searchable 
            />
        </div>

        {{-- 差額分錄預覽 --}}
		@if(count($diff_lines) > 0)
			<div class="mt-8">
				
				{{-- 口語化摘要卡片 --}}
				<div class="mb-4 p-4 bg-info/10 rounded-xl border border-info/30">
					<div class="flex items-start gap-3">
						<x-icon name="o-light-bulb" class="text-info w-6 h-6 mt-0.5" />
						<div>
							<div class="font-bold text-info mb-1">💡 系統將進行以下更正：</div>
							<div class="text-sm text-gray-700 leading-relaxed">
								{{ $correction_summary }}
							</div>
							<div class="mt-2 text-xs text-gray-500">
								原始分錄會保留不動，系統會自動產生一筆「更正分錄」來調整帳務。
							</div>
						</div>
					</div>
				</div>

				{{-- 視覺化分錄預覽 --}}
				<div class="bg-base-100 rounded-xl border shadow-sm overflow-hidden">
					
					{{-- 標題列 --}}
					<div class="px-5 py-3 bg-warning/10 border-b border-warning/20 flex items-center justify-between">
						<div class="font-bold text-warning flex items-center gap-2">
							<x-icon name="o-eye" />
							更正分錄預覽
						</div>
						<div class="text-xs text-gray-500">
							借方合計 = 貸方合計 = {{ number_format($this->amount, 4) }}
						</div>
					</div>

					{{-- 分錄內容 --}}
					<div class="p-5 space-y-3">
						
						{{-- 用口語化方式呈現每筆分錄 --}}
						@foreach($diff_lines as $line)
							<div class="flex items-center gap-3 p-3 rounded-lg border-l-4 {{
								$line['action'] === 'cancel' 
									? 'bg-error/5 border-error/50' 
									: 'bg-success/5 border-success/50'
							}}">
								
								{{-- 圖示 --}}
								<div class="flex-shrink-0">
									<x-icon 
										name="{{ $line['icon'] }}" 
										class="w-5 h-5 {{ $line['action'] === 'cancel' ? 'text-error' : 'text-success' }}" 
									/>
								</div>

								{{-- 內容 --}}
								<div class="flex-grow min-w-0">
									<div class="flex items-baseline gap-2 flex-wrap">
										{{-- 動作標籤 --}}
										<span class="text-xs font-bold px-2 py-0.5 rounded {{
											$line['action'] === 'cancel'
												? 'bg-error/10 text-error'
												: 'bg-success/10 text-success'
										}}">
											{{ $line['action_label'] }}
										</span>
										
										{{-- 科目名稱 --}}
										<span class="font-medium text-sm">
											{{ $line['account_name'] }}
										</span>
										
										{{-- 科目代碼 --}}
										<span class="text-xs text-gray-400 font-mono">
											({{ $line['account_code'] }})
										</span>
									</div>
									
									{{-- 口語化說明 --}}
									<div class="text-xs text-gray-500 mt-1">
										@if($line['action'] === 'cancel')
											原始分錄的{{ $line['entry_type'] === 'debit' ? '借方' : '貸方' }}記錄
										@else
											更正後的{{ $line['entry_type'] === 'debit' ? '借方' : '貸方' }}記錄
										@endif
									</div>
								</div>

								{{-- 金額 --}}
								<div class="flex-shrink-0 text-right">
									<div class="font-bold font-mono text-sm {{
										$line['action'] === 'cancel' ? 'text-error' : 'text-success'
									}}">
										{{ $line['action'] === 'cancel' ? '-' : '+' }}
										{{ number_format($line['amount'], 4) }}
									</div>
									<div class="text-xs text-gray-400">
										{{ $line['entry_type'] === 'debit' ? '借' : '貸' }}
									</div>
								</div>
							</div>
						@endforeach

					</div>

					{{-- 底部說明 --}}
					<div class="px-5 py-3 bg-base-200/50 border-t text-xs text-gray-500 flex items-center gap-2">
						<x-icon name="o-shield-check" class="w-4 h-4" />
						<span>
							紅色 = 沖銷原始錯誤分錄　|　綠色 = 建立更正後分錄　|　兩者相抵即為實際影響
						</span>
					</div>
				</div>

				{{-- 完整對照（折疊式，進階使用者可展開） --}}
				<div class="mt-4 collapse collapse-arrow bg-base-200 rounded-lg">
					<input type="checkbox" /> 
					<div class="collapse-title text-sm font-medium text-gray-600 flex items-center gap-2">
						<x-icon name="o-table-cells" class="w-4 h-4" />
						查看完整對照表（原始 + 更正後）
					</div>
					<div class="collapse-content">
						<div class="overflow-x-auto">
							<table class="table table-sm">
								<thead>
									<tr class="text-xs text-gray-500">
										<th>科目</th>
										<th class="text-right">原始借方</th>
										<th class="text-right">原始貸方</th>
										<th class="text-right text-warning">更正影響</th>
										<th class="text-right">最終淨額</th>
									</tr>
								</thead>
								<tbody>
									@php
										// 計算每個科目的淨額
										$accountBalances = [];
										foreach($generated_lines as $line) {
											$code = $line['account_code'];
											if (!isset($accountBalances[$code])) {
												$accountBalances[$code] = [
													'name' => $line['account_name'],
													'debit' => '0',
													'credit' => '0',
												];
											}
											if ($line['entry_type'] === 'debit') {
												$accountBalances[$code]['debit'] = bcadd(
													$accountBalances[$code]['debit'], 
													$line['is_original'] ? $line['amount'] : $line['amount'],
													4
												);
											} else {
												$accountBalances[$code]['credit'] = bcadd(
													$accountBalances[$code]['credit'],
													$line['is_original'] ? $line['amount'] : $line['amount'],
													4
												);
											}
										}
									@endphp
									
									@foreach($accountBalances as $code => $bal)
										@php
											$netDebit = bcsub($bal['debit'], $bal['credit'], 4);
											$isNetDebit = bccomp($netDebit, '0', 4) > 0;
											$netAmount = $isNetDebit ? $netDebit : bcsub('0', $netDebit, 4);
										@endphp
										<tr class="text-sm">
											<td>
												{{ $bal['name'] }}
												<span class="text-xs text-gray-400">({{ $code }})</span>
											</td>
											<td class="text-right font-mono text-gray-400">
												{{ bccomp($bal['debit'], '0', 4) > 0 ? number_format($bal['debit'], 4) : '-' }}
											</td>
											<td class="text-right font-mono text-gray-400">
												{{ bccomp($bal['credit'], '0', 4) > 0 ? number_format($bal['credit'], 4) : '-' }}
											</td>
											<td class="text-right font-mono text-warning">
												{{-- 簡化顯示：有變動才顯示 --}}
												@if(bccomp($bal['debit'], $bal['credit'], 4) !== 0)
													{{ $isNetDebit ? '+' : '-' }}{{ number_format($netAmount, 4) }}
												@else
													-
												@endif
											</td>
											<td class="text-right font-mono font-bold">
												{{ $isNetDebit ? number_format($netAmount, 4) : '(' . number_format($netAmount, 4) . ')' }}
											</td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					</div>
				</div>

			</div>
		@else
			{{-- 無變動提示 --}}
			<div class="mt-8 p-4 bg-info/10 rounded-lg text-info text-center flex items-center justify-center gap-2">
				<x-icon name="o-information-circle" />
				<span>目前資訊與原始憑證一致，修改「金額」或「會計科目」後將自動產生更正分錄。</span>
			</div>
		@endif

        <x-slot:actions>
            <x-button label="取消" link="{{ route('accountings.journals.index') }}" />
            <x-button 
                label="✅ 確認產生更正分錄" 
                type="submit" 
                class="btn-warning" 
                spinner="save"
				wire:loading.attr="disabled"
                :disabled="count($diff_lines) === 0"
            />
        </x-slot:actions>
    </x-form>
		<x-drawer wire:model="showOriginalDrawer" title="原始憑證詳情" right separator class="w-11/12 lg:w-1/3">
		@include('livewire.accountings.includes._journal-detail', ['journal' => $originalJournal])
		<x-slot:actions>
			<x-button label="關閉" @click="$wire.showOriginalDrawer = false" />
		</x-slot:actions>
	</x-drawer>
</div>