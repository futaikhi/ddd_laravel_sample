<?php

declare(strict_types=1);

use Apps\Api\Booking\BookingController;
use Apps\Api\Client\ClientController;
use Apps\Api\Customer\CustomerController;
use Apps\Api\Product\ProductController;
use Apps\Api\Sales\SalesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Booking Sample
|--------------------------------------------------------------------------
|
| Simple booking API demonstrating DDD patterns:
| - Request → DTO → Action flow
| - CQRS with Commands and Queries
| - Repository pattern
|
*/

Route::prefix('clients')->group(function () {
    Route::post('/', [ClientController::class, 'create']);
    Route::get('/{id}', [ClientController::class, 'show']);
});

Route::prefix('bookings')->group(function () {
    Route::get('/', [BookingController::class, 'index']);
    Route::post('/', [BookingController::class, 'create']);
    Route::get('/{id}', [BookingController::class, 'show']);
    Route::post('/{id}/confirm', [BookingController::class, 'confirm']);
    Route::post('/{id}/cancel', [BookingController::class, 'cancel']);
});

Route::prefix('customers')->group(function () {
    Route::post('/', [CustomerController::class, 'create']);
});

Route::prefix('products')->group(function () {
    Route::post('/', [ProductController::class, 'create']);
});

Route::prefix('sales')->group(function () {
    Route::post('/', [SalesController::class, 'create']);
    Route::post('/{id}/confirm', [SalesController::class, 'confirm']);
    Route::post('/{id}/cancel', [SalesController::class, 'cancel']);
    Route::post('/{id}/complete', [SalesController::class, 'complete']);
});

// Include Swagger/API Documentation routes
require __DIR__.'/swagger.php';
