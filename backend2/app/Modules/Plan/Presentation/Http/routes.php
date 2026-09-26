<?php

declare(strict_types=1);

use App\Modules\Plan\Presentation\Http\Controller\PlanAudioController;
use App\Modules\Plan\Presentation\Http\Controller\PlanCardJudgeController;
use App\Modules\Plan\Presentation\Http\Controller\PlanController;
use App\Modules\Plan\Presentation\Http\Controller\PlanConversationController;
use App\Modules\Plan\Presentation\Http\Controller\PlanDayController;
use App\Modules\Plan\Presentation\Http\Controller\PlanImageController;
use App\Modules\Plan\Presentation\Http\Controller\PlanRescueAudioController;
use Illuminate\Support\Facades\Route;

// Prefixed with /api/v1 by PlanServiceProvider. The contract: docs/plan-api.md.

// Scene photo copies — OUTSIDE the API throttle (доработка PLAN-UI-3). In the compose stack nginx
// serves the stored file itself (docker/nginx/default.conf) and only a missing copy reaches this
// route, which fetches, stores and serves it; ten photos of a route never eat the API's 120/min.
Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/plans/images/{sceneId}/{size}', [PlanImageController::class, 'show'])->whereIn('size', ['112', '448']);
});

Route::middleware(['throttle:120,1', 'auth:sanctum'])->group(function (): void {
    // Named routes before /plans/{id}: «current», «versions», «languages», «audio» and «rescue-audio» are words, not ULIDs.
    Route::get('/plans/current', [PlanController::class, 'current']);
    Route::get('/plans/versions', [PlanController::class, 'versions']);
    Route::get('/plans/languages', [PlanController::class, 'languages']);
    Route::get('/plans/audio/{audioId}', [PlanAudioController::class, 'show']);
    // A line of the rescue kit (наряд LANG-1b §2): one file per (target, gender, voice, line), whatever the plan.
    Route::get('/plans/rescue-audio/{key}', [PlanRescueAudioController::class, 'show'])->where('key', '[0-9a-f]{40}');
    // Both sides of a plan's pair, named (наряд LANG-1 §7): the entry screen's targets and the natives the
    // learner's own language is picked from. `/plans/languages` stays as it was for build (21).
    Route::get('/languages', [PlanController::class, 'languageOptions']);

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
    // The slot judge (SESSION-1a): one attempt at a card judged by meaning — a synchronous model call, capped per day.
    Route::post('/plans/{id}/days/{number}/cards/{cardId}/judge', [PlanCardJudgeController::class, 'judge'])->whereNumber('number');
    Route::post('/plans/{id}/days/{number}/stages/{stage}/close', [PlanDayController::class, 'closeStage'])->whereNumber('number');
    Route::post('/plans/{id}/days/{number}/close', [PlanDayController::class, 'close'])->whereNumber('number');

    // THE SIXTH STAGE (наряд CONV-1): the talk with the agent — start or carry on, one move, read back.
    // A move waits on a model and a voice, so it is the slowest call of the API by design (≤ 6 s).
    Route::post('/plans/{id}/days/{number}/conversation', [PlanConversationController::class, 'start'])->whereNumber('number');
    Route::get('/plans/{id}/conversation/{conversationId}', [PlanConversationController::class, 'show']);
    Route::post('/plans/{id}/conversation/{conversationId}/turn', [PlanConversationController::class, 'move']);
});
