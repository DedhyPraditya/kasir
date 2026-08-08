<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/download-apk', [\App\Http\Controllers\Api\AppVersionController::class, 'download']);

Route::get('/', function () {
    if (!auth()->check()) {
        return redirect()->route('login');
    }
    if (auth()->user()->hasRole('kasir')) {
        return redirect()->route('pos');
    }
    return redirect()->route('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/pos', \App\Livewire\Pos::class)->name('pos');
    
    // Hanya Admin yang bisa akses dashboard
    Route::get('/dashboard', \App\Livewire\Dashboard::class)
        ->middleware(['redirect.kasir', 'role:admin'])
        ->name('dashboard');

    // Laporan - hanya Admin
    Route::get('/laporan', \App\Livewire\Laporan::class)
        ->middleware(['redirect.kasir', 'role:admin'])
        ->name('laporan');
    Route::get('/laporan/export', [\App\Http\Controllers\LaporanController::class, 'export'])
        ->middleware(['redirect.kasir', 'role:admin'])
        ->name('laporan.export');

    // Produk - hanya Admin
    Route::get('/produk', \App\Livewire\Produk::class)
        ->middleware(['redirect.kasir', 'role:admin'])
        ->name('produk');

    // Pengaturan QRIS - hanya Admin
    Route::get('/pengaturan/qris', \App\Livewire\QrisSettings::class)
        ->middleware(['redirect.kasir', 'role:admin'])
        ->name('qris.settings');

    // Rute Profile dimasukkan ke dalam grup middleware 'auth'
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
