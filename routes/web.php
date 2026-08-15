<?php

use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Dashboard\ActivityLogController;
use App\Http\Controllers\Dashboard\CategoryController;
use App\Http\Controllers\Dashboard\CustomerController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\OutletController;
use App\Http\Controllers\Dashboard\ProductController;
use App\Http\Controllers\Dashboard\ReportController;
use App\Http\Controllers\Dashboard\SaleController;
use App\Http\Controllers\Dashboard\SettingsController;
use App\Http\Controllers\Dashboard\ShiftController;
use App\Http\Controllers\Dashboard\StockController;
use App\Http\Controllers\Dashboard\UserController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('landing');

/*
| Dashboard sign-in. Separate from the terminal sign-in so the two sessions
| never overlap.
*/
Route::middleware('guest:web')->group(function () {
    Route::get('/admin/login', [AdminAuthController::class, 'show'])->name('admin.login');
    Route::post('/admin/login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('admin.login.attempt');
});

Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

/*
| Scannable product marks. Available to any signed-in operator because the
| terminal renders labels too.
*/
Route::prefix('media')->name('media.')->group(function () {
    Route::get('/barcode/{product}', [MediaController::class, 'barcode'])->name('barcode');
    Route::get('/qr/{product}', [MediaController::class, 'qr'])->name('qr');
});

/*
| Management dashboard — Owner and Supervisor only.
*/
Route::middleware('dashboard.auth')->prefix('dashboard')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // --- Catalogue -----------------------------------------------------
    Route::middleware('can.do:product.view')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/labels', [ProductController::class, 'labels'])->name('products.labels');
        Route::get('/products/{product}', [ProductController::class, 'show'])
            ->whereNumber('product')->name('products.show');
    });

    Route::middleware('can.do:product.create')->group(function () {
        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    });

    Route::middleware('can.do:product.update')->group(function () {
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::post('/products/{product}/regenerate-code', [ProductController::class, 'regenerateCode'])
            ->name('products.regenerate');
    });

    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('can.do:product.delete')->name('products.destroy');

    Route::middleware('can.do:category.manage')->group(function () {
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    });

    // --- Inventory -----------------------------------------------------
    Route::middleware('can.do:stock.view')->group(function () {
        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('/stock/opname', [StockController::class, 'opname'])->name('stock.opname');
    });

    Route::middleware('can.do:stock.adjust')->group(function () {
        Route::post('/stock/adjust', [StockController::class, 'adjust'])->name('stock.adjust');
        Route::post('/stock/opname', [StockController::class, 'storeOpname'])->name('stock.opname.store');
    });

    // --- Customers -----------------------------------------------------
    Route::middleware('can.do:customer.manage')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    });

    // --- Transactions --------------------------------------------------
    Route::middleware('can.do:sale.view')->group(function () {
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('/sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
        Route::get('/sales/{sale}/invoice-pdf', [SaleController::class, 'invoicePdf'])->name('sales.invoice');
    });

    Route::post('/sales/{sale}/void', [SaleController::class, 'void'])
        ->middleware('can.do:sale.void')->name('sales.void');

    // --- Shifts --------------------------------------------------------
    Route::middleware('can.do:shift.view.all')->group(function () {
        Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
        Route::get('/shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');
        Route::get('/shifts/{shift}/pdf', [ShiftController::class, 'pdf'])->name('shifts.pdf');
    });

    // --- Reports -------------------------------------------------------
    Route::middleware('can.do:report.view')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/{report}', [ReportController::class, 'show'])->name('show')
            ->where('report', 'summary|sales|products|categories|cashiers|payments|profit|inventory|shifts|voids');
        Route::get('/{report}/export/{format}', [ReportController::class, 'export'])->name('export')
            ->where('report', 'summary|sales|products|categories|cashiers|payments|profit|inventory|shifts|voids')
            ->where('format', 'pdf|csv');
    });

    // --- Own account ---------------------------------------------------
    // No permission gate: everyone who reaches the dashboard owns a profile.
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.destroy');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/pin', [ProfileController::class, 'updatePin'])->name('profile.pin');

    // --- Administration ------------------------------------------------
    // --- Branches ------------------------------------------------------
    // Switching is available to anyone not pinned to a single outlet; the
    // controller re-checks that, so an assigned operator cannot roam.
    Route::post('/outlet-switch', [OutletController::class, 'switch'])->name('outlets.switch');

    Route::middleware('can.do:outlet.manage')->group(function () {
        Route::get('/outlets', [OutletController::class, 'index'])->name('outlets.index');
        Route::post('/outlets', [OutletController::class, 'store'])->name('outlets.store');
        Route::put('/outlets/{outlet}', [OutletController::class, 'update'])->name('outlets.update');
        Route::delete('/outlets/{outlet}', [OutletController::class, 'destroy'])->name('outlets.destroy');
    });

    Route::middleware('can.do:user.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::middleware('can.do:settings.manage')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings/store', [SettingsController::class, 'updateStore'])->name('settings.store');
        Route::put('/settings/receipt', [SettingsController::class, 'updateReceipt'])->name('settings.receipt');
        Route::put('/settings/sku', [SettingsController::class, 'updateSku'])->name('settings.sku');
        Route::post('/settings/sku/preview', [SettingsController::class, 'previewSku'])->name('settings.sku.preview');
    });

    Route::get('/activity', [ActivityLogController::class, 'index'])
        ->middleware('can.do:audit.view')->name('activity.index');
});
