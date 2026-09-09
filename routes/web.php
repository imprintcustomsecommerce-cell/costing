<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\MaterialController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ArtworkController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\QuotationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('quotations', QuotationController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
    Route::post('quotations/{quotation}/duplicate', [QuotationController::class, 'duplicate'])->name('quotations.duplicate');

    Route::resource('customers', CustomerController::class)->except(['show', 'destroy']);

    Route::get('artwork', [ArtworkController::class, 'index'])->name('artwork.index');
    Route::post('quotations/{quotation}/artwork', [ArtworkController::class, 'store'])->name('artwork.store');
    Route::get('artwork/{artwork}/download', [ArtworkController::class, 'download'])->name('artwork.download');
    Route::delete('artwork/{artwork}', [ArtworkController::class, 'destroy'])->name('artwork.destroy');

    Route::middleware('role:SUPER ADMIN|ADMIN')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('materials', MaterialController::class)->except(['show', 'destroy']);
        Route::post('materials/{material}/cost', [MaterialController::class, 'cost'])->name('materials.cost');
        Route::resource('products', ProductController::class)->except(['show', 'destroy']);
        Route::get('audit-logs', AuditLogController::class)->name('audit.index');
    });

    Route::middleware('role:SUPER ADMIN')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });

    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
