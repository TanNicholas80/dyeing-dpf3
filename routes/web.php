<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuxlController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MesinController;
use App\Http\Controllers\ProsesController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ActivityLogController;

Route::get('/', [AuthController::class, 'login'])->name('login');
Route::post('/login-proses', [AuthController::class, 'login_proses'])->name('login-proses');

Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard: Semua role bisa akses kecuali aux
    Route::middleware('role:super_admin,ds,mesin,ppic,fm,vp,owner')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard/proses-statuses', [DashboardController::class, 'prosesStatuses'])->name('dashboard.proses-statuses');
    });

    Route::get('/profile', [UserController::class, 'editProfile'])->name('user.profile');
    Route::post('/profile/update', [UserController::class, 'updateProfile'])->name('user.profile.update');

    /**
     * USER MANAGEMENT
     * - SuperAdmin: full CRUD user
     * - Owner: hanya melihat daftar user
     */

    Route::middleware('role:super_admin')->group(function () {
        Route::get('/user', [UserController::class, 'index'])->name('user.index');
        Route::get('/user/create', [UserController::class, 'create'])->name('user.create');
        Route::post('/user/store', [UserController::class, 'store'])->name('user.store');
        Route::get('/user/edit/{id}', [UserController::class, 'edit'])->name('user.edit');
        Route::post('/user/update/{id}', [UserController::class, 'update'])->name('user.update');
        Route::delete('/user/delete/{id}', [UserController::class, 'destroy'])->name('user.delete');
    });

    /**
     * MESIN
     * - SuperAdmin: full CRUD mesin
     * - FM, VP, PPIC, Owner: hanya melihat mesin (index)
     */
    Route::middleware('role:super_admin,fm,vp,ppic,owner')->group(function () {
        Route::get('/mesin', [MesinController::class, 'index'])->name('mesin.index');
    });
    Route::middleware('role:super_admin')->group(function () {
        Route::resource('mesin', MesinController::class)->except(['show', 'index']);
    });
    Route::get('/mesin/statuses', [MesinController::class, 'statuses'])->name('mesin.statuses');

    /**
     * PROSES & BARCODE
     * - SuperAdmin, DS, Mesin, PPIC: kelola proses & tambah barcode
     * - PPIC: cancel barcode
     */
    Route::middleware('role:super_admin,ppic')->group(function () {
        Route::resource('proses', ProsesController::class)->except(['update', 'destroy']);

        // Update / pindah / tukar / delete proses
        Route::post('/proses/{id}/update', [ProsesController::class, 'update'])->name('proses.update');
        Route::post('/proses/{id}/move', [ProsesController::class, 'move'])->name('proses.move');
        Route::post('/proses/{id}/swap', [ProsesController::class, 'swap'])->name('proses.swap');
        Route::delete('/proses/{id}/delete', [ProsesController::class, 'destroy'])->name('proses.delete');
    });

    Route::middleware('role:super_admin,mesin,ppic')->group(function () {
        // Tambah barcode
        Route::post('/proses/{id}/barcode/kain', [ProsesController::class, 'barcodeKain'])->name('proses.barcode.kain');
        Route::post('/proses/{id}/barcode/la', [ProsesController::class, 'barcodeLa'])->name('proses.barcode.la');
        Route::post('/proses/{id}/barcode/aux', [ProsesController::class, 'barcodeAux'])->name('proses.barcode.aux');
    });

    // View barcode: SuperAdmin, DS, Mesin, PPIC, FM, VP, Owner (untuk melihat barcode yang sudah ditambahkan)
    Route::middleware('role:super_admin,ds,mesin,ppic,fm,vp,owner')->group(function () {
        Route::get('/proses/{id}/barcodes', [ProsesController::class, 'barcodes'])->name('proses.barcodes');
    });

    // Cancel barcode: SuperAdmin, PPIC
    Route::middleware('role:super_admin,ppic')->group(function () {
        Route::post('/proses/{proses}/barcode/{type}/{barcode}/cancel', [ProsesController::class, 'cancelBarcode']);
    });

    /**
     * APPROVAL & ACTIVITY LOG
     * - FM: approval FM + activity log
     * - VP: approval VP + activity log
     * - Owner: melihat approval FM/VP (GET saja)
     */
    Route::middleware('role:super_admin,fm')->group(function () {
        Route::get('/approval/fm', [ApprovalController::class, 'approval_fm'])->name('approval.fm');
    });
    Route::middleware('role:super_admin,vp')->group(function () {
        Route::get('/approval/vp', [ApprovalController::class, 'approval_vp'])->name('approval.vp');
    });
    // Ubah status approval hanya oleh SuperAdmin, FM, VP
    Route::middleware('role:super_admin,fm,vp')->group(function () {
        Route::post('/approval/status', [ApprovalController::class, 'approval_status'])->name('approval.status');
    });

    // Activity log: SuperAdmin, FM, VP
    Route::middleware('role:super_admin,fm,vp')->group(function () {
        Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');
    });

    /**
     * AUX
     * - AUX user: akses penuh menu AUX
     * - SuperAdmin: akses penuh AUX
     * - Owner: minimal bisa melihat AUX (sementara pakai resource penuh, jika perlu bisa dibatasi di controller)
     */
    Route::middleware('role:super_admin,aux')->group(function () {
        Route::resource('aux', AuxlController::class)->except(['destroy']);
    });
    Route::middleware('role:super_admin')->group(function () {
        Route::delete('aux/{aux}', [AuxlController::class, 'destroy'])
            ->name('aux.destroy');
    });
});

// Tambahkan di luar middleware auth agar bisa diakses select2
Route::post('/api/proxy-op', [App\Http\Controllers\ProsesController::class, 'proxyOpSearch']);
// Tambahkan route API proxy auxiliary untuk select2
Route::post('/api/proxy-auxiliary', [\App\Http\Controllers\AuxlController::class, 'proxyAuxiliary']);
// Cek (no_op, no_partai) sudah terpakai di proses lain (validasi tambah proses)
Route::post('/api/check-partai-used', [App\Http\Controllers\ProsesController::class, 'checkPartaiUsed']);
