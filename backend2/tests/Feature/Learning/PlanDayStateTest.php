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

it('does not call a day «пройден» on its intros alone — the scene has to be spoken once', function () {
    // The live run of 05.09: every intro acknowledged, the dialogue still ahead — and the day
    // passed, the focus moved to day 2 in the middle of day 1's sitting. A scene line opens its
    // stage B the day it is met (DECISIONS п. 266), so «пройден» is the introduction AND the first
    // touch of the conversation.
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $session = planSession($this, $token, $planId);
    $intros = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['card']['exercise_mode'] === 'intro',
    ));
    $rest = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['card']['exercise_mode'] !== 'intro',
    ));
    expect($intros)->not->toBeEmpty()->and($rest)->not->toBeEmpty();

    $seq = answerTasks($this, $token, ['session_id' => $session['session_id'], 'tasks' => $intros]);

    $plan = planPayload($this, $token, $planId);
    expect(dayOf($plan, 1)['day_state'])->toBe('in_progress')
        ->and($plan['focus_day_index'])->toBe(1);

    answerTasks($this, $token, ['session_id' => $session['session_id'], 'tasks' => $rest], $seq);

    expect(dayOf(planPayload($this, $token, $planId), 1)['day_state'])->toBe('done');
});

it('names BOTH touches of a scene line on the day screen — met today, spoken today', function () {
    // «познакомишься · выберешь ответ»: the row's `next_step` is the intro, `then_step` the stage-B
    // exercise that follows in the same sitting. A word owes only its intro today, so it has none.
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $rows = dayPayload($this, $token, $planId, 1)['terms'];
    $byShelf = [];
    foreach ($rows as $row) {
        $byShelf[$row['shelf']][] = $row;
    }
    expect($byShelf)->toHaveKeys(['hear', 'say', 'ask', 'words']);

    foreach (['hear' => 'hear', 'say' => 'choose', 'ask' => 'assemble'] as $shelf => $then) {
        foreach ($byShelf[$shelf] as $row) {
            expect($row['next_step'])->toBe('meet', "{$shelf}: {$row['text']}")
                ->and($row['then_step'])->toBe($then, "{$shelf}: {$row['text']}");
        }
    }
    foreach ($byShelf['words'] as $row) {
        expect($row['then_step'])->toBeNull($row['text']);
    }
});

it('never prints a counter the screens could disagree on — one word, and the minutes are whole', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    foreach (planPayload($this, $token, $planId)['days'] as $day) {
        expect($day['day_state'])->toBeIn(['not_started', 'in_progress', 'done'])
            ->and($day['minutes_left'])->toBeInt()
            ->and($day['minutes_left'])->toBeGreaterThanOrEqual(0);
    }
});
