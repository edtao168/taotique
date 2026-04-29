{{-- resources/views/livewire/dashboard/overview.blade.php --}}
<div>
    <x-header title="系統概覽" subtitle="今日經營數據與趨勢分析">
        <x-slot:actions>
            <x-button label="新增銷售" icon="o-chart-bar" class="btn-primary" link="{{ route('sales.create') }}" />
            <x-button label="新增採購" icon="o-shopping-bag" :link="route('purchases.create')" />
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
	<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
		{{-- 左側：最近銷售紀錄 (已改為 div 結構) --}}
		<div class="lg:col-span-3">
			<x-card title="最近銷售" subtitle="最新的 5 筆交易紀錄" shadow separator>
				<x-slot:actions>
					<x-button label="查看全部" icon="o-list-bullet" link="{{ route('sales.index') }}" class="btn-ghost btn-sm" />
				</x-slot:actions>

				<div class="space-y-4">
					{{-- 列表標頭 (僅在 PC 端顯示) --}}
					<div class="hidden md:grid grid-cols-12 gap-4 px-4 py-2 text-xs font-bold text-gray-500 uppercase tracking-wider">
						<div class="col-span-3">單號 / 日期</div>
						<div class="col-span-3">歸屬分店 / 通路</div>
						<div class="col-span-2 text-right">買家實付</div>
						<div class="col-span-2 text-right">最終進帳</div>
						<div class="col-span-2 text-center">操作</div>
					</div>

					{{-- 銷售項目卡片 --}}
					@forelse($recentSales as $sale)
						<div class="group grid grid-cols-1 md:grid-cols-12 gap-4 items-center p-4 bg-white dark:bg-base-200 border border-gray-100 dark:border-gray-700 rounded-xl hover:shadow-md transition-all">
							
							{{-- 單號與時間 --}}
							<div class="md:col-span-3">
								<div class="font-mono font-bold text-primary">{{ $sale->invoice_number }}</div>
								<div class="text-xs text-gray-400 mt-1">{{ $sale->sold_at->format('Y-m-d H:i') }}</div>
							</div>

							{{-- 分店與通路 (顯示 Name) --}}
							<div class="md:col-span-3 flex flex-wrap gap-2">
								<x-badge :value="$sale->shop->name ?? '未指定分店'" class="badge-ghost badge-sm" />
								<x-badge :value="$sale->channel->name ?? '未知通路'" class="badge-outline badge-primary badge-sm" />
							</div>

							{{-- 金額區塊 (手機端會自動排列) --}}
							<div class="md:col-span-2 text-left md:text-right">
								<span class="text-xs text-gray-400 md:hidden block">買家實付：</span>
								<span class="font-mono font-semibold italic text-blue-600">
									NT$ {{ number_format($sale->customer_total, 0) }}
								</span>
							</div>

							<div class="md:col-span-2 text-left md:text-right">
								<span class="text-xs text-gray-400 md:hidden block">最終進帳：</span>
								<span class="font-mono font-bold text-emerald-600">
									NT$ {{ number_format($sale->final_net_amount, 0) }}
								</span>
							</div>

							{{-- 操作 --}}
							<div class="md:col-span-2 flex justify-end">
								<x-button icon="o-eye" link="{{ route('sales.index', ['search' => $sale->invoice_number]) }}" class="btn-circle btn-ghost btn-sm" />
							</div>
						</div>
					@empty
						<div class="text-center py-10 text-gray-400">
							目前尚無銷售紀錄
						</div>
					@endforelse
				</div>
			</x-card>
		</div>
	</div>	
</div>