<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderSyncController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ToppingController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['api.token'])->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/toppings', [ToppingController::class, 'index']);
    Route::post('/orders/sync', [OrderSyncController::class, 'sync']);
});
