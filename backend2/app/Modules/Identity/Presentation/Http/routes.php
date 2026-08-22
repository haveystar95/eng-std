<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Service\DevLoginGate;
use App\Modules\Identity\Presentation\Http\Controller\AuthController;
use App\Modules\Identity\Presentation\Http\Controller\ProfileController;
use Illuminate\Support\Facades\Route;

// Prefixed with /api/v1 by IdentityServiceProvider.
Route::middleware('throttle:60,1')->group(function (): void {
    Route::post('/auth/google', [AuthController::class, 'google']);

    // The QA door. It is not registered at all unless the gate is open (non-production AND
    // DEV_LOGIN_ENABLED) — the FIRST of the two locks; the port asks the same question again at
    // request time. Registration-time gating is what keeps the path absent from a production
    // route table entirely, so `route:list` on a deployed box cannot even name it.
    if (DevLoginGate::isOpen((string) app()->environment(), (bool) config('qa.dev_login'))) {
        Route::post('/auth/dev', [AuthController::class, 'dev']);
    }

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::delete('/auth/me', [AuthController::class, 'deleteAccount']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::put('/profile', [ProfileController::class, 'update']);
    });
});
