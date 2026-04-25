<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Dashboard
use App\Livewire\Dashboard\Overview;


// 採購進貨 (Purchases)
use App\Livewire\Suppliers\Index as SupplierIndex;
use App\Livewire\Purchases\Index as PurchaseIndex;
use App\Livewire\Purchases\Create as PurchaseCreate;
use App\Livewire\Purchases\Returns\ReturnCreate as PurchaseReturnCreate;
use App\Livewire\Purchases\Returns\ReturnIndex as PurchaseReturnIndex;

// 銷售模組
use App\Livewire\Customers\Index as CustomerIndex;
use App\Livewire\Sales\Index as SalesIndex;
use App\Livewire\Sales\Create as SalesCreate;
use App\Livewire\Sales\Returns\ReturnCreate;
use App\Livewire\Sales\Returns\ReturnIndex;

// 庫存與調撥 (Inventories)
use App\Livewire\Inventories\Index as InventoryIndex;
use App\Livewire\Inventories\Transfers;
use App\Livewire\Inventories\Stocktakes;
use App\Livewire\Inventories\Movements;
use App\Livewire\Conversions\Index as ConversionIndex;
use App\Livewire\Conversions\Create as ConversionCreate;

// 商品管理
use App\Livewire\Products\Index as ProductIndex;
use App\Livewire\Products\Create as ProductCreate;
use App\Livewire\Products\Show as ProductShow;
use App\Livewire\Products\Edit as ProductEdit;

// 系統設定 (Settings)
use App\Livewire\Settings\Warehouses\Index as WarehouseIndex;
use App\Livewire\Settings\Shops\Index as ShopIndex;
use App\Livewire\Settings\Partners\Index as PartnerIndex;
use App\Livewire\Settings\Users\UserManagement;
use App\Livewire\Settings\Categories\Index as CategoryIndex;
use App\Livewire\Settings\Materials\Index as MaterialIndex;
use App\Livewire\Settings\SystemSettings;
use App\Livewire\Settings\BackupIndex;

Route::middleware(['auth'])->group(function () {
    
    // --- Dashboard ---
    Route::get('/', Overview::class)->name('home');
    Route::get('/dashboard', Overview::class)->name('dashboard');

    // --- 採購進貨系統 (Purchases) ---
    Route::prefix('purchases')->name('purchases.')->group(function () {
		
		Route::get('/suppliers', SupplierIndex::class)->name('suppliers.index');
		Route::get('/', PurchaseIndex::class)->name('index');
		Route::get('/create', PurchaseCreate::class)->name('create');
		Route::get('/{purchase}/edit', PurchaseCreate::class)->name('edit');
		Route::prefix('returns')->name('returns.')->group(function () {
			Route::get('/', PurchaseReturnIndex::class)->name('index');
			Route::get('/create/{purchase}', PurchaseReturnCreate::class)->name('create');
		});
    });

    // --- 銷售模組 (Sales) ---
    Route::prefix('sales')->name('sales.')->group(function () {        
		Route::get('/overview', SalesIndex::class)->name('overview');    
		Route::get('/', SalesIndex::class)->name('index');
        Route::get('/create', SalesCreate::class)->name('create');        
		Route::get('/{sale}/edit', SalesCreate::class)->name('edit');
		Route::get('/customers', CustomerIndex::class)->name('customers.index');
		Route::prefix('returns')->name('returns.')->group(function () {
			Route::get('/', ReturnIndex::class)->name('index');
			Route::get('/create/{sale}', ReturnCreate::class)->name('create');
		});
    });

    // --- 庫存與調撥系統 (Inventories) ---
    Route::prefix('inventories')->name('inventories.')->group(function () {       
        Route::get('/', InventoryIndex::class)->name('index');
		Route::get('/transfers', Transfers::class)->name('transfers');
		Route::get('/stocktakes', Stocktakes::class)->name('stocktakes');
        Route::get('/movements', Movements::class)->name('movements');
		// --- 拆裝組合作業 ---
        Route::get('/conversions', ConversionIndex::class)->name('conversions.index');
        Route::get('/conversions/create', ConversionCreate::class)->name('conversions.create');        
		Route::get('/conversions/{conversion}/edit', ConversionCreate::class)->name('conversions.edit');
    });
	
    // --- 商品管理 (Products) ---
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', ProductIndex::class)->name('index');    
        Route::get('/create', ProductCreate::class)->name('create');
        Route::get('/{product}', ProductShow::class)->name('show');
        Route::get('/{product}/edit', ProductEdit::class)->name('edit');
    });
	
    // --- 系統設定 (Settings) ---
    Route::prefix('settings')->group(function () {
        Route::get('/categories', CategoryIndex::class)->name('categories.index');
		Route::get('/materials', MaterialIndex::class)->name('materials.index');
		Route::get('/warehouses', WarehouseIndex::class)->name('warehouses.index');
		Route::get('/shops', ShopIndex::class)->name('shops.index');        
        Route::get('/partners', PartnerIndex::class)->name('partners.index');
        Route::get('/users', UserManagement::class)->name('users.index');
        // 直接將 Route 指向 Livewire Component，完全跳過 Controller
		Route::get('/', SystemSettings::class)->name('settings.system')->middleware(['auth']);
    });
	
	// [本地操作] routes/web.php
	Route::get('/settings/backup', BackupIndex::class)->name('settings.backup')->middleware('auth');
});