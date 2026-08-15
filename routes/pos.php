<?php

use App\Http\Controllers\Auth\PosAuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\Pos\PosSaleController;
use App\Http\Controllers\Pos\PosShiftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cashier terminal
|--------------------------------------------------------------------------
|
| The terminal is reachable without any dashboard session. Operators sign in
| here on the dedicated `pos` guard, open a shift, and sell — a Kasir never
| holds a dashboard session at all.
|
*/

Route::prefix('pos')->name('pos.')->group(function () {

    // --- Terminal sign-in ------------------------------------------------
    Route::middleware('guest:pos')->group(function () {
        Route::get('/login', [PosAuthController::class, 'show'])->name('login');
        Route::post('/login', [PosAuthController::class, 'login'])
            ->middleware('throttle:15,1')->name('login.attempt');
    });

    Route::post('/logout', [PosAuthController::class, 'logout'])->name('logout');

    Route::middleware('pos.auth')->group(function () {

        // --- Cash drawer session -----------------------------------------
        Route::get('/shift/open', [PosShiftController::class, 'showOpen'])->name('shift.open');
        Route::post('/shift/open', [PosShiftController::class, 'open'])->name('shift.open.store');
        Route::get('/shift/close', [PosShiftController::class, 'showClose'])->name('shift.close');
        Route::post('/shift/close', [PosShiftController::class, 'close'])->name('shift.close.store');
        Route::get('/shift/{shift}/report', [PosShiftController::class, 'report'])->name('shift.report');

        // --- Selling ------------------------------------------------------
        Route::middleware('pos.shift')->group(function () {
            Route::get('/', [PosController::class, 'index'])->name('index');
            Route::get('/products', [PosController::class, 'products'])->name('products');
            Route::get('/lookup', [PosController::class, 'lookup'])->name('lookup');
            Route::get('/customers', [PosController::class, 'customers'])->name('customers');
            Route::post('/customers', [PosController::class, 'storeCustomer'])->name('customers.store');

            Route::post('/checkout', [PosSaleController::class, 'checkout'])->name('checkout');

            Route::post('/hold', [PosSaleController::class, 'hold'])->name('hold');
            Route::get('/hold', [PosSaleController::class, 'heldList'])->name('hold.list');
            Route::delete('/hold/{heldOrder}', [PosSaleController::class, 'releaseHold'])->name('hold.release');
        });

        // --- Own account ---------------------------------------------------
        // Deliberately outside the open-shift guard: a cashier must be able
        // to change their PIN before the drawer is opened.
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
        Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.destroy');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::put('/profile/pin', [ProfileController::class, 'updatePin'])->name('profile.pin');

        // --- After the sale ------------------------------------------------
        Route::get('/receipt/{sale}', [PosSaleController::class, 'receipt'])->name('receipt');
        Route::get('/history', [PosSaleController::class, 'history'])->name('history');
        Route::post('/void/{sale}', [PosSaleController::class, 'void'])->name('void');
    });
});
