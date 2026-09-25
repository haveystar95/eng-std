<?php

declare(strict_types=1);

use App\Modules\Admin\Presentation\Http\Controller\AuthController;
use App\Modules\Admin\Presentation\Http\Controller\CollectionController;
use App\Modules\Admin\Presentation\Http\Controller\ContentHealthController;
use App\Modules\Admin\Presentation\Http\Controller\CostController;
use App\Modules\Admin\Presentation\Http\Controller\DashboardController;
use App\Modules\Admin\Presentation\Http\Controller\ExerciseModeController;
use App\Modules\Admin\Presentation\Http\Controller\GenerationController;
use App\Modules\Admin\Presentation\Http\Controller\LadderController;
use App\Modules\Admin\Presentation\Http\Controller\ModeSettingsController;
use App\Modules\Admin\Presentation\Http\Controller\PlanChecksController;
use App\Modules\Admin\Presentation\Http\Controller\PlanPageController;
use App\Modules\Admin\Presentation\Http\Controller\PlaygroundController;
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
    // The learner's access to the paid plan (наряд ACC-1 §2): read only — rights are given by `access:grant`.
    Route::get('/users/{id}/access', [UserController::class, 'access']);

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

    // The plan's checks (docs/plan-v2.md §5): how often each fired, per prompt version. Read-only;
    // a mode is switched in config/plan.php, never from the panel.
    Route::get('/plans/checks', [PlanChecksController::class, 'index']);

    // The learner's plan page (наряд ADM-1): read-only, one aggregating endpoint per section, each for the whole plan or
    // `?day=N`. The plan is its code (characters 5–10 of the ULID, upper-case Crockford) or its full id — never «checks».
    Route::get('/users/{id}/plans', [PlanPageController::class, 'learnerPlans']);
    Route::prefix('/plans/{code}')
        ->where(['code' => '[0-9A-HJKMNP-TV-Z]{6}|[0-9A-HJKMNP-TV-Z]{26}'])
        ->group(function (): void {
            Route::get('/', [PlanPageController::class, 'show']);
            Route::get('/issues', [PlanPageController::class, 'issues']);
            Route::get('/days', [PlanPageController::class, 'days']);
            Route::get('/pipeline', [PlanPageController::class, 'pipeline']);
            Route::get('/lesson', [PlanPageController::class, 'lesson']);
            Route::get('/passage', [PlanPageController::class, 'passage']);
            Route::get('/conversations', [PlanPageController::class, 'conversations']);
            Route::get('/money', [PlanPageController::class, 'money']);
            Route::get('/calls', [PlanPageController::class, 'calls']);
            Route::get('/audio/{audioId}', [PlanPageController::class, 'audio'])->where('audioId', '[0-9A-HJKMNP-TV-Z]{26}');
        });

    // «Здоровье контента» — what the dictionary is stocked with and which trainers that stock can
    // build. Read-only by design: there is deliberately no route that STARTS the enrichment run,
    // only one that hands over the command to paste. Not to be confused with /ladder, which answers
    // when a trainer opens FOR A LEARNER; this answers whether the card can be built at all.
    Route::get('/content-health/summary', [ContentHealthController::class, 'summary']);
    Route::get('/content-health/collections/{id}', [ContentHealthController::class, 'collection']);
    Route::get('/content-health/terms/{id}', [ContentHealthController::class, 'term']);

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

    // «Песочница»: try a prompt on a model, then run what came back past the REAL distractor
    // validator. Writes nothing — no distractors, no suppressions, no version marks. POST because
    // both spend or compute on a body, not because either mutates anything.
    Route::get('/playground/providers', [PlaygroundController::class, 'providers']);
    Route::post('/playground/generate', [PlaygroundController::class, 'generateAction']);
    Route::get('/playground/runs/{id}', [PlaygroundController::class, 'runAction']);
    Route::post('/playground/validate', [PlaygroundController::class, 'validateAction']);

    Route::get('/practice-dialogs', [PracticeDialogController::class, 'index']);
    Route::get('/practice-dialogs/{id}', [PracticeDialogController::class, 'show']);

    Route::get('/generations', [GenerationController::class, 'index']);
});
