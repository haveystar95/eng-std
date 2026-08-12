<?php

declare(strict_types=1);

use App\Modules\Admin\Presentation\Http\Controller\AuthController;
use App\Modules\Admin\Presentation\Http\Controller\CollectionController;
use App\Modules\Admin\Presentation\Http\Controller\CostController;
use App\Modules\Admin\Presentation\Http\Controller\DashboardController;
use App\Modules\Admin\Presentation\Http\Controller\ExerciseModeController;
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

    // Trainer toggles: the product default, and a per-user override (audited, like the tier).
    Route::get('/exercise-modes', [ExerciseModeController::class, 'index']);
    Route::put('/exercise-modes', [ExerciseModeController::class, 'update']);
    Route::get('/users/{id}/exercise-modes', [ExerciseModeController::class, 'showForUser']);
    Route::put('/users/{id}/exercise-modes', [ExerciseModeController::class, 'updateForUser']);

    Route::get('/collections', [CollectionController::class, 'index']);
    Route::get('/collections/{id}', [CollectionController::class, 'show']);
    Route::get('/collections/{id}/costs', [CostController::class, 'collection']);

    Route::get('/costs', [CostController::class, 'summary']);

    Route::get('/terms', [TermController::class, 'index']);
    Route::get('/terms/{id}', [TermController::class, 'show']);

    // `/request-logs` is the original name, kept so nothing that already calls it breaks; `/logs`
    // is what the panel and the contract use. Same controller — one implementation, two paths.
    Route::get('/logs', [RequestLogController::class, 'index']);
    Route::get('/logs/{id}', [RequestLogController::class, 'show']);
    Route::get('/request-logs', [RequestLogController::class, 'index']);

    Route::get('/practice-dialogs', [PracticeDialogController::class, 'index']);
    Route::get('/practice-dialogs/{id}', [PracticeDialogController::class, 'show']);

    Route::get('/generations', [GenerationController::class, 'index']);
});
