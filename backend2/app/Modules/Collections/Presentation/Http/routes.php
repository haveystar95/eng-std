<?php

declare(strict_types=1);

use App\Modules\Collections\Presentation\Http\Controller\CollectionController;
use Illuminate\Support\Facades\Route;

// Prefixed with /api/v1 by CollectionsServiceProvider.
Route::middleware(['throttle:120,1', 'auth:sanctum'])->group(function (): void {
    Route::get('/collections', [CollectionController::class, 'index']);
    Route::post('/collections', [CollectionController::class, 'store']);
    Route::get('/collections/{id}', [CollectionController::class, 'show']);
    Route::patch('/collections/{id}', [CollectionController::class, 'update']);
    Route::delete('/collections/{id}', [CollectionController::class, 'destroy']);
});
