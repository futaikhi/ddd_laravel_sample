<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Documentation routes (will be under /api prefix from api.php)
Route::get('/documentation', function () {
    $specUrl = route('api-docs-yaml');

    return view('swagger-ui', ['specUrl' => $specUrl]);
})->name('api-documentation');

Route::get('/docs/api-docs.yaml', function () {
    return response()->file(storage_path('api-docs/api-docs.yaml'), [
        'Content-Type' => 'application/x-yaml',
    ]);
})->name('api-docs-yaml');
