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
 * P2R BY HAND — `plan:repair-card` (наряд GEN-2a): one card of a stored lesson, by its address and what the
 * validator finds at it — a warning, since a fatal finding never reaches a stored lesson (`LessonGateBuildTest`);
 * nothing written without `--apply`; with it the repaired answer replaces the stored one, the unit rows keep their
 * ids and photos, and a dealt day is never rewritten.
 */

/** A built one-day plan whose lesson writes alternatives into a native frame, and the model's repair of it. */
function lrBuild(object $ctx, ?Closure $repair = null): array
{
    $fake = new FakePlanModel(
        lesson: static function ($request): array {
            $p = FakePlanModel::lessonPayload($request);
            $p['phrases'][0]['frame_native'] = 'У него болит в/на ___.';

            return $p;
        },
        repair: $repair ?? static function ($request): array {
            $card = $request->card;
            $card['frame_native'] = 'У него болит ___.';

            return ['card' => $card];
        },
    );
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();
    $id = planCreate($ctx, $token, ['days_total' => 1])['id'];

    return [$fake, $token, $id, (string) DB::table('plan_scenes')->where('plan_id', $id)->value('id')];
}

it('shows a repaired card and its findings before and after, and writes nothing without --apply', function () {
    [$fake, , , $sceneId] = lrBuild($this);
    $before = DB::table('plan_scenes')->where('id', $sceneId)->first();

    Artisan::call('plan:repair-card', ['scene' => $sceneId, 'address' => 'p1.f2']);
    $output = Artisan::output();

    expect($fake->repairCalls)->toBe(1)
        ->and($fake->repairRequests[0]->address)->toBe('p1')
        ->and($fake->repairRequests[0]->kind)->toBe('frame')
        ->and(array_column($fake->repairRequests[0]->findings, 'code'))->toBe(['frame.native_alternatives'])
        // The model is told what is broken in English, never shown another card's text as a finding.
        ->and($fake->repairRequests[0]->findings[0]['detail'])->toStartWith('p1: «У него болит в/на ___.»')
        ->and($output)->toContain('status: repaired')
        ->and($output)->toContain('findings in the lesson: 1 → 0')
        ->and($output)->toContain('not written (no --apply)')
        ->and(DB::table('plan_scenes')->where('id', $sceneId)->first())->toEqual($before);
});

it('writes the repaired lesson with --apply, keeping every unit row and its photo', function () {
    [, , , $sceneId] = lrBuild($this);
    $terms = DB::table('plan_terms')->where('scene_id', $sceneId)->orderBy('ref')->get(['id', 'ref', 'image_url'])->all();

    Artisan::call('plan:repair-card', ['scene' => $sceneId, 'address' => 'p1', '--apply' => true]);

    $lesson = json_decode((string) DB::table('plan_scenes')->where('id', $sceneId)->value('lesson_json'), true);
    expect(Artisan::output())->toContain('written')
        ->and($lesson['phrases'][0]['frame_native'])->toBe('У него болит ___.')
        ->and(json_decode((string) DB::table('plan_scenes')->where('id', $sceneId)->value('checks_json'), true))->toBe([])
        ->and(DB::table('plan_terms')->where('scene_id', $sceneId)->orderBy('ref')->get(['id', 'ref', 'image_url'])->all())->toEqual($terms)
        ->and(DB::table('plan_terms')->where('scene_id', $sceneId)->where('ref', 'p1')->value('frame_native'))->toBe('У него болит ___.');
});

// Catches a repair written under the cards a learner already holds.
it('refuses to write a repair into a lesson whose day is dealt', function () {
    [, $token, $id, $sceneId] = lrBuild($this);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    planOpenDay($this, $token, $id, 1);
    $before = DB::table('plan_scenes')->where('id', $sceneId)->value('lesson_json');

    $exit = Artisan::call('plan:repair-card', ['scene' => $sceneId, 'address' => 'p1', '--apply' => true]);

    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('already dealt into day 1')
        ->and(DB::table('plan_scenes')->where('id', $sceneId)->value('lesson_json'))->toBe($before);
});

it('asks nothing for a card the validator finds nothing at, or an address that is no card', function () {
    [$fake, , , $sceneId] = lrBuild($this);

    Artisan::call('plan:repair-card', ['scene' => $sceneId, 'address' => 'x3.check']);
    $nothing = Artisan::output();
    Artisan::call('plan:repair-card', ['scene' => $sceneId, 'address' => 'A3']);

    expect($fake->repairCalls)->toBe(0)
        ->and($nothing)->toContain('status: nothing_to_repair')
        ->and(Artisan::output())->toContain('status: not_a_card');
});

it('keeps the stored lesson when the repaired card is not the card\'s shape', function () {
    [$fake, , , $sceneId] = lrBuild($this, static fn (): array => ['card' => ['id' => 'p1']]);
    $before = DB::table('plan_scenes')->where('id', $sceneId)->value('lesson_json');

    $exit = Artisan::call('plan:repair-card', ['scene' => $sceneId, 'address' => 'p1', '--apply' => true]);

    expect($exit)->toBe(1)
        ->and($fake->repairCalls)->toBe(1)
        ->and(Artisan::output())->toContain('status: off_schema')
        ->and(DB::table('plan_scenes')->where('id', $sceneId)->value('lesson_json'))->toBe($before);
});
