<?php

declare(strict_types=1);

use App\Modules\Admin\Presentation\Http\Controller\AuthController;
use App\Modules\Admin\Presentation\Http\Controller\CollectionController;
use App\Modules\Admin\Presentation\Http\Controller\DashboardController;
use App\Modules\Admin\Presentation\Http\Controller\GenerationController;
use App\Modules\Admin\Presentation\Http\Controller\PracticeDialogController;
use App\Modules\Admin\Presentation\Http\Controller\RequestLogController;
use App\Modules\Admin\Presentation\Http\Controller\TermController;
use App\Modules\Admin\Presentation\Http\Controller\TierController;
use App\Modules\Admin\Presentation\Http\Controller\UserController;
use Illuminate\Support\Facades\Route;

// Prefixed with /admin/api by AdminServiceProvider. App users can never authenticate here — the
// `admin` guard's provider is the `admins` table, so a user's Sanctum token fails it.
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:admin')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::get('/users/{id}/plan', [UserController::class, 'plan']);
    Route::get('/users/{id}/collections', [UserController::class, 'collections']);
    Route::get('/users/{id}/reviews', [UserController::class, 'reviews']);
    Route::post('/users/{id}/tier', [TierController::class, 'update']);

    Route::get('/collections', [CollectionController::class, 'index']);
    Route::get('/collections/{id}', [CollectionController::class, 'show']);

    Route::get('/terms', [TermController::class, 'index']);
    Route::get('/terms/{id}', [TermController::class, 'show']);

    Route::get('/request-logs', [RequestLogController::class, 'index']);

    Route::get('/practice-dialogs', [PracticeDialogController::class, 'index']);
    Route::get('/practice-dialogs/{id}', [PracticeDialogController::class, 'show']);

    Route::get('/generations', [GenerationController::class, 'index']);
});
