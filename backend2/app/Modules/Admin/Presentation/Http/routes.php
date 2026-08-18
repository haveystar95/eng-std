<?php

declare(strict_types=1);

use App\Modules\Admin\Presentation\Http\Controller\AuthController;
use App\Modules\Admin\Presentation\Http\Controller\CollectionController;
use App\Modules\Admin\Presentation\Http\Controller\CostController;
use App\Modules\Admin\Presentation\Http\Controller\DashboardController;
use App\Modules\Admin\Presentation\Http\Controller\ExerciseModeController;
use App\Modules\Admin\Presentation\Http\Controller\GenerationController;
use App\Modules\Admin\Presentation\Http\Controller\LadderController;
use App\Modules\Admin\Presentation\Http\Controller\ModeSettingsController;
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

    // The acquisition ladder, watched live while a device is being used. Read-only, and polled by
    // the panel every few seconds — the ladder's CONTROLS (the admission matrix) are elsewhere.
    Route::get('/ladder/learners', [LadderController::class, 'learners']);
    Route::get('/users/{id}/ladder', [LadderController::class, 'progress']);
    Route::get('/users/{id}/ladder/events', [LadderController::class, 'events']);

    // Trainer toggles: the product default, and a per-user override (audited, like the tier).
    Route::get('/exercise-modes', [ExerciseModeController::class, 'index']);
    Route::put('/exercise-modes', [ExerciseModeController::class, 'update']);
    Route::get('/users/{id}/exercise-modes', [ExerciseModeController::class, 'showForUser']);
    Route::put('/users/{id}/exercise-modes', [ExerciseModeController::class, 'updateForUser']);
    // The acquisition ladder: which rung opens which trainer. Its own screen is a separate task;
    // the API exists so the matrix is inspectable and movable from the moment it starts deciding
    // what learners are dealt. One mode per call — see ChangeModeAdmission.
    Route::put('/exercise-modes/admission', [ExerciseModeController::class, 'updateAdmission']);
    Route::put('/users/{id}/exercise-modes/admission', [ExerciseModeController::class, 'updateAdmissionForUser']);

    // «Матрица режимов»: the whole row (on/off, position and threshold) as one editable unit, keyed
    // by (scope, mode). Separate from /exercise-modes* above, which stays as the toggles screen's
    // API and is untouched by this наряд.
    Route::get('/mode-settings', [ModeSettingsController::class, 'index']);
    Route::put('/mode-settings', [ModeSettingsController::class, 'update']);
    Route::get('/users/{id}/mode-settings', [ModeSettingsController::class, 'showForUser']);
    Route::put('/users/{id}/mode-settings', [ModeSettingsController::class, 'updateForUser']);
    Route::delete('/users/{id}/mode-settings/{mode}', [ModeSettingsController::class, 'resetForUser']);

    Route::get('/collections', [CollectionController::class, 'index']);
    Route::get('/collections/{id}', [CollectionController::class, 'show']);
    Route::get('/collections/{id}/costs', [CostController::class, 'collection']);
    // Content curation. `impact` is read first so the confirm dialog can state the blast radius.
    Route::get('/collections/{id}/impact', [CollectionController::class, 'impact']);
    Route::patch('/collections/{id}', [CollectionController::class, 'update']);
    Route::post('/collections/{id}/terms', [CollectionController::class, 'addTerm']);
    Route::delete('/collections/{id}/terms/{termId}', [CollectionController::class, 'removeTerm']);
    Route::delete('/collections/{id}', [CollectionController::class, 'destroy']);

    Route::get('/costs', [CostController::class, 'summary']);

    Route::get('/terms', [TermController::class, 'index']);
    Route::get('/terms/{id}', [TermController::class, 'show']);
    Route::get('/terms/{id}/impact', [TermController::class, 'impact']);
    Route::patch('/terms/{id}', [TermController::class, 'update']);
    Route::delete('/terms/{id}', [TermController::class, 'destroy']);

    // `/request-logs` is the original name, kept so nothing that already calls it breaks; `/logs`
    // is what the panel and the contract use. Same controller — one implementation, two paths.
    Route::get('/logs', [RequestLogController::class, 'index']);
    Route::get('/logs/{id}', [RequestLogController::class, 'show']);
    Route::get('/request-logs', [RequestLogController::class, 'index']);

    Route::get('/practice-dialogs', [PracticeDialogController::class, 'index']);
    Route::get('/practice-dialogs/{id}', [PracticeDialogController::class, 'show']);

    Route::get('/generations', [GenerationController::class, 'index']);
});
