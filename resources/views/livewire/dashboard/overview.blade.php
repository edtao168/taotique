{{-- resources/views/livewire/dashboard/overview.blade.php --}}
<div class="bg-base-100 text-base-content min-h-screen p-4 lg:p-6">
    <x-header title="系統概覽" subtitle="今日經營數據與趨勢分析">
        <x-slot:actions>
            <x-button label="新增銷售" icon="o-chart-bar" class="btn-primary" link="{{ route('sales.create') }}" />
            <x-button label="新增採購" icon="o-shopping-bag" :link="route('purchases.create')" />
        </x-slot:actions>
    </x-header>

    {{-- 統計卡片 --}}
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
        <x-stat title="今日營業額" value="NT$ {{ number_format($metrics->todaySales) }}" icon="o-sun" color="text-info" />
        <x-stat title="本月營業額" value="NT$ {{ number_format($metrics->monthSales) }}" icon="o-calendar" description="買家實付" />
        <x-stat
            title="本月淨營業額"
            value="NT$ {{ number_format($metrics->monthNetRevenue) }}"
            icon="o-currency-dollar"
            color="text-success"
            description="商家實收（未扣成本）"
        />
        <x-stat
            title="本月毛利"
            value="NT$ {{ number_format($metrics->monthGrossProfit) }}"
            icon="o-banknotes"
            color="text-primary"
            description="毛利率 {{ $metrics->monthGrossMarginRate }}%"
        />
        <x-stat title="庫存總額" value="NT$ {{ number_format($inventoryValue) }}" icon="o-circle-stack" />
        <x-stat title="庫存預警" value="{{ $lowStockCount }}" icon="o-exclamation-triangle" color="text-warning" />
    </div>

    {{-- 圖表區域 --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="shadow p-4 bg-base-100 border border-base-300 rounded-lg min-h-[300px]">
            <h3 class="text-lg font-bold mb-4 text-base-content">營業收入統計（最近12個月買家實付合計）</h3>
            <div class="h-[300px]">
                <canvas id="salesChart"></canvas>
            </div>
        </div>

        <div class="shadow p-4 bg-base-100 border border-base-300 rounded-lg min-h-[300px]">
            <h3 class="text-lg font-bold mb-4 text-base-content">實際收款統計（最近12個月最終訂單進帳）</h3>
            <div class="h-[300px]">
                <canvas id="profitChart"></canvas>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- ABC 分析區塊                                                  --}}
    {{-- ============================================================ --}}
    <div class="shadow p-4 bg-base-100 border border-base-300 rounded-lg mb-8">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-2">
                <h3 class="text-lg font-bold text-base-content">商品 ABC 分析</h3>
                <x-popover>
                    <x-slot:trigger>
                        <x-icon name="o-information-circle" class="w-4 h-4 text-base-content/40 cursor-help" />
                    </x-slot:trigger>
                    <x-slot:content class="max-w-xs text-xs">
                        <p class="font-bold mb-1">ABC 分級說明</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li><b>A 類</b>：累計貢獻 ≤ 70%（主力商品，確保不斷貨）</li>
                            <li><b>B 類</b>：70% ~ 90%（維持）</li>
                            <li><b>C 類</b>：> 90%（檢視是否淘汰或改接單進貨）</li>
                            <li class="pt-1 text-base-content/60">指標依「買家實付」加總計算</li>
                        </ul>
                    </x-slot:content>
                </x-popover>
            </div>

            <div class="flex gap-2">
                <x-select
                    wire:model.live="abcMetric"
                    :options="[
                        ['id' => 'revenue', 'name' => '依營收'],
                        ['id' => 'gross_profit', 'name' => '依毛利'],
                    ]"
                    option-value="id"
                    option-label="name"
                    class="select-sm w-32"
                />

                <x-select
                    wire:model.live="abcMonths"
                    :options="[
                        ['id' => 1, 'name' => '近 1 個月'],
                        ['id' => 3, 'name' => '近 3 個月'],
                        ['id' => 6, 'name' => '近 6 個月'],
                        ['id' => 12, 'name' => '近 12 個月'],
                    ]"
                    option-value="id"
                    option-label="name"
                    class="select-sm w-32"
                />
            </div>
        </div>

        @php
            $abcItems = $this->abcItems;
            $abcSummary = $this->abcSummary;

            // 靜態映射表：避免 Tailwind JIT 掃不到動態拼接類別
            $gradeCardStyles = [
                'A' => 'bg-success/10 border-success/30',
                'B' => 'bg-warning/10 border-warning/30',
                'C' => 'bg-error/10 border-error/30',
            ];
            $gradeTextStyles = [
                'A' => 'text-success',
                'B' => 'text-warning',
                'C' => 'text-error',
            ];
            $gradeBadgeStyles = [
                'A' => 'badge-success',
                'B' => 'badge-warning',
                'C' => 'badge-error',
            ];
            $gradeBarStyles = [
                'A' => 'bg-success',
                'B' => 'bg-warning',
                'C' => 'bg-error',
            ];
        @endphp

        @if($abcItems->isEmpty())
            <div class="text-center py-10 text-base-content/40">
                此期間尚無銷售資料
            </div>
        @else
            {{-- A / B / C 摘要卡 --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
                @foreach(['A', 'B', 'C'] as $grade)
                    <div class="border rounded-lg p-3 {{ $gradeCardStyles[$grade] }}">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-2xl font-black {{ $gradeTextStyles[$grade] }}">{{ $grade }}</span>
                            <span class="text-xs text-base-content/60">{{ $abcSummary[$grade]['count'] }} 項</span>
                        </div>
                        <div class="text-sm font-bold text-base-content">
                            NT$ {{ number_format($abcSummary[$grade]['total'], 0) }}
                        </div>
                        <div class="text-xs text-base-content/60">
                            佔比 {{ $abcSummary[$grade]['share'] }}%
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- 商品明細表 --}}
            <div class="overflow-x-auto">
                <table class="table table-sm w-full">
                    <thead>
                        <tr class="text-xs text-base-content/60">
                            <th class="w-16">級別</th>
                            <th>商品</th>
                            <th class="text-right w-32">營收</th>
                            <th class="text-right w-20">佔比</th>
                            <th class="text-right w-24">累計佔比</th>
                            <th class="w-32">貢獻度</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($abcItems->take(20) as $item)
                            <tr class="hover:bg-base-200">
                                <td>
                                    <x-badge
                                        :value="$item->grade"
                                        class="{{ $gradeBadgeStyles[$item->grade] }} badge-sm font-black"
                                    />
                                </td>
                                <td class="text-sm text-base-content">{{ $item->productName }}</td>
                                <td class="text-right font-mono text-sm text-base-content">
                                    NT$ {{ number_format($item->revenue, 0) }}
                                </td>
                                <td class="text-right font-mono text-xs text-base-content/60">
                                    {{ $item->revenueShare }}%
                                </td>
                                <td class="text-right font-mono text-xs text-base-content/60">
                                    {{ $item->cumulativeShare }}%
                                </td>
                                <td>
                                    <div class="w-full bg-base-300 rounded-full h-2">
                                        <div
                                            class="h-2 rounded-full {{ $gradeBarStyles[$item->grade] }}"
                                            style="width: {{ min($item->revenueShare * 3, 100) }}%"
                                        ></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($abcItems->count() > 20)
                <div class="text-center text-xs text-base-content/40 mt-3">
                    僅顯示前 20 名，共 {{ $abcItems->count() }} 項商品
                </div>
            @endif

            @if($abcSummary['C']['count'] > 0)
                <div class="mt-4 p-3 bg-warning/10 border border-warning/30 rounded-lg text-xs text-warning">
                    <x-icon name="o-exclamation-triangle" class="w-4 h-4 inline" />
                    C 類商品共 {{ $abcSummary['C']['count'] }} 項，貢獻僅 {{ $abcSummary['C']['share'] }}% 營收。
                    建議檢視是否淘汰、降價促銷，或改為「接單後進貨」以減少庫存壓力。
                </div>
            @endif
        @endif
    </div>

    {{-- Chart.js 初始化 --}}
    @script
    <script>
        let salesChartInstance = null;
        let profitChartInstance = null;

        function initDashboardCharts() {
            if (typeof Chart === 'undefined') {
                setTimeout(initDashboardCharts, 100);
                return;
            }

            const salesEl = document.getElementById('salesChart');
            const profitEl = document.getElementById('profitChart');

            if (!salesEl || !profitEl) {
                setTimeout(initDashboardCharts, 100);
                return;
            }

            if (salesChartInstance) {
                salesChartInstance.destroy();
                salesChartInstance = null;
            }
            if (profitChartInstance) {
                profitChartInstance.destroy();
                profitChartInstance = null;
            }

            const labels = @json($monthlyData->pluck('month')->map(fn($m) => \Carbon\Carbon::parse($m . '-01')->format('Y/m')));
            const salesData = @json($monthlyData->pluck('sales'));
            const profitData = @json($monthlyData->pluck('profit'));

            // 讀取目前主題的文字顏色，讓圖表在 dark 模式下也能正確顯示
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const tickColor = isDark ? '#e5e7eb' : '#374151';
            const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';

            const sharedOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return 'NT$ ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: tickColor,
                            callback: function (value) {
                                return 'NT$ ' + value.toLocaleString();
                            }
                        },
                        grid: { color: gridColor }
                    },
                    x: {
                        ticks: {
                            color: tickColor,
                            maxRotation: 45,
                            minRotation: 45
                        },
                        grid: { color: gridColor }
                    }
                }
            };

            try {
                salesChartInstance = new Chart(salesEl.getContext('2d'), {
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
                    options: sharedOptions
                });

                profitChartInstance = new Chart(profitEl.getContext('2d'), {
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
                    options: sharedOptions
                });
            } catch (error) {
                console.error('圖表初始化失敗:', error);
            }
        }

        requestAnimationFrame(() => {
            initDashboardCharts();
        });

        Livewire.hook('morph.updated', ({ el, component }) => {
            if (component.name !== 'dashboard.overview') return;
            requestAnimationFrame(() => {
                initDashboardCharts();
            });
        });

        $wire.$on('$commit', () => {
            requestAnimationFrame(() => {
                initDashboardCharts();
            });
        });
    </script>
    @endscript

    {{-- ============================================================ --}}
    {{-- 時段熱度分析                                                  --}}
    {{-- ============================================================ --}}
    <div class="shadow p-4 bg-base-100 text-base-content border border-base-300 rounded-lg mb-8 w-full">
        {{-- 標題列 --}}
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-2">
                <h3 class="text-lg font-bold">時段熱度分析</h3>
                <x-popover>
                    <x-slot:trigger>
                        <x-icon name="o-information-circle" class="w-4 h-4 opacity-40 cursor-help" />
                    </x-slot:trigger>
                    <x-slot:content class="max-w-xs text-xs">
                        <p class="font-bold mb-1">時段熱度說明</p>
                        <p class="mb-2">依「銷售時間」統計每週 7 天 × 24 小時的分布。</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>顏色越深 → 該時段營收越高</li>
                            <li>用來決定「備貨節奏、人力安排、促銷時機」</li>
                            <li>只計入「已審核 / 已結案 / 已結算」的銷售單</li>
                        </ul>
                    </x-slot:content>
                </x-popover>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-select
                    wire:model.live="heatmapMode"
                    :options="[
                        ['id' => 'revenue', 'name' => '依營收'],
                        ['id' => 'order_count', 'name' => '依訂單數'],
                    ]"
                    option-value="id"
                    option-label="name"
                    class="select-sm w-28"
                />

                <x-select
                    wire:model.live="heatmapMonths"
                    :options="[
                        ['id' => 1, 'name' => '近 1 個月'],
                        ['id' => 3, 'name' => '近 3 個月'],
                        ['id' => 6, 'name' => '近 6 個月'],
                        ['id' => 12, 'name' => '近 12 個月'],
                    ]"
                    option-value="id"
                    option-label="name"
                    class="select-sm w-32"
                />

                <span wire:loading wire:target="heatmapMonths,heatmapMode" class="text-xs opacity-40">
                    <x-icon name="o-arrow-path" class="w-4 h-4 animate-spin inline" />
                </span>
            </div>
        </div>

        @php
            $heatmap = $this->heatmapMatrix;
            $matrix = $heatmap['matrix'];
            $maxValue = $heatmap['maxValue'];
            $topSlots = $this->heatmapTopSlots;

            $dayNamesFull = ['週日', '週一', '週二', '週三', '週四', '週五', '週六'];

            // ============================================================
            // 熱度色階：橙色 → 紅色漸變（5 級）
            // 使用靜態映射表，避免 Tailwind JIT 掃不到動態拼接類別
            // ============================================================
            $heatLevels = [
                'bg-base-200',                  // 0：無資料
                'bg-orange-200 dark:bg-orange-900/50',   // 1：低
                'bg-orange-300 dark:bg-orange-700/70',   // 2
                'bg-orange-400 dark:bg-orange-600',      // 3
                'bg-orange-500 dark:bg-orange-500',      // 4
                'bg-orange-600 dark:bg-orange-400',      // 5
                'bg-red-500 dark:bg-red-500',            // 6
                'bg-red-600 dark:bg-red-600',            // 7
                'bg-red-700 dark:bg-red-700',            // 8：高
            ];

            $getCellClass = function ($value) use ($maxValue, $heatLevels) {
                if ($maxValue <= 0 || $value <= 0) {
                    return $heatLevels[0];
                }
                $ratio = $value / $maxValue;
                // 將 0~1 映射到 1~8 級
                $level = (int) ceil($ratio * 8);
                $level = max(1, min(8, $level));
                return $heatLevels[$level];
            };

            $getTextClass = function ($value) use ($maxValue) {
                if ($maxValue <= 0 || $value <= 0) {
                    return 'text-base-content/20';
                }
                $ratio = $value / $maxValue;
                // 高熱度用白字，低熱度用深色字
                return $ratio > 0.5 ? 'text-white' : 'text-gray-800 dark:text-gray-100';
            };

            $formatValue = function ($value) use ($heatmapMode) {
                return $heatmapMode === 'revenue'
                    ? 'NT$ ' . number_format($value, 0)
                    : $value . ' 筆';
            };

            $mobileTopSlots = collect($topSlots)->take(10);
        @endphp

        @if($maxValue <= 0)
            <div class="text-center py-10 text-base-content/40">
                此期間尚無銷售資料
            </div>
        @else
            {{-- ============================================================ --}}
            {{-- 桌機版：7×24 熱度圖（改用 flex 排版，取代 grid-cols-24）     --}}
            {{-- ============================================================ --}}
            <div class="hidden lg:block w-full">
                <div class="overflow-x-auto w-full pb-2">
                    <div class="min-w-[860px]">
                        {{-- 小時標頭 --}}
                        <div class="flex items-center gap-1 mb-1">
                            <div class="w-12 text-[10px] text-base-content/40 text-center font-bold shrink-0">時段</div>
                            <div class="flex-1 flex gap-1">
                                @for($h = 0; $h < 24; $h++)
                                    <div class="flex-1 min-w-[24px] text-[10px] text-base-content/40 text-center font-mono">
                                        {{ str_pad($h, 2, '0', STR_PAD_LEFT) }}
                                    </div>
                                @endfor
                            </div>
                        </div>

                        {{-- 7 天資料列 --}}
                        @for($d = 0; $d < 7; $d++)
                            <div class="flex items-center gap-1 mb-1">
                                <div class="w-12 text-xs font-bold text-base-content/70 text-center shrink-0">
                                    {{ $dayNamesFull[$d] }}
                                </div>

                                {{-- ✅ 改用 flex，每個格子 flex-1，跨環境穩定 --}}
                                <div class="flex-1 flex gap-1">
                                    @for($h = 0; $h < 24; $h++)
                                        @php
                                            $value = $matrix[$d][$h] ?? 0;
                                            $cellClass = $getCellClass($value);
                                            $textClass = $getTextClass($value);
                                            $tooltip = $dayNamesFull[$d] . ' ' . str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                                            $tooltip .= ' ｜ ' . ($value > 0 ? $formatValue($value) : '無銷售');
                                        @endphp
                                        <div
                                            class="flex-1 min-w-[24px] {{ $cellClass }} {{ $textClass }} h-8 rounded flex items-center justify-center text-[9px] font-mono cursor-pointer hover:ring-2 hover:ring-primary transition"
                                            title="{{ $tooltip }}"
                                        >
                                            @if($value > 0 && $value == $maxValue)
                                                ★
                                            @endif
                                        </div>
                                    @endfor
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>

                {{-- 圖例 --}}
                <div class="flex items-center justify-center gap-2 mt-4 text-[10px] text-base-content/50">
                    <span>低</span>
                    <div class="w-4 h-4 bg-base-200 rounded border border-base-300"></div>
                    <div class="w-4 h-4 bg-orange-200 dark:bg-orange-900/50 rounded"></div>
                    <div class="w-4 h-4 bg-orange-300 dark:bg-orange-700/70 rounded"></div>
                    <div class="w-4 h-4 bg-orange-400 dark:bg-orange-600 rounded"></div>
                    <div class="w-4 h-4 bg-orange-500 dark:bg-orange-500 rounded"></div>
                    <div class="w-4 h-4 bg-orange-600 dark:bg-orange-400 rounded"></div>
                    <div class="w-4 h-4 bg-red-500 dark:bg-red-500 rounded"></div>
                    <div class="w-4 h-4 bg-red-600 dark:bg-red-600 rounded"></div>
                    <div class="w-4 h-4 bg-red-700 dark:bg-red-700 rounded"></div>
                    <span>高</span>
                </div>

                {{-- 桌機 Top 3 --}}
                @if(!empty($topSlots))
                    <div class="mt-6 grid grid-cols-3 gap-3">
                        @foreach($topSlots as $index => $slot)
                            <div class="border border-base-300 rounded-lg p-3 bg-base-200/40">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-lg font-black text-warning">#{{ $index + 1 }}</span>
                                    <span class="text-xs opacity-60">最佳時段</span>
                                </div>
                                <p class="text-sm font-bold">
                                    {{ $dayNamesFull[$slot['day']] }} {{ str_pad($slot['hour'], 2, '0', STR_PAD_LEFT) }}:00
                                </p>
                                <p class="text-xs opacity-60 mt-1">
                                    @if($heatmapMode === 'revenue')
                                        營收 NT$ {{ number_format($slot['revenue'], 0) }}
                                    @else
                                        {{ $slot['orderCount'] }} 筆訂單
                                    @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ============================================================ --}}
            {{-- 手機版：Top 時段列表                                           --}}
            {{-- ============================================================ --}}
            <div class="block lg:hidden">
                @if(!empty($topSlots))
                    <div class="grid grid-cols-1 gap-3 mb-4">
                        @foreach(collect($topSlots)->take(3) as $index => $slot)
                            <div class="border border-base-300 rounded-lg p-3 bg-base-200/40 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl font-black text-warning">#{{ $index + 1 }}</span>
                                    <div>
                                        <p class="text-sm font-bold">
                                            {{ $dayNamesFull[$slot['day']] }} {{ str_pad($slot['hour'], 2, '0', STR_PAD_LEFT) }}:00
                                        </p>
                                        <p class="text-xs opacity-60">
                                            @if($heatmapMode === 'revenue')
                                                營收 NT$ {{ number_format($slot['revenue'], 0) }}
                                            @else
                                                {{ $slot['orderCount'] }} 筆訂單
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <div class="w-16 bg-base-300 rounded-full h-2">
                                    <div
                                        class="h-2 rounded-full bg-warning"
                                        style="width: {{ $maxValue > 0 ? min(($slot['revenue'] / $maxValue) * 100, 100) : 0 }}%"
                                    ></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="border-t border-base-300 pt-4">
                    <p class="text-xs font-bold opacity-60 mb-3">熱門時段 Top 10</p>
                    <div class="space-y-2">
                        @foreach($mobileTopSlots as $index => $slot)
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-mono opacity-40 w-6 text-right">#{{ $index + 1 }}</span>
                                <span class="text-xs font-bold w-24">
                                    {{ $dayNamesFull[$slot['day']] }} {{ str_pad($slot['hour'], 2, '0', STR_PAD_LEFT) }}:00
                                </span>
                                <div class="flex-1 bg-base-200 rounded-full h-2">
                                    <div
                                        class="h-2 rounded-full bg-warning"
                                        style="width: {{ $maxValue > 0 ? min(($slot['revenue'] / $maxValue) * 100, 100) : 0 }}%"
                                    ></div>
                                </div>
                                <span class="text-xs font-mono opacity-60 w-20 text-right">
                                    @if($heatmapMode === 'revenue')
                                        NT$ {{ number_format($slot['revenue'], 0) }}
                                    @else
                                        {{ $slot['orderCount'] }} 筆
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- 建議區塊 --}}
            <div class="mt-4 p-3 bg-base-200/60 border border-base-300 rounded-lg text-xs opacity-80">
                <x-icon name="o-light-bulb" class="w-4 h-4 inline" />
                建議：在最佳時段前備妥熱銷商品、安排人力，並考慮於此時段推播或做促銷。
            </div>
        @endif
    </div>

    {{-- 最近銷貨記錄 --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-3">
            <x-card title="最近銷售" subtitle="最新的 5 筆交易紀錄" shadow separator>
                <x-slot:actions>
                    <x-button label="查看全部" icon="o-list-bullet" link="{{ route('sales.index') }}" class="btn-ghost btn-sm" />
                </x-slot:actions>

                <div class="space-y-4">
                    {{-- 列表標頭 --}}
                    <div class="hidden md:grid grid-cols-12 gap-4 px-4 py-2 text-xs font-bold text-base-content/60 uppercase tracking-wider">
                        <div class="col-span-3">單號 / 日期</div>
                        <div class="col-span-3">歸屬分店 / 通路</div>
                        <div class="col-span-2 text-right">買家實付</div>
                        <div class="col-span-2 text-right">最終進帳</div>
                        <div class="col-span-2 text-center">操作者</div>
                    </div>

                    {{-- 銷售項目卡片 --}}
                    @forelse($recentSales as $sale)
                        <div class="group grid grid-cols-1 md:grid-cols-12 gap-4 items-center p-4 bg-base-100 border border-base-300 rounded-xl hover:shadow-md transition-all">
                            <div class="md:col-span-3">
                                <div class="font-mono font-bold text-primary">{{ $sale->invoice_number }}</div>
                                <div class="text-xs text-base-content/50 mt-1">{{ $sale->sold_at->format('Y-m-d H:i') }}</div>
                            </div>

                            <div class="md:col-span-3 flex flex-wrap gap-2">
                                <x-badge :value="$sale->shop->name ?? '未指定分店'" class="badge-ghost badge-sm" />
                                <x-badge :value="$sale->channel->name ?? '未知通路'" class="badge-outline badge-primary badge-sm" />
                            </div>

                            <div class="md:col-span-2 text-left md:text-right">
                                <span class="text-xs text-base-content/50 md:hidden block">買家實付：</span>
                                <span class="font-mono font-semibold italic text-info">
                                    NT$ {{ number_format($sale->customer_total, 0) }}
                                </span>
                            </div>

                            <div class="md:col-span-2 text-left md:text-right">
                                <span class="text-xs text-base-content/50 md:hidden block">最終進帳：</span>
                                <span class="font-mono font-bold text-success">
                                    NT$ {{ number_format($sale->final_net_amount, 0) }}
                                </span>
                            </div>

                            <div class="md:col-span-2 flex items-center justify-center">
                                <span class="text-xs text-base-content/50 md:hidden block">操作者：</span>
                                <span class="text-sm font-medium text-base-content/80">
                                    {{ $sale->user->name ?? '系統' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-10 text-base-content/40">
                            目前尚無銷售紀錄
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>
</div>