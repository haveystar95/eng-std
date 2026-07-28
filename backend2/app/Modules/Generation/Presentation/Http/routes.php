<?php

declare(strict_types=1);

use App\Modules\Generation\Presentation\Http\Controller\GenerationController;
use Illuminate\Support\Facades\Route;

// Prefixed with /api/v1 by GenerationServiceProvider.
Route::middleware(['throttle:60,1', 'auth:sanctum'])->group(function (): void {
    Route::post('/generations', [GenerationController::class, 'store']);
    Route::get('/generations/{id}', [GenerationController::class, 'show']);
});
