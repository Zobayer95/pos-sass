<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::prefix('staff')->middleware('can:manage-staff')->group(function () {
        Route::get('/', [AuthController::class, 'listStaff']);
        Route::post('/', [AuthController::class, 'createStaff']);
        Route::delete('/{id}', [AuthController::class, 'deleteStaff']);
    });

    Route::apiResource('products', ProductController::class);
    Route::apiResource('customers', CustomerController::class);

    Route::apiResource('orders', OrderController::class)->except(['update', 'destroy']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    Route::prefix('reports')->group(function () {
        Route::get('/daily-sales', [ReportController::class, 'dailySales']);
        Route::get('/top-products', [ReportController::class, 'topProducts']);
        Route::get('/low-stock', [ReportController::class, 'lowStock']);
    });
});
