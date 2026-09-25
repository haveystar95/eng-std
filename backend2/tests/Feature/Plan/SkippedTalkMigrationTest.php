<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * THE COLUMN GOES, THE FIVE-STAGE DAYS STAY WHOLE (наряд ACC-1 §3, `2026_09_25_120000_drop_has_conversation_from_plan_days`).
 * «День на пяти этапах — не ломать: перевести на шесть с этапом conversation в состоянии „пропущен" (не пройден, без
 * возврата)». Every dealt day of five stages — in progress or closed — gets its sixth stage skipped, and only those: not
 * a day dealt with the talk, not a day not dealt yet. The talk of such a day is refused, and the way down gives the
 * column back with every day's own value and takes the skipped passages out. Catches a five-stage day left without its
 * skip (never closable), a skip written over a day that has the talk, and a `down()` that cannot find its way back.
 */
it('skips the talk of every day dealt without it, drops the column, and takes both back on the way down', function () {
    $migration = require base_path('app/Modules/Plan/Infrastructure/Migration/2026_09_25_120000_drop_has_conversation_from_plan_days.php');
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 4])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    planOpenDay($this, $token, $id, 1);
    $day = static fn (int $n): string => (string) DB::table('plan_days')->where('plan_id', $id)->where('number', $n)->value('id');

    // The stand as the migration finds it: day 1 in progress on five stages, day 2 closed on five (both dealt before the
    // talk existed), day 3 dealt WITH the talk, day 4 not dealt yet.
    $migration->down();
    DB::table('plan_days')->where('id', $day(1))->update(['has_conversation' => false]);
    DB::table('plan_days')->where('id', $day(2))->update(['status' => 'closed', 'opened_at' => '2026-09-14 10:00:00+00', 'closed_at' => '2026-09-14 11:00:00+00', 'has_conversation' => false]);
    DB::table('plan_days')->where('id', $day(3))->update(['status' => 'closed', 'opened_at' => '2026-09-22 10:00:00+00', 'closed_at' => '2026-09-22 11:00:00+00', 'has_conversation' => true]);

    $migration->up();

    $skips = DB::table('plan_stage_passages')->whereNull('conversation_id')->orderBy('passed_at')->get();
    expect(Schema::hasColumn('plan_days', 'has_conversation'))->toBeFalse()
        ->and($skips->pluck('day_id')->all())->toBe([$day(2), $day(1)])
        ->and($skips->pluck('stage')->unique()->all())->toBe(['conversation'])
        ->and(substr((string) $skips->first()->passed_at, 0, 10))->toBe('2026-09-14');
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/conversation")
        ->assertStatus(422)->assertJsonPath('code', 'plan_conversation_not_in_day');

    $migration->down();

    expect(DB::table('plan_days')->where('plan_id', $id)->orderBy('number')->pluck('has_conversation')->all())->toBe([false, false, true, false])
        ->and(DB::table('plan_stage_passages')->whereNull('conversation_id')->count())->toBe(0);
});
