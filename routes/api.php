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
| API Routes for Booking Sample
|--------------------------------------------------------------------------
|
| Simple booking API demonstrating DDD patterns:
| - Request → DTO → Action flow
| - CQRS Commands and Queries
| - Repository pattern
|
*/

Route::prefix('clients')->group(function (): void {
    Route::post('/', [ClientController::class, 'create']);
    Route::get('/{id}', [ClientController::class, 'show']);
});

Route::prefix('bookings')->group(function (): void {
    Route::get('/', [BookingController::class, 'index']);
    Route::post('/', [BookingController::class, 'create']);
    Route::get('/{id}', [BookingController::class, 'show']);
    Route::post('/{id}/confirm', [BookingController::class, 'confirm']);
    Route::post('/{id}/cancel', [BookingController::class, 'cancel']);
});

Route::prefix('customers')->group(function (): void {
    Route::post('/', [CustomerController::class, 'create']);
});

Route::prefix('products')->group(function (): void {
    Route::post('/', [ProductController::class, 'create']);
});

Route::prefix('sales')->group(function (): void {
    // Commands (write side)
    Route::post('/', [SalesController::class, 'create']);
    Route::post('/import-csv', [SalesController::class, 'importCsv']);
    Route::post('/{id}/confirm', [SalesController::class, 'confirm']);
    Route::post('/{id}/cancel', [SalesController::class, 'cancel']);
    Route::post('/{id}/complete', [SalesController::class, 'complete']);

    // Queries (read side) - CQRS
    Route::get('/', [SalesController::class, 'index']);
    Route::get('/reports/sales', [SalesController::class, 'salesReport']);
    Route::get('/reports/commissions', [SalesController::class, 'commissionSummary']);
    Route::get('/{id}', [SalesController::class, 'show']);
});

// Include Swagger/API Documentation routes
require __DIR__.'/swagger.php';
