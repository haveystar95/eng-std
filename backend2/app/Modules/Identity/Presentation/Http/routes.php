<?php

declare(strict_types=1);

use App\Modules\Identity\Presentation\Http\Controller\AuthController;
use App\Modules\Identity\Presentation\Http\Controller\ProfileController;
use Illuminate\Support\Facades\Route;

// Prefixed with /api/v1 by IdentityServiceProvider.
Route::middleware('throttle:60,1')->group(function (): void {
    Route::post('/auth/google', [AuthController::class, 'google']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::delete('/auth/me', [AuthController::class, 'deleteAccount']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::put('/profile', [ProfileController::class, 'update']);
    });
});
