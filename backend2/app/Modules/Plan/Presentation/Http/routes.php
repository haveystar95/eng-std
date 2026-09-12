<?php

declare(strict_types=1);

use App\Modules\Plan\Presentation\Http\Controller\PlanAudioController;
use App\Modules\Plan\Presentation\Http\Controller\PlanController;
use App\Modules\Plan\Presentation\Http\Controller\PlanDayController;
use App\Modules\Plan\Presentation\Http\Controller\PlanImageController;
use Illuminate\Support\Facades\Route;

// Prefixed with /api/v1 by PlanServiceProvider. The contract: docs/plan-api.md.

// Scene photo copies — OUTSIDE the API throttle (доработка PLAN-UI-3). In the compose stack nginx
// serves the stored file itself (docker/nginx/default.conf) and only a missing copy reaches this
// route, which fetches, stores and serves it; ten photos of a route never eat the API's 120/min.
Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/plans/images/{sceneId}/{size}', [PlanImageController::class, 'show'])->whereIn('size', ['112', '448']);
});

Route::middleware(['throttle:120,1', 'auth:sanctum'])->group(function (): void {
    // Named routes before /plans/{id}: «current», «versions», «languages» and «audio» are words, not ULIDs.
    Route::get('/plans/current', [PlanController::class, 'current']);
    Route::get('/plans/versions', [PlanController::class, 'versions']);
    Route::get('/plans/languages', [PlanController::class, 'languages']);
    Route::get('/plans/audio/{audioId}', [PlanAudioController::class, 'show']);

    Route::get('/plans', [PlanController::class, 'index']);
    Route::post('/plans', [PlanController::class, 'store']);
    Route::get('/plans/{id}', [PlanController::class, 'show']);
    Route::get('/plans/{id}/build', [PlanController::class, 'buildStatus']);
    Route::post('/plans/{id}/build/retry', [PlanController::class, 'retryBuild']);
    Route::delete('/plans/{id}/scenes/{sceneId}', [PlanController::class, 'removeScene']);
    Route::post('/plans/{id}/scenes/{sceneId}/lesson/retry', [PlanController::class, 'retryLesson']);
    Route::post('/plans/{id}/start', [PlanController::class, 'start']);
    Route::patch('/plans/{id}/schedule', [PlanController::class, 'reschedule']);
    Route::post('/plans/{id}/finish', [PlanController::class, 'finish']);
    Route::delete('/plans/{id}', [PlanController::class, 'destroy']);

    Route::get('/plans/{id}/days/{number}', [PlanDayController::class, 'room'])->whereNumber('number');
    Route::post('/plans/{id}/days/{number}/open', [PlanDayController::class, 'open'])->whereNumber('number');
    Route::get('/plans/{id}/days/{number}/cards', [PlanDayController::class, 'cards'])->whereNumber('number');
    Route::post('/plans/{id}/days/{number}/cards/{cardId}/answer', [PlanDayController::class, 'answer'])->whereNumber('number');
    Route::post('/plans/{id}/days/{number}/stages/{stage}/close', [PlanDayController::class, 'closeStage'])->whereNumber('number');
    Route::post('/plans/{id}/days/{number}/close', [PlanDayController::class, 'close'])->whereNumber('number');
    Route::get('/plans/{id}/days/{number}/sheet', [PlanDayController::class, 'sheet'])->whereNumber('number');
});
