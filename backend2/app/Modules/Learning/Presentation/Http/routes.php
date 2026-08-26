<?php

declare(strict_types=1);

use App\Modules\Learning\Presentation\Http\Controller\HomeController;
use App\Modules\Learning\Presentation\Http\Controller\PoolController;
use App\Modules\Learning\Presentation\Http\Controller\ReviewController;
use App\Modules\Learning\Presentation\Http\Controller\StudyController;
use App\Modules\Learning\Presentation\Http\Controller\SyncController;
use App\Modules\Learning\Presentation\Http\Controller\TriageController;
use Illuminate\Support\Facades\Route;

// Prefixed with /api/v1 by LearningServiceProvider.
Route::middleware(['throttle:120,1', 'auth:sanctum'])->group(function (): void {
    Route::post('/study/sessions', [StudyController::class, 'session']);
    Route::post('/study/sessions/{sessionId}/complete', [StudyController::class, 'complete']);
    Route::get('/study/progress', [StudyController::class, 'progress']);
    Route::get('/stats', [StudyController::class, 'stats']);
    // The home screen's whole day in one read — the planner's answer, not the dashboard's totals.
    Route::get('/home-plan', [HomeController::class, 'plan']);
    Route::post('/reviews/batch', [ReviewController::class, 'batch']);

    // The pool: the two deliberate acts that put a word into the trainer and take it back out.
    // Reading the pool is not here on purpose — the device reads it from its own mirror.
    Route::put('/pool/terms/{termId}', [PoolController::class, 'enroll']);
    Route::delete('/pool/terms/{termId}', [PoolController::class, 'unenroll']);

    Route::get('/triage/queue', [TriageController::class, 'queue']);
    Route::post('/triage/batch', [TriageController::class, 'batch']);

    Route::get('/sync/cursor', [SyncController::class, 'cursor']);
    Route::get('/sync', [SyncController::class, 'sync']);
});
