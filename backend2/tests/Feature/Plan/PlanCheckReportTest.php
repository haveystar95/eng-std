<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `plan:check-report` (наряд CHECK-1): what the validator finds on the written days, by code — off the stored findings
 * and the counters, read-only.
 */

// Canon CHECK-1: «по каждому коду — срабатываний, из них фатальных, дней ушло в failed, доля от дней, 3 примера текста с
// планом и днём; ключ --since». Catches a report that counts a fatal code as a warning, misses a failed day, prints more
// than three examples, or ignores --since.
it('reports every code with its findings, fatal ones, days, failed days, share, counters and three examples', function () {
    // The test database keeps the days of earlier runs (the timestamps are whole seconds): the report is read since this
    // test began, and the failed day an earlier run of this very test left is put back to pending first.
    $since = now()->format(DATE_ATOM);
    DB::table('plan_scenes')->where('fail_reason', 'fatal: filler.ungrammatical')->update(['lesson_status' => 'pending', 'fail_reason' => null, 'build_started_at' => null, 'checks_json' => '[]']);
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 3])['id'];
    $scenes = DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->get();
    // Day 1 is written at once; the later scenes wait for their day (GEN-3 §11).
    expect($scenes->where('lesson_status', 'ready')->count())->toBe(1)
        ->and($scenes->where('lesson_status', 'pending')->count())->toBeGreaterThanOrEqual(1);

    // One day failed on a fatal code, with the model's answer's findings stored beside it — as a failed day is stored:
    // no `generated_at`, the build's start as its moment.
    $failed = $scenes->where('lesson_status', 'pending')->first();
    DB::table('plan_scenes')->where('id', $failed->id)->update([
        'lesson_status' => 'failed',
        'fail_reason' => 'fatal: filler.ungrammatical',
        'build_started_at' => now(),
        'checks_json' => json_encode([
            ['code' => 'filler.ungrammatical', 'address' => 'p6.f1', 'detail' => '«I\'d like the 3 p.m. appointment.»: the filler carries its own punctuation'],
            ['code' => 'filler.ungrammatical', 'address' => 'p6.f2', 'detail' => '«I\'d like the 5:30 p.m. appointment.»: the filler carries its own punctuation'],
            ['code' => 'partner.too_long', 'address' => 'A5', 'detail' => '«We have 3 p.m. and 5:30 p.m. today.» has 3 sentences (max 2)'],
            ['code' => 'partner.too_long', 'address' => 'A6', 'detail' => 'second'],
            ['code' => 'partner.too_long', 'address' => 'A7', 'detail' => 'third'],
            ['code' => 'partner.too_long', 'address' => 'A8', 'detail' => 'fourth'],
        ], JSON_UNESCAPED_UNICODE),
    ]);
    // The counters of every attempt, two prompt versions — the report adds them up.
    DB::table('plan_check_counters')->where('check_name', 'filler.ungrammatical')->delete();
    foreach ([['lesson_day.v4.6', 'counted', 2], ['lesson_day.v4.7', 'counted', 3], ['lesson_day.v4.7', 'gated', 5], ['lesson_day.v4.7', 'failed', 2]] as [$version, $action, $hits]) {
        DB::table('plan_check_counters')->insert(['id' => (string) Str::ulid(), 'prompt_version' => $version, 'check_name' => 'filler.ungrammatical', 'action' => $action, 'hits' => $hits, 'updated_at' => now()]);
    }
    $days = DB::table('plan_scenes')->whereIn('lesson_status', ['ready', 'failed'])->whereRaw('coalesce(generated_at, build_started_at, updated_at) >= ?', [$since])->count();
    $dayNumber = (int) DB::table('plan_days')->where('scene_id', $failed->id)->value('number');

    Artisan::call('plan:check-report', ['--since' => $since]);
    $report = Artisan::output();

    expect($report)
        ->toMatch('/filler\.ungrammatical\s*\|\s*2\s*\|\s*2\s*\|\s*1\s*\|\s*1\s*\|\s*'.preg_quote(number_format(100 / $days, 1), '/').' %\s*\|\s*5 \/ 5 \/ 2\s*\|/')
        ->toMatch('/partner\.too_long\s*\|\s*4\s*\|\s*0\s*\|\s*1\s*\|\s*0\s*\|\s*[\d.]+ %\s*\|\s*0 \/ 0 \/ 0\s*\|/')
        ->toContain("Days with a lesson: {$days} since")
        ->toContain('filler.ungrammatical (fatal)')
        ->toContain("«I'd like the 3 p.m. appointment.»: the filler carries its own punctuation — «".$failed->title_native.'», day '.$dayNumber)
        ->toContain('(failed)')
        ->toContain('second')->toContain('third')->not->toContain('fourth');
    // The fixture's own warning stands on the ready days, on none of them failed.
    expect($report)->toMatch('/frame\.adjacent_repeat\s*\|\s*\d+\s*\|\s*0\s*\|\s*\d+\s*\|\s*0\s*\|/');

    Artisan::call('plan:check-report', ['--since' => '2099-01-01']);
    expect(Artisan::output())->toContain('No day with a lesson since 2099-01-01');

    Artisan::call('plan:check-report', ['--since' => '2000-01-01']);
    expect(Artisan::output())->toContain('filler.ungrammatical');

    expect(Artisan::call('plan:check-report', ['--since' => 'not a date']))->toBe(1);
});
