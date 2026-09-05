<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * ОДНО СОСТОЯНИЕ ДНЯ — наряд DAY-FIX-2, Ч.3.
 *
 * Живой прогон 05.09: вкладка «План» говорила «Начать день», экран дня — «Продолжить · осталось 1»,
 * присест — «Сцена 11/57». Три экрана, три счёта. Теперь слово и минуты считает только сервер, и
 * все три пейлоада — плана, дня и посадки — несут одно и то же.
 */
beforeEach(function (): void {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

function planPayload(object $ctx, string $token, string $planId): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}")->assertOk()->json('data');
}

function dayPayload(object $ctx, string $token, string $planId, int $day): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}/days/{$day}")->assertOk()->json('data');
}

function dayOf(array $plan, int $index): array
{
    foreach ($plan['days'] as $day) {
        if ($day['index'] === $index) {
            return $day;
        }
    }

    throw new RuntimeException("no day {$index}");
}

it('calls a fresh day «не начат» on the plan, on the day and on the sitting — with the same minutes', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $plan = planPayload($this, $token, $planId);
    $day1 = dayOf($plan, 1);
    expect($day1['day_state'])->toBe('not_started')
        ->and($day1['minutes_left'])->toBeGreaterThan(0);

    $dayScreen = dayPayload($this, $token, $planId, 1);
    expect($dayScreen['day_state'])->toBe('not_started')
        ->and($dayScreen['minutes_left'])->toBe($day1['minutes_left']);

    $session = planSession($this, $token, $planId);
    expect($session['day_state'])->toBe('not_started')
        ->and($session['minutes_left'])->toBe($day1['minutes_left'])
        // The minutes ARE the sitting: cards × 16 s, rounded up to a whole minute.
        ->and($session['minutes_left'])->toBe((int) ceil(count($session['tasks']) * 16 / 60));

    // A day AHEAD of the focus is «не начат» too, priced by its own cards.
    expect(dayOf($plan, 2)['day_state'])->toBe('not_started')
        ->and(dayOf($plan, 2)['minutes_left'])->toBeGreaterThan(0);
});

it('calls a day «идёт» after the first answer, and «пройден» everywhere once its cards are met', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    // ONE card answered — the first non-intro one — and the day is in progress on every payload.
    $session = planSession($this, $token, $planId);
    $first = null;
    foreach ($session['tasks'] as $task) {
        if ($task['card']['exercise_mode'] !== 'intro') {
            $first = $task;

            break;
        }
    }
    expect($first)->not->toBeNull();
    answerTasks($this, $token, ['session_id' => $session['session_id'], 'tasks' => [$first]]);

    expect(dayOf(planPayload($this, $token, $planId), 1)['day_state'])->toBe('in_progress')
        ->and(dayPayload($this, $token, $planId, 1)['day_state'])->toBe('in_progress')
        ->and(planSession($this, $token, $planId)['day_state'])->toBe('in_progress');

    // EVERY card met — «пройден» on the plan and on the day WITHOUT re-entering: the census reads
    // the standings, not the row's status, so it does not wait for `POST /complete`.
    walkDay($this, $token, $planId, 1, 2);

    $day1 = dayOf(planPayload($this, $token, $planId), 1);
    expect($day1['day_state'])->toBe('done')
        ->and($day1['minutes_left'])->toBe(0)
        ->and(dayPayload($this, $token, $planId, 1)['day_state'])->toBe('done');
});

it('never prints a counter the screens could disagree on — one word, and the minutes are whole', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    foreach (planPayload($this, $token, $planId)['days'] as $day) {
        expect($day['day_state'])->toBeIn(['not_started', 'in_progress', 'done'])
            ->and($day['minutes_left'])->toBeInt()
            ->and($day['minutes_left'])->toBeGreaterThanOrEqual(0);
    }
});
