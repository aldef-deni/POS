<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// POS routes (accessible without dashboard login)
use App\Http\Controllers\PosAuthController;
use App\Http\Controllers\PosController;

Route::get('/pos/login', [PosAuthController::class, 'showLogin'])->name('pos.login');
Route::post('/pos/login', [PosAuthController::class, 'login'])->name('pos.login.post');
Route::get('/pos/logout', [PosAuthController::class, 'logout'])->name('pos.logout');

Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
Route::post('/pos/print', [PosController::class, 'printReceipt'])->name('pos.print');
