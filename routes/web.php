<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuxlController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MesinController;
use App\Http\Controllers\ProsesController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ApprovalController;


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

        Route::resource('mesin', MesinController::class)->except(['show']);
        Route::resource('proses', ProsesController::class)->except(['update', 'destroy']);
    });

    Route::get('/mesin/statuses', [MesinController::class, 'statuses'])->name('mesin.statuses');

    // Barcode proses (semua user login bisa akses)
    Route::post('/proses/{id}/barcode/kain', [ProsesController::class, 'barcodeKain'])->name('proses.barcode.kain');
    Route::post('/proses/{id}/barcode/la', [ProsesController::class, 'barcodeLa'])->name('proses.barcode.la');
    Route::post('/proses/{id}/barcode/aux', [ProsesController::class, 'barcodeAux'])->name('proses.barcode.aux');
    Route::get('/proses/{id}/barcodes', [ProsesController::class, 'barcodes'])->name('proses.barcodes');
    Route::post('/proses/{proses}/barcode/{type}/{barcode}/cancel', [ProsesController::class, 'cancelBarcode']);
    Route::post('/proses/{id}/update', [ProsesController::class, 'update'])->name('proses.update');
    Route::post('/proses/{id}/move', [ProsesController::class, 'move'])->name('proses.move');
    Route::delete('/proses/{id}/delete', [ProsesController::class, 'destroy'])->name('proses.delete');

    // Approval routes
    Route::get('/approval/fm', [ApprovalController::class, 'approval_fm'])->name('approval.fm');
    Route::get('/approval/vp', [ApprovalController::class, 'approval_vp'])->name('approval.vp');
    Route::post('/approval/status', [ApprovalController::class, 'approval_status'])->name('approval.status');

    Route::resource('aux', AuxlController::class);
});

// Tambahkan di luar middleware auth agar bisa diakses select2
Route::post('/api/proxy-op', [App\Http\Controllers\ProsesController::class, 'proxyOpSearch']);
