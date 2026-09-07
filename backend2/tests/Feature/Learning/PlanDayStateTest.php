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
    // МИНУТЫ — ПРО ДЕНЬ, А ПОСАДКА — ПРО ЭТАП (наряд DAY-GATE-1, Ч.1.4): сессия раздаёт «Слова и
    // фразы», а «около N минут» по-прежнему считает ВЕСЬ день, потому что на экране дня написано
    // именно это. Поэтому карточек в посадке меньше, чем минут дня, и это не расхождение.
    expect($session['day_state'])->toBe('not_started')
        ->and($session['stage'])->toBe('material')
        ->and($session['minutes_left'])->toBe($day1['minutes_left'])
        ->and($session['minutes_left'])->toBeGreaterThanOrEqual((int) ceil(count($session['tasks']) * 16 / 60));

    // ДЕНЬ ВПЕРЕДИ ФОКУСА ЕЩЁ НЕ НАПИСАН (наряд DAY-GATE-1, Ч.1.3): план пишется по одному дню, и
    // следующий встаёт в очередь по факту «день N пройден». Он «не начат», он ЗАПЕРТ, и минут у
    // него ноль — потому что карточек у него пока нет ни одной, а выдумывать их нечем.
    expect(dayOf($plan, 2)['day_state'])->toBe('not_started')
        ->and(dayOf($plan, 2)['status'])->toBe('pending')
        ->and(dayOf($plan, 2)['locked_by_day_index'])->toBe(1)
        ->and(dayOf($plan, 2)['minutes_left'])->toBe(0);
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

    $seq = answerTasks($this, $token, ['session_id' => $session['session_id'], 'tasks' => $rest], $seq);

    // …И ЭТОГО ВСЁ ЕЩЁ МАЛО: материал пройден, а разговор и прогон впереди (наряд DAY-GATE-1).
    expect(dayOf(planPayload($this, $token, $planId), 1)['day_state'])->toBe('material_done');

    walkDay($this, $token, $planId, 1, $seq);
    expect(dayOf(planPayload($this, $token, $planId), 1)['day_state'])->toBe('done');
});

it('names EVERY touch of a row on the day screen — met, exercised, spoken, in the order of the sitting', function () {
    // «познакомишься · соберёшь из блоков · выберешь ответ»: the row's `next_step` is the intro,
    // `then_steps` everything that follows in the same sitting (наряд DAY-FIX-3, Ч.5.1). A word
    // meets its translation choice, a connector its tiles; a question is assembled once for A and
    // once as its turn, and the screen says «соберёшь» once.
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $rows = dayPayload($this, $token, $planId, 1)['terms'];
    $byShelf = [];
    foreach ($rows as $row) {
        $byShelf[$row['shelf']][] = $row;
    }
    expect($byShelf)->toHaveKeys(['hear', 'say', 'ask', 'words']);

    foreach (['hear' => ['hear'], 'say' => ['assemble', 'choose'], 'ask' => ['assemble'], 'words' => ['translate'], 'chunks' => ['tiles']] as $shelf => $then) {
        foreach ($byShelf[$shelf] ?? [] as $row) {
            expect($row['next_step'])->toBe('meet', "{$shelf}: {$row['text']}")
                ->and($row['then_steps'])->toBe($then, "{$shelf}: {$row['text']}")
                ->and($row['then_step'])->toBe($then[0], "{$shelf}: {$row['text']}")
                ->and($row['mark'])->toBeNull("{$shelf}: {$row['text']}");
        }
    }
});

it('calls a day «материал пройден» between its two sittings, and prices each sitting apart', function () {
    // The two sittings of a day (наряд DAY-FIX-3, Ч.4): «Материал» — every card met and
    // exercised — then «Разговор». The word between them is the server's, and so are both minute
    // counts, on the plan and on the day.
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    // ПРИСЕСТ ТЕПЕРЬ ОДИН НА СЕССИЮ — это этап дня (наряд DAY-GATE-1, Ч.1.4), — а МИНУТЫ ОБОИХ
    // присестов по-прежнему считаются на весь день: их печатает экран дня, а не шапка посадки.
    $session = planSession($this, $token, $planId);
    expect($session['stage'])->toBe('material')
        ->and($session['sitting_plan'])->toHaveCount(1)
        ->and($session['sitting_plan'][0]['kind'])->toBe('material')
        ->and(array_column($session['sitting_plan'], 'cards'))->toBe($session['sittings'])
        ->and($session['material_minutes'])->toBeGreaterThan(0)
        ->and($session['conversation_minutes'])->toBeGreaterThan(0)
        ->and($session['minutes_left'])->toBeGreaterThanOrEqual($session['material_minutes']);

    $day1 = dayOf(planPayload($this, $token, $planId), 1);
    expect($day1['material_minutes'])->toBe($session['material_minutes'])
        ->and($day1['conversation_minutes'])->toBe($session['conversation_minutes']);

    // THE MATERIAL, whole — and the day says so everywhere, with the conversation still priced.
    answerTasks($this, $token, $session);

    $plan = planPayload($this, $token, $planId);
    expect(dayOf($plan, 1)['day_state'])->toBe('material_done')
        ->and(dayOf($plan, 1)['material_minutes'])->toBe(0)
        ->and(dayOf($plan, 1)['conversation_minutes'])->toBeGreaterThan(0)
        ->and($plan['focus_day_index'])->toBe(1)
        ->and(dayPayload($this, $token, $planId, 1)['day_state'])->toBe('material_done');

    // The rows of the material carry their marks: «применяешь» on what was exercised.
    foreach (dayPayload($this, $token, $planId, 1)['terms'] as $row) {
        if ($row['shelf'] === 'rescue') {
            continue;
        }
        expect($row['mark'])->toBeIn(['met', 'applying'], "{$row['shelf']}: {$row['text']}");
    }

    // A fresh sitting picks the day up at the conversation — nothing of the material comes back.
    $again = planSession($this, $token, $planId);
    expect($again['day_state'])->toBe('material_done')
        ->and($again['stage'])->toBe('conversation')
        ->and($again['sitting_plan'])->toHaveCount(1)
        ->and($again['sitting_plan'][0]['kind'])->toBe('conversation');
});

it('never prints a counter the screens could disagree on — one word, and the minutes are whole', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    foreach (planPayload($this, $token, $planId)['days'] as $day) {
        expect($day['day_state'])->toBeIn(['not_started', 'in_progress', 'material_done', 'done'])
            ->and($day['minutes_left'])->toBeInt()
            ->and($day['minutes_left'])->toBeGreaterThanOrEqual(0)
            ->and($day['material_minutes'] + $day['conversation_minutes'])->toBeGreaterThanOrEqual($day['minutes_left'] > 0 ? 1 : 0);
    }
});
