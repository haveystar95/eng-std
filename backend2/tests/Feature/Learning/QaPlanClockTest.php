<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * ДЕВ-ДВЕРЬ СМЕНЫ ДНЕЙ — наряд DAY-FIX-2.
 *
 * Та же дверь, что у входа без пароля и у подстановки транскрипта: аккаунт `is_qa` И среда не
 * production при включённом флаге. Сдвиг живёт в кэше, не в таблице; пока он стоит, «сегодня» этого
 * аккаунта — на N дней впереди, а даты, которые ставит телефон, сдвигаются так же на входе.
 */
beforeEach(function (): void {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

it('is a 404 for an ordinary account, door or no door', function () {
    config()->set('qa.dev_login', true);
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/qa/plan-clock', ['days' => 1])->assertNotFound();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/qa/plan-clock')->assertNotFound();
});

it('is a 404 for a QA account when the door itself is shut', function () {
    config()->set('qa.dev_login', false);
    [$user, $token] = learner();
    $user->forceFill(['is_qa' => true])->save();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/qa/plan-clock', ['days' => 1])->assertNotFound();
});

it('moves a QA account`s «today» forward: the night passes without ageing a single row', function () {
    config()->set('qa.dev_login', true);
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);
    $user->forceFill(['is_qa' => true])->save();
    app('auth')->forgetGuards();

    $seq = walkDay($this, $token, $planId, 1);

    // Same day, no shift: the focus has moved to day 2 and the seam is not there yet — the
    // night has not passed for scene 1's lines.
    $before = planSession($this, $token, $planId);
    expect($before['day_index'])->toBe(2)
        ->and(array_filter($before['tasks'], static fn (array $t): bool => $t['section'] === 'review'))->toBe([]);

    // Shift by one day…
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/qa/plan-clock', ['days' => 1])->assertOk()->assertJsonPath('data.days', 1);
    expect($this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/qa/plan-clock')->assertOk()->json('data.days'))->toBe(1);

    // …and the night has passed: scene 1 comes back in the seam, by assembly.
    $after = planSession($this, $token, $planId);
    $seam = array_values(array_filter($after['tasks'], static fn (array $t): bool => $t['section'] === 'review'));
    expect($seam)->not->toBeEmpty();

    // AN ANSWER GIVEN UNDER THE SHIFT LANDS ON THE SHIFTED DAY, so «closed today» stays today.
    answerTasks($this, $token, ['session_id' => $after['session_id'], 'tasks' => [$seam[0]]], $seq);
    $stored = DB::table('reviews')->where('user_id', $user->id)->where('term_id', $seam[0]['card']['term_id'])
        ->orderByDesc('client_seq')->value('answered_at');
    expect(substr((string) $stored, 0, 10))->toBe(now()->addDay()->format('Y-m-d'));

    // Not a row of the log was rewritten: the earlier answers still carry the real day.
    $earliest = DB::table('reviews')->where('user_id', $user->id)->orderBy('client_seq')->value('answered_at');
    expect(substr((string) $earliest, 0, 10))->toBe(now()->format('Y-m-d'));

    // Zero clears it.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/qa/plan-clock', ['days' => 0])->assertOk()->assertJsonPath('data.days', 0);
});
