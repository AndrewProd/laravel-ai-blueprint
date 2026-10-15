<?php

use App\Http\Controllers\Api\MissingController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('orders', OrderController::class)->only(['index', 'store']);
});

Route::get('/legacy', [MissingController::class, 'index']);
