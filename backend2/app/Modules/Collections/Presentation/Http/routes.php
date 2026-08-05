<?php

declare(strict_types=1);

use App\Modules\Collections\Presentation\Http\Controller\CollectionController;
use App\Modules\Collections\Presentation\Http\Controller\StoreController;
use Illuminate\Support\Facades\Route;

// Prefixed with /api/v1 by CollectionsServiceProvider.
Route::middleware(['throttle:120,1', 'auth:sanctum'])->group(function (): void {
    Route::get('/store/collections', [StoreController::class, 'index']);
    Route::post('/store/collections/{id}/subscribe', [StoreController::class, 'subscribe']);
    Route::delete('/store/collections/{id}/subscribe', [StoreController::class, 'unsubscribe']);

    Route::get('/collections', [CollectionController::class, 'index']);
    Route::post('/collections', [CollectionController::class, 'store']);
    Route::get('/collections/{id}', [CollectionController::class, 'show']);
    Route::patch('/collections/{id}', [CollectionController::class, 'update']);
    Route::delete('/collections/{id}', [CollectionController::class, 'destroy']);

    Route::post('/collections/{id}/items', [CollectionController::class, 'addItem']);
    Route::delete('/collections/{id}/items/{termId}', [CollectionController::class, 'removeItem']);
});
