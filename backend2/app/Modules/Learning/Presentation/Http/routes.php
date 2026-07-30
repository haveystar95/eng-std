<?php

declare(strict_types=1);

use App\Modules\Learning\Presentation\Http\Controller\ReviewController;
use App\Modules\Learning\Presentation\Http\Controller\StudyController;
use App\Modules\Learning\Presentation\Http\Controller\TriageController;
use Illuminate\Support\Facades\Route;

// Prefixed with /api/v1 by LearningServiceProvider.
Route::middleware(['throttle:120,1', 'auth:sanctum'])->group(function (): void {
    Route::get('/study/due', [StudyController::class, 'due']);
    Route::get('/study/progress', [StudyController::class, 'progress']);
    Route::get('/stats', [StudyController::class, 'stats']);
    Route::post('/reviews/batch', [ReviewController::class, 'batch']);

    Route::get('/triage/queue', [TriageController::class, 'queue']);
    Route::post('/triage/batch', [TriageController::class, 'batch']);
});
