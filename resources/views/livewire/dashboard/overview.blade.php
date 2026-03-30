{{-- resources/views/livewire/dashboard/overview.blade.php --}}
<div>
    <x-header title="系統概覽" subtitle="今日經營數據與趨勢分析">
        <x-slot:actions>
            <x-button label="新增銷貨" icon="o-plus" class="btn-primary" link="{{ route('sales.create') }}" />
            <x-button label="快速進貨" icon="o-shopping-cart" :link="route('purchases.create')" />
        </x-slot:actions>
    </x-header>

    {{-- 統計卡片 --}}
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
        <x-stat title="今日營業額" value="NT$ {{ number_format($stats['todaySales']) }}" icon="o-sun" color="text-blue-500" />
        <x-stat title="本月營業額" value="NT$ {{ number_format($stats['monthSales']) }}" icon="o-calendar" description="客戶實付" />
        <x-stat title="本月淨進帳" value="NT$ {{ number_format($stats['monthNetProfit']) }}" icon="o-currency-dollar" color="text-emerald-500" description="商家實收" />
        <x-stat title="庫存總額" value="NT$ {{ number_format($stats['inventoryValue']) }}" icon="o-circle-stack" />
        <x-stat title="庫存預警" value="{{ $stats['lowStockCount'] }}" icon="o-exclamation-triangle" color="text-orange-500" />
    </div>

    {{-- 圖表區域 --}}
	<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
		
		{{-- 營業收入統計圖表 --}}
		<div class="shadow p-4 bg-white rounded-lg min-h-[300px]">
			<h3 class="text-lg font-bold mb-4 text-gray-700">營業收入統計（最近12個月顧客付款合計）</h3>
			<div class="h-[300px]">
				<canvas id="salesChart"></canvas>
			</div>
		</div>

		{{-- 實際收款統計圖表 --}}
		<div class="shadow p-4 bg-white rounded-lg min-h-[300px]">
			<h3 class="text-lg font-bold mb-4 text-gray-700">實際收款統計（最近12個月最終訂單進帳）</h3>
			<div class="h-[300px]">
				<canvas id="profitChart"></canvas>
			</div>
		</div>
	</div>

	{{-- Chart.js 初始化 --}}
	<script>
    // 1. 封裝初始化函式
    function initDashboardCharts() {
        // 檢查 Canvas 元素是否存在，避免在其他頁面執行報錯
        const salesEl = document.getElementById('salesChart');
        const profitEl = document.getElementById('profitChart');

        if (!salesEl || !profitEl) return;

        // 準備數據 (由 Blade 渲染至 JS)
        const labels = @json($monthlyData->pluck('month')->map(fn($m) => \Carbon\Carbon::parse($m)->format('Y/m')));
        const salesData = @json($monthlyData->pluck('sales'));
        const profitData = @json($monthlyData->pluck('profit'));

        // 營業額圖表
        new Chart(salesEl.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '營業額',
                    data: salesData,
                    backgroundColor: '#3b82f6',
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'NT$ ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'NT$ ' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });

        // 淨利圖表
        new Chart(profitEl.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '淨利',
                    data: profitData,
                    backgroundColor: '#10b981',
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'NT$ ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'NT$ ' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });
    }

    // 2. 監聽導覽事件 (處理往返 Dashboard)
    document.addEventListener('livewire:navigated', initDashboardCharts);

    // 3. 處理初次進入頁面
    document.addEventListener('DOMContentLoaded', initDashboardCharts);
</script>

    {{-- 最近銷貨記錄 --}}
	<div class="bg-white shadow rounded-lg p-4 md:p-6">
		<div class="flex justify-between items-center mb-4">
			<h2 class="text-lg font-semibold text-gray-800">最近銷貨記錄</h2>
			<x-button label="查看全部" link="/sales" class="btn-ghost btn-sm text-primary" />
		</div>

		{{-- 1. 電腦端：顯示完整表格 (md 以上) --}}
		<div class="hidden md:block overflow-x-auto">
			<table class="w-full">
				<thead>
					<tr class="border-b border-gray-100 bg-gray-50/50">
						<th class="text-left py-3 px-2 text-xs font-bold text-gray-500 uppercase">日期</th>
						<th class="text-left py-3 px-2 text-xs font-bold text-gray-500 uppercase">客戶</th>
						<th class="text-left py-3 px-2 text-xs font-bold text-gray-500 uppercase">通路</th>
						<th class="text-right py-3 px-2 text-xs font-bold text-gray-500 uppercase">顧客付款</th>
						<th class="text-right py-3 px-2 text-xs font-bold text-gray-500 uppercase">最終進帳</th>
						<th class="text-center py-3 px-2 text-xs font-bold text-gray-500 uppercase">狀態</th>
					</tr>
				</thead>
				<tbody>
					@forelse($recentSales as $sale)
						<tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
							<td class="py-3 px-2 text-sm text-gray-600">{{ $sale->created_at->format('Y-m-d') }}</td>
							<td class="py-3 px-2 text-sm font-medium text-gray-900">{{ $sale->customer->name ?? '未知客戶' }}</td>
							<td class="py-3 px-2 text-sm text-gray-600">
								<span class="badge badge-ghost badge-sm">{{ $sale->channel }}</span>
							</td>
							<td class="py-3 px-2 text-sm text-right font-mono text-blue-600">NT$ {{ number_format($sale->customer_total) }}</td>
							<td class="py-3 px-2 text-sm text-right font-mono font-bold text-emerald-600">NT$ {{ number_format($sale->final_net_amount) }}</td>
							<td class="py-3 px-2 text-center">
								<span class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-[10px] font-bold">
									{{ $sale->status ?? '完成' }}
								</span>
							</td>
						</tr>
					@empty
						<tr><td colspan="6" class="text-center py-10 text-gray-400">暫無銷貨記錄</td></tr>
					@endforelse
				</tbody>
			</table>
		</div>

		{{-- 2. 手機端：顯示卡片列表 (md 以下) --}}
		<div class="md:hidden space-y-3">
			@forelse($recentSales as $sale)				
				<div class="p-4 border border-gray-100 rounded-xl bg-gray-50/30 space-y-3 cursor-pointer hover:bg-gray-100 transition-colors active:scale-[0.98]"
				>
					<div class="flex justify-between items-start">
						<div>
							<div class="text-xs text-gray-400">{{ $sale->created_at->format('Y-m-d H:i') }}</div>
							<div class="font-bold text-gray-800">{{ $sale->customer->name ?? '未知客戶' }}</div>
						</div>
						{{-- 狀態標籤 --}}
						<span class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-[10px] font-bold">
							{{ $sale->status ?? '完成' }}
						</span>
					</div>

					<div class="grid grid-cols-2 gap-2 pt-2 border-t border-gray-100">
						<div>
							<div class="text-[10px] text-gray-400 uppercase">顧客付款</div>
							<div class="text-sm font-mono text-blue-600 font-bold">NT$ {{ number_format($sale->customer_total) }}</div>
						</div>
						<div class="text-right">
							<div class="text-[10px] text-gray-400 uppercase">最終進帳</div>
							<div class="text-sm font-mono text-emerald-600 font-bold">NT$ {{ number_format($sale->final_net_amount) }}</div>
						</div>
					</div>

					<div class="flex justify-between items-center text-[10px]">
						<span class="text-gray-400">來源通路：<span class="text-gray-600">{{ $sale->channel }}</span></span>
						<x-icon name="o-chevron-right" class="w-4 h-4 text-gray-300" />
					</div>
				</div>
			@empty
				<div class="text-center py-10 text-gray-400 text-sm italic">暫無銷貨記錄</div>
			@endforelse
		</div>
	</div>
</div>