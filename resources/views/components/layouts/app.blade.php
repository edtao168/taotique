<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="song_dynasty">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="csrf-token" content="{{ csrf_token() }}">
		<title>{{ config('app.name', '陶老闆IMS') }}</title>
		
		<!-- Fonts -->
		<link rel="preconnect" href="https://fonts.bunny.net">
		<link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
		
		<!-- Vite -->
		@vite(['resources/css/app.css', 'resources/js/app.js'])
		
		@livewireStyles
		
		<!-- Chart.js -->
		<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>	
		
		@stack('styles')
		<style>
			/* 確保側邊欄在手機上永遠在最上層 */
			.drawer-side {
				z-index: 100 !important;
			}
		</style>
	</head>
	<body class="min-h-screen font-sans antialiased bg-base-200/50 dark:bg-base-200">
	<x-toast />
	<div x-data="{ mainDrawer: false }">
		{{-- Mary UI 的主佈局組件 --}}
		<x-main full-width>
			{{-- 側邊欄 (Sidebar) --}}
			<x-slot:sidebar drawer="main-drawer" id="main-drawer" collapsible class="bg-base-100 w-[80vw] lg:w-80">

				{{-- 系統標誌/Logo --}}
				<div class="p-5 pt-3 flex items-center gap-2">                
					<img src="{{ asset('logo.png') }}" class="w-8" />
					<div class="font-bold text-xl tracking-tight">陶老闆
						<span class="text-xs text-gray-500 italic truncate max-w-xs">進銷存系統</span>
						<span class="text-xs text-gray-500 italic truncate max-w-xs block sm:inline">第一版</span>
					</div>
				</div>

				{{-- 導覽選單 --}}			
				<x-menu activate-by-route>
					{{-- 0. 核心入口 --}}
					<x-menu-item title="系統概覽" icon="o-home" link="/dashboard" />

					<x-menu-separator />

					{{-- 1. 採購進貨系統 --}}
					<x-menu-sub title="採購進貨系統" icon="o-shopping-bag">
						<x-menu-item title="新增採購入庫" icon="o-plus-circle" :link="route('purchases.create')" />
						<x-menu-item title="採購進貨紀錄" icon="o-clipboard-document-list" :link="route('purchases.index')" />
						<x-menu-item title="採購退回紀錄" icon="o-arrow-path" :link="route('purchases.returns.index')" />
						<x-menu-item title="供應商管理" icon="o-user-group" :link="route('purchases.suppliers.index')" />
						{{-- 業務報表嵌入 --}}
						<x-menu-item title="採購統計報表" icon="o-chart-bar" link="#" class="text-sm opacity-80" />
					</x-menu-sub>

					{{-- 2. 銷貨管理系統 --}}
					<x-menu-sub title="銷貨管理系統" icon="o-shopping-cart">
						<x-menu-item title="新增銷貨單" icon="o-plus-circle" :link="route('sales.create')" />
						<x-menu-item title="銷售數據概況" icon="o-chart-bar" :link="route('sales.index')" />
						<x-menu-item title="銷貨退回紀錄" icon="o-arrow-path" :link="route('sales.returns.index')" />
						<x-menu-item title="客戶管理" icon="o-users" :link="route('sales.customers.index')" />
						{{-- 業務報表嵌入 --}}
						<x-menu-item title="銷售業績分析" icon="o-chart-pie" link="#" class="text-sm opacity-80" />
					</x-menu-sub>

					{{-- 3. 庫存管理系統 --}}
					<x-menu-sub title="庫存管理" icon="o-archive-box">
						<x-menu-item title="庫存總覽" icon="o-magnifying-glass" :link="route('inventories.index')" />
						<x-menu-item title="倉庫調撥" icon="o-arrows-right-left" :link="route('inventories.transfers')" />
						<x-menu-item title="拆裝組合作業" icon="o-beaker" :link="route('inventories.conversions.create')" />
		<x-menu-item title="拆裝作業紀錄" icon="o-list-bullet" :link="route('inventories.conversions.index')" />
						<x-menu-item title="庫存盤點" icon="o-check-badge" :link="route('inventories.stocktakes')" />
						<x-menu-item title="異動流水帳" icon="o-clock" :link="route('inventories.movements')" />
					</x-menu-sub>

					<x-menu-separator />
					
					<x-menu-sub title="帳務管理" icon="o-currency-dollar">
						{{-- 使用冒號綁定 PHP route() 函數 --}}
						<x-menu-item title="手動記帳" icon="o-pencil-square" :link="route('accountings.journals.create')" />
						<x-menu-item title="日記帳流水" icon="o-book-open" :link="route('accountings.journals.index')" />
						<x-menu-item title="會計科目表" icon="o-list-bullet" :link="route('accounts.index')" />
						<x-menu-item title="自動過帳規則" icon="o-adjustments-horizontal" :link="route('accounting_rules.index')" />
					</x-menu-sub>
					{{-- 
					<x-menu-separator />

					<x-menu-sub title="私人記帳" icon="o-user">
						<x-menu-item title="記一筆" icon="o-pencil-square" :link="route('personal.ledgers.create')" />
						<x-menu-item title="流水帳" icon="o-book-open" :link="route('personal.ledgers.index')" />
					</x-menu-sub>
						--}}
					<x-menu-separator />

					{{-- 4. 基本資料設定 --}}
					<x-menu-sub title="基本資料設定" icon="o-cog-6-tooth">
						<x-menu-item title="商品資料管理" icon="o-cube" :link="route('products.index')" />
						<x-menu-item title="商品分類定義" icon="o-tag" :link="route('categories.index')" />
						<x-menu-item title="材質分類定義" icon="o-photo" :link="route('materials.index')" />
						<x-menu-item title="營業點管理" icon="o-map-pin" :link="route('shops.index')" />
						<x-menu-item title="庫別管理" icon="o-building-office" :link="route('warehouses.index')" />
						<x-menu-item title="通路管理" icon="o-computer-desktop" :link="route('channels.index')" />
						<x-menu-separator />
						{{-- 人事與權限分開 --}}
						<x-menu-item title="夥伴資料維護" icon="o-identification" :link="route('partners.index')" />
						{{-- 系統帳號權限：極度敏感，僅限管理員 --}}
						@can('manage_users')
							<x-menu-item title="系統帳號權限" icon="o-shield-check" :link="route('users.index')" />
						@endcan
						<x-menu-separator />
						<x-menu-item title="系統參數設定" icon="o-adjustments-horizontal" link="/settings" />
						<x-menu-item title="系統備份" icon="o-cloud-arrow-up" link="{{ route('settings.backup') }}" />
					</x-menu-sub>

					<x-menu-separator />
					<x-menu-item title="關於本系統" icon="o-information-circle" :link="route('about')" />
					
					<x-menu-item title="登出系統" icon="o-power" no-wire-navigate onclick="event.preventDefault(); document.getElementById('logout-form').submit();" />
				</x-menu>

						
			</x-slot:sidebar>

			{{-- 主要內容區 --}}
			<x-slot:content>
				{{-- 行動端漢堡選單按鈕 --}}
				<div class="lg:hidden mb-5">				
					<label for="main-drawer" class="btn btn-ghost lg:hidden">
						<x-icon name="o-bars-3" class="w-6 h-6" />
					</label>
				</div>
				
				{{ $slot }}
			</x-slot:content>
		</x-main>    
		</div>
		<form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
			@csrf
		</form>	
		@livewireScripts
		
		<script>
			document.addEventListener('focusin', (e) => {
				// 針對 type="number" 或含有特定 class 的 input 進行處理
				if (e.target.tagName === 'INPUT' && (e.target.type === 'number' || e.target.classList.contains('font-mono'))) {
					// 當值為 0 時清空，方便使用者直接輸入
					if (parseFloat(e.target.value) === 0) {
						e.target.value = '';
					}
				}
			});

			document.addEventListener('focusout', (e) => {
				if (e.target.tagName === 'INPUT' && (e.target.type === 'number' || e.target.classList.contains('font-mono'))) {
					// 當使用者離開且未輸入內容時，補回 0
					if (e.target.value === '') {
						e.target.value = '0';
						
						// 手動觸發 input 事件以確保 Livewire 抓取到「補回 0」的變更
						e.target.dispatchEvent(new Event('input'));
					}
				}
			});
		</script>
	</body>
</html>