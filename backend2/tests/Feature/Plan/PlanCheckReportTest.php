<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `plan:check-report` (наряд CHECK-1; since GEN-4 the checks of the skeleton and the dialogue): what the day's checks find
 * on the written days, by code — off the stored findings and the counters, read-only.
 */

// The rows of this test go with it (наряд ACC-1 §4): it used to keep its failed day and its counters in the test
// database, and a serial run of the folder read them in the tests after it — the purge migration's canon among them.
uses(RefreshDatabase::class);

// Canon CHECK-1: «по каждому коду — срабатываний, из них фатальных, дней ушло в failed, доля от дней, 3 примера текста с
// планом и днём; ключ --since». Catches a report that counts a fatal code as a warning, misses a failed day, prints more
// than three examples, or ignores --since.
it('reports every code with its findings, fatal ones, days, failed days, share, counters and three examples', function () {
    // The report is read since this test began (the timestamps are whole seconds).
    $since = now()->format(DATE_ATOM);
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
        'fail_reason' => 'fatal: line.ne_frame',
        'build_started_at' => now(),
        'checks_json' => json_encode([
            ['code' => 'line.ne_frame', 'address' => 'B6', 'detail' => 'the target line «I\'d like the 3 p.m. slot.» is not «I\'d like the 3 p.m. appointment» (p6 with «the 3 p.m.»)'],
            ['code' => 'line.ne_frame', 'address' => 'B7', 'detail' => 'the target line «I\'d like 5:30.» is not «I\'d like the 5:30 p.m. appointment» (p6 with «the 5:30 p.m.»)'],
            ['code' => 'partner.too_long', 'address' => 'a5', 'detail' => '«We have 3 p.m. and 5:30 p.m. today, and 6 p.m. is the last slot of the evening for a first visit.» has 19 words (at most 18)'],
            ['code' => 'partner.too_long', 'address' => 'a6', 'detail' => 'second'],
            ['code' => 'partner.too_long', 'address' => 'a7', 'detail' => 'third'],
            ['code' => 'partner.too_long', 'address' => 'a8', 'detail' => 'fourth'],
        ], JSON_UNESCAPED_UNICODE),
    ]);
    // The counters of every attempt, two prompt versions — the report adds them up.
    DB::table('plan_check_counters')->where('check_name', 'line.ne_frame')->delete();
    foreach ([['lesson_day.v4.10', 'counted', 2], ['lesson_dialogue.v1.1', 'counted', 3], ['lesson_dialogue.v1.1', 'gated', 5], ['lesson_dialogue.v1.1', 'failed', 2]] as [$version, $action, $hits]) {
        DB::table('plan_check_counters')->insert(['id' => (string) Str::ulid(), 'prompt_version' => $version, 'check_name' => 'line.ne_frame', 'action' => $action, 'hits' => $hits, 'updated_at' => now()]);
    }
    $days = DB::table('plan_scenes')->whereIn('lesson_status', ['ready', 'failed'])->whereRaw('coalesce(generated_at, build_started_at, updated_at) >= ?', [$since])->count();
    $dayNumber = (int) DB::table('plan_days')->where('scene_id', $failed->id)->value('number');

    Artisan::call('plan:check-report', ['--since' => $since]);
    $report = Artisan::output();

    expect($report)
        ->toMatch('/line\.ne_frame\s*\|\s*2\s*\|\s*2\s*\|\s*1\s*\|\s*1\s*\|\s*'.preg_quote(number_format(100 / $days, 1), '/').' %\s*\|\s*5 \/ 5 \/ 2\s*\|/')
        ->toMatch('/partner\.too_long\s*\|\s*4\s*\|\s*0\s*\|\s*1\s*\|\s*0\s*\|\s*[\d.]+ %\s*\|\s*0 \/ 0 \/ 0\s*\|/')
        ->toContain("Days with a lesson: {$days} since")
        ->toContain('line.ne_frame (fatal)')
        ->toContain('partner.too_long')->not->toContain('partner.too_long (fatal)')
        ->toContain("the target line «I'd like the 3 p.m. slot.» is not «I'd like the 3 p.m. appointment» (p6 with «the 3 p.m.») — «".$failed->title_native.'», day '.$dayNumber)
        ->toContain('(failed)')
        ->toContain('second')->toContain('third')->not->toContain('fourth');
    // The fixture's day is clean (наряд GEN-4): its ready days add no finding of their own.
    expect(preg_match_all('/^\|\s*[a-z_]+\.[a-z_.]+\s*\|/m', $report))->toBe(2);

    // The seam judge that did not answer is a counter, no finding — the report prints it under the table. CATCHES a judge
    // gone quiet invisible to whoever reads the report.
    foreach ([['counted', 3], ['failed', 1]] as [$action, $hits]) {
        DB::table('plan_check_counters')->insert(['id' => (string) Str::ulid(), 'prompt_version' => 'lesson_seam_judge.v1.1', 'check_name' => App\Modules\Plan\Domain\Check\LessonCodes::JUDGE_UNAVAILABLE, 'action' => $action, 'hits' => $hits, 'updated_at' => now()]);
    }
    Artisan::call('plan:check-report', ['--since' => $since]);
    expect(Artisan::output())->toContain('The counter that is no finding')->toContain(App\Modules\Plan\Domain\Check\LessonCodes::JUDGE_UNAVAILABLE.': 3 / 0 / 1');

    Artisan::call('plan:check-report', ['--since' => '2099-01-01']);
    expect(Artisan::output())->toContain('No day with a lesson since 2099-01-01');

    Artisan::call('plan:check-report', ['--since' => '2000-01-01']);
    expect(Artisan::output())->toContain('line.ne_frame');

    expect(Artisan::call('plan:check-report', ['--since' => 'not a date']))->toBe(1);
});
