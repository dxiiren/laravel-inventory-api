<?php

use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProductController;
use App\Http\Middleware\ApiDataResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(ApiDataResponse::class)->group(function () {

    // Public read: the companion vue-inventory-ui browses the catalogue without
    // a token.
    Route::get('products', [ProductController::class, 'index'])
        ->name('products.index');

    // Everything that mutates stock needs a Sanctum token, as does the import
    // report those writes produce.
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('products/import', [ProductController::class, 'import'])
            ->name('products.import');

        Route::apiResource('products', ProductController::class)
            ->only(['store', 'update', 'destroy']);

        Route::get('imports/{import}', [ImportController::class, 'show'])
            ->name('imports.show');
    });
});
