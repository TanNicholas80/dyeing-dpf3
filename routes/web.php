<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MesinController;
use App\Http\Controllers\ProsesController;
use App\Http\Controllers\UserController;


Route::get('/', [AuthController::class, 'login'])->name('login');
Route::post('/login-proses', [AuthController::class, 'login_proses'])->name('login-proses');

Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');

    Route::get('/profile', [UserController::class, 'editProfile'])->name('user.profile');
    Route::post('/profile/update', [UserController::class, 'updateProfile'])->name('user.profile.update');

    Route::middleware('role:super_admin')->group(function () {
        Route::get('/user', [UserController::class, 'index'])->name('user.index');
        Route::get('/user/create', [UserController::class, 'create'])->name('user.create');
        Route::post('/user/store', [UserController::class, 'store'])->name('user.store');
        Route::get('/user/edit/{id}', [UserController::class, 'edit'])->name('user.edit');
        Route::post('/user/update/{id}', [UserController::class, 'update'])->name('user.update');
        Route::delete('/user/delete/{id}', [UserController::class, 'destroy'])->name('user.delete');

        Route::resource('mesin', MesinController::class);
        Route::post('/mesin/{mesin}/toggle-status', [MesinController::class, 'toggleStatus'])->name('mesin.toggleStatus');
        Route::get('/mesin/{mesin}/status', [MesinController::class, 'status'])->name('mesin.status');

        Route::resource('proses', ProsesController::class);
    });

    // Barcode proses (semua user login bisa akses)
    Route::post('/proses/{id}/barcode/kain', [ProsesController::class, 'barcodeKain'])->name('proses.barcode.kain');
    Route::post('/proses/{id}/barcode/la', [ProsesController::class, 'barcodeLa'])->name('proses.barcode.la');
    Route::post('/proses/{id}/barcode/aux', [ProsesController::class, 'barcodeAux'])->name('proses.barcode.aux');
});

// Tambahkan di luar middleware auth agar bisa diakses select2
Route::post('/api/proxy-op', [App\Http\Controllers\ProsesController::class, 'proxyOpSearch']);
