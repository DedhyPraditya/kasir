<?php

use App\Http\Controllers\Api\OrderSyncController;
use Illuminate\Support\Facades\Route;

Route::post('/orders/sync', [OrderSyncController::class, 'sync']);
