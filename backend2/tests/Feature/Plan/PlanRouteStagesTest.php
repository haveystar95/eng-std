<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * THE ROUTE KNOWS EACH DAY'S STAGES, AND THE PLAN SAYS ITSELF IN ONE SENTENCE (PLAN-UI-3).
 * The rules themselves are unit-tested in tests/Unit/Plan/RouteStagesTest.php and PlanSummaryTest.php;
 * here — that the wire carries them, from the right source, without a query per day.
 */

/** @return list<array{0: string, 1: string}> */
function routeWire(array $day): array
{
    return array_map(static fn (array $s): array => [$s['stage'], $s['state']], $day['stages']);
}

function routeCurrent(object $ctx, string $token): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/current')->assertOk()->json('data');
}

it('puts every day’s stages on the route: the day open today at its first stage, the rest by what they will deal', function () {
    [, $token] = planLearner();
    $build = planCreate($this, $token);

    // Built, not started: nothing may be walked today, so nothing is current.
    $ready = routeCurrent($this, $token);
    expect(array_unique(array_column(routeWire($ready['days'][0]), 1)))->toBe(['locked']);

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$build['id']}/start")->assertOk();
    $plan = routeCurrent($this, $token);

    // Six nodes since наряд CONV-1 — the talk is the last of them, on a scene day, a review and the rehearsal alike.
    $six = [
        ['words', 'current'], ['phrases', 'locked'], ['dialogue', 'locked'],
        ['listen', 'locked'], ['speak', 'locked'], ['conversation', 'locked'],
    ];
    expect(routeWire($plan['days'][0]))->toBe($six)
        ->and(routeWire($plan['current_day']))->toBe($six)
        ->and(array_column(routeWire($plan['days'][1]), 1))->toBe(['locked', 'locked', 'locked', 'locked', 'locked', 'locked'])
        // A review: «Повторение», its own stage — not «Говорю сам» (наряд BACK-TAILS-2 §3).
        ->and(routeWire($plan['days'][2]))->toBe([['words', 'locked'], ['repetition', 'locked'], ['conversation', 'locked']])
        ->and(routeWire($plan['days'][4]))->toBe([['recall', 'locked'], ['conversation', 'locked']]);

    // The day room's own `day` says the same.
    $room = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$build['id']}/days/1")->assertOk()->json('data');
    expect(routeWire($room['day']))->toBe($six);
});

it('reads a day being walked off its cards, and a closed day as all done', function () {
    [, $token] = planLearner();
    $build = planCreate($this, $token, ['days_total' => 2]);
    $id = $build['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    $cards = planOpenDay($this, $token, $id, 1)['cards'];
    foreach (array_filter($cards, static fn (array $c): bool => $c['stage'] === 'words') as $card) {
        planAnswer($this, $token, $id, 1, $card['id'], 'passed');
    }
    expect(routeWire(routeCurrent($this, $token)['days'][0]))->toBe([
        ['words', 'done'], ['phrases', 'current'], ['dialogue', 'locked'],
        ['listen', 'locked'], ['speak', 'locked'], ['conversation', 'locked'],
    ]);

    planWalkDay($this, $token, $id, 1);
    $plan = routeCurrent($this, $token);
    expect(array_unique(array_column(routeWire($plan['days'][0]), 1)))->toBe(['done'])
        // Day 2 opens tomorrow: its stages are known, none of them is today's.
        ->and($plan['days'][1]['status'])->toBe('locked')
        ->and(array_unique(array_column(routeWire($plan['days'][1]), 1)))->toBe(['locked']);

    planShiftDay($id);
    expect(routeWire(routeCurrent($this, $token)['days'][1])[0])->toBe(['words', 'current']);
});

it('counts the whole route’s stages in one query, however many days are dealt', function () {
    [, $token] = planLearner();
    $build = planCreate($this, $token, ['days_total' => 5]);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$build['id']}/start")->assertOk();
    planOpenDay($this, $token, $build['id'], 1);

    DB::flushQueryLog();
    DB::enableQueryLog();
    routeCurrent($this, $token);
    $cardQueries = array_filter(DB::getQueryLog(), static fn (array $q): bool => str_contains($q['query'], 'day_cards'));
    DB::disableQueryLog();

    expect(count($cardQueries))->toBe(1);
});

it('says the plan in one sentence: the scene days in route order, never a review or the rehearsal', function () {
    [, $token] = planLearner();
    $year = (int) date('Y') + 1;
    $build = planCreate($this, $token, ['event_date' => "{$year}-09-17"]);
    $plan = planRead($this, $token, $build['id']);

    expect(array_column($plan['days'], 'type'))->toBe(['scene', 'scene', 'review', 'scene', 'rehearsal'])
        ->and($plan['summary'])->toBe('Запись к врачу, приём у врача, аптека. К 17 сентября скажешь всё это сам');

    // The first scene leaves the preview: its day is a review now, and it is not named.
    $first = $plan['days'][0]['scene_id'];
    $after = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/plans/{$build['id']}/scenes/{$first}")->assertOk()->json('data');
    expect($after['summary'])->toBe('Приём у врача, аптека. К 17 сентября скажешь всё это сам');

    // No date: the promise loses it. (A reschedule lays the route out again and writes a scene for
    // the emptied day, so the names are read off the route it produced — scene days only.)
    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/plans/{$build['id']}/schedule", ['event_date' => null])->assertOk();
    $undated = planRead($this, $token, $build['id']);
    $names = array_slice(array_column(array_values(array_filter($undated['days'], static fn (array $d): bool => $d['type'] === 'scene')), 'title_native'), 0, 3);
    $joined = mb_strtolower(implode(', ', $names));
    expect($undated['summary'])->toBe(mb_strtoupper(mb_substr($joined, 0, 1)).mb_substr($joined, 1).'. Скажешь всё это сам');
});
