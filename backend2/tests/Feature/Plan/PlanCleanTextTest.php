<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * `plan:clean-text` (наряд LANG-1b §6): «команда plan:clean-text --dry-run → --apply по бою (с бэкапом): число исправленных
 * строк в отчёт». The plan is built by the fake model, which — like every answer stored before the наряд — skips the
 * adapter that now reads the model's text through {@see App\Modules\Plan\Domain\Service\ModelText}: the live day's title
 * «Опы\u{0004}т и навыки», a soft hyphen in a phrase of the lesson, a zero-width space in a dealt card.
 */

/** @return array{0: string, 1: string} the plan and its first scene, the scene titled as the live day was */
function pctDirtyPlan(object $ctx): array
{
    $fake = new FakePlanModel(
        plan: static function ($request): array {
            $p = FakePlanModel::planPayload($request);
            $p['scenes'][0]['title_native'] = "Опы\u{0004}т и навыки";

            return $p;
        },
        lesson: static function ($request): array {
            $p = planCleanLesson($request);
            $p['dialogue'][0]['messages'][0]['text_native'] = "Где бо\u{00AD}лит: вверху или внизу спины?";

            return $p;
        },
    );
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();
    $id = planCreate($ctx, $token, ['days_total' => 1])['id'];
    $scene = (string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('id');
    // The photo vendor's author: a Persian name keeps its non-joiner — not the model's text, never touched.
    DB::table('plan_scenes')->where('id', $scene)->update(['image_author' => "Sed\u{200C} \"Creatives\" Sardar"]);

    return [$id, $scene];
}

// CATCHES a dry run that writes, an apply that leaves a dirty text or touches the vendor's author name, a JSON column not read
// (the lesson as the model wrote it), a count that says nothing, and a second run that finds something again.
it('prints what it would clean, cleans it on --apply, leaves the vendor\'s names alone, and finds nothing the second time', function () {
    [, $scene] = pctDirtyPlan($this);

    Artisan::call('plan:clean-text', ['--dry-run' => true]);
    $dry = Artisan::output();
    expect($dry)->toContain('[dry-run] plan_scenes · '.$scene.' · title_native: «Опы⟨U+0004⟩т и навыки» → «Опыт и навыки»')
        ->and($dry)->toContain('lesson_json')
        ->and($dry)->toContain('[dry-run] Would fix rows: ')
        ->and(DB::table('plan_scenes')->where('id', $scene)->value('title_native'))->toBe("Опы\u{0004}т и навыки");

    Artisan::call('plan:clean-text', ['--apply' => true]);
    $applied = Artisan::output();
    $row = DB::table('plan_scenes')->where('id', $scene)->first();
    $lesson = json_decode((string) $row->lesson_json, true);

    expect($applied)->toMatch('/Fixed rows: [1-9]\d* \(plans 0, plan_scenes 1, plan_terms \d+, day_cards \d+\) · fields: \d+/')
        ->and($row->title_native)->toBe('Опыт и навыки')
        ->and($lesson['dialogue'][0]['messages'][0]['text_native'])->toBe('Где болит: вверху или внизу спины?')
        ->and($row->image_author)->toBe("Sed\u{200C} \"Creatives\" Sardar");

    Artisan::call('plan:clean-text', ['--apply' => true]);
    expect(Artisan::output())->toContain('Fixed rows: 0 (plans 0, plan_scenes 0, plan_terms 0, day_cards 0) · fields: 0');
});

it('refuses --dry-run and --apply together', function () {
    expect(Artisan::call('plan:clean-text', ['--dry-run' => true, '--apply' => true]))->toBe(1);
});
