<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderSyncController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ToppingController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AppVersionController;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/app-version', [AppVersionController::class, 'check']);
Route::get('/download-apk', [AppVersionController::class, 'download']);

Route::middleware(['api.token'])->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/toppings', [ToppingController::class, 'index']);
    Route::get('/orders', [OrderSyncController::class, 'history']);
    Route::post('/orders/sync', [OrderSyncController::class, 'sync']);
});
