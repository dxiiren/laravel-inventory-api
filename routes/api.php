<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ApiDataResponse;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProductController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(ApiDataResponse::class)->group(function () {

    Route::post('products/import', [ProductController::class, 'import'])
        ->name('products.import');

    Route::get('products', [ProductController::class, 'index'])
        ->name('products.index');

    Route::apiResource('products', ProductController::class)
        ->only(['store', 'update', 'destroy']);

    Route::get('imports/{import}', [ImportController::class, 'show'])
        ->name('imports.show');
});