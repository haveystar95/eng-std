<?php

declare(strict_types=1);

use App\Modules\Learning\Presentation\Http\Controller\HomeController;
use App\Modules\Learning\Presentation\Http\Controller\PlanController;
use App\Modules\Learning\Presentation\Http\Controller\PoolController;
use App\Modules\Learning\Presentation\Http\Controller\QaPlanClockController;
use App\Modules\Learning\Presentation\Http\Controller\ReviewController;
use App\Modules\Learning\Presentation\Http\Controller\StudyController;
use App\Modules\Learning\Presentation\Http\Controller\SyncController;
use App\Modules\Learning\Presentation\Http\Controller\TriageController;
use App\Modules\Learning\Presentation\Http\Middleware\ShiftQaPlanClock;
use Illuminate\Support\Facades\Route;

// Prefixed with /api/v1 by LearningServiceProvider.
// ПОДСТАНОВКА «СЕГОДНЯ» для QA-аккаунта стоит на всей группе (наряд DAY-FIX-2): плановые экраны,
// посадка и партия ответов обязаны жить в одном календаре, и для всех, кому дверь закрыта, это
// no-op.
Route::middleware(['throttle:120,1', 'auth:sanctum', ShiftQaPlanClock::class])->group(function (): void {
    // ДЕВ-ДВЕРЬ СМЕНЫ ДНЕЙ — та же дверь, что у входа без пароля: аккаунт `is_qa` И среда не
    // production при включённом флаге. Всем остальным — 404, как чужому плану.
    Route::get('/qa/plan-clock', [QaPlanClockController::class, 'show']);
    Route::post('/qa/plan-clock', [QaPlanClockController::class, 'set']);

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

    // The entry's optional listening step (кадры V4·03…03г) — asked BEFORE any plan exists, which
    // is why it carries no id and stands above the rest. It answers 200 with an empty list when the
    // warm-up could not be written: the step is optional, and a failure is not the learner's news.
    Route::post('/plans/listen-warmup', [PlanController::class, 'listenWarmup']);

    // Learning plans. `/plans/active` sits BEFORE `/plans/{planId}` — otherwise «active» is
    // matched as a plan id and answers 404 forever.
    Route::post('/plans', [PlanController::class, 'store']);
    // The finished plan and the archive under it — everything that is not a draft, newest first.
    Route::get('/plans', [PlanController::class, 'index']);
    Route::get('/plans/active', [PlanController::class, 'active']);
    Route::get('/plans/{planId}', [PlanController::class, 'show']);
    Route::get('/plans/{planId}/days/{dayIndex}', [PlanController::class, 'day']);
    // The day actually being studied. Both shapes on purpose: with a day index for a client that
    // knows which day it is showing, without one for «дай мне сегодняшнюю» — the server owns the
    // focus, so it must be possible to ask for it without recomputing it on the device.
    Route::post('/plans/{planId}/days/{dayIndex}/session', [PlanController::class, 'session']);
    Route::post('/plans/{planId}/session', [PlanController::class, 'session']);
    // «Собери мне день n». Idempotent and safe to poll — it answers with the day's status.
    Route::post('/plans/{planId}/days/{dayIndex}/generate', [PlanController::class, 'generateDay']);
    // A BUTTON, not a poll: one more attempt for a day that burned, and it spends money.
    Route::post('/plans/{planId}/days/{dayIndex}/rebuild', [PlanController::class, 'rebuildDay']);
    Route::post('/plans/{planId}/outline', [PlanController::class, 'buildOutline']);
    Route::patch('/plans/{planId}/outline', [PlanController::class, 'reschedule']);
    Route::post('/plans/{planId}/start', [PlanController::class, 'start']);
    Route::post('/plans/{planId}/pause', [PlanController::class, 'pause']);
    Route::post('/plans/{planId}/abandon', [PlanController::class, 'abandon']);
    // «Подготовка завершена» — the last day walked, the plan closed by the learner. The other way
    // in is `feedback`, which closes it the evening AFTER the event; this one is the morning of
    // it, and without it the plan could not be finished from the app at all (Д-27).
    Route::post('/plans/{planId}/complete', [PlanController::class, 'complete']);
    // The morning of the event, and the evening after it. The second one CLOSES the plan: answering
    // «как прошло» is the last thing it asks, and a plan whose event is over must stop holding
    // words out of the ordinary day.
    Route::post('/plans/{planId}/rehearsal', [PlanController::class, 'rehearsal']);
    // ПРОГОН СЦЕНЫ ЗАВЕРШЁН (наряд SCENE-RUN). Ходы с исходами; числа считает сервер. Отдельный
    // вызов, а не поле в `complete` посадки: прогон — событие сцены, а посадка может кончиться, не
    // дойдя до него.
    Route::post('/plans/{planId}/scene-runs', [PlanController::class, 'sceneRun']);
    Route::post('/plans/{planId}/feedback', [PlanController::class, 'feedback']);

    Route::get('/sync/cursor', [SyncController::class, 'cursor']);
    Route::get('/sync', [SyncController::class, 'sync']);
});
