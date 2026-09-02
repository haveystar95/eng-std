<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\RecordsPlanSpend;
use App\Modules\Generation\Application\Service\PlanDayComposer;
use App\Modules\Generation\Application\Service\PlanDayRepairer;
use App\Modules\Generation\Application\Service\PlanOutlineService;
use App\Modules\Generation\Infrastructure\Prompt\PlanPromptLibrary;
use App\Modules\Learning\Application\Port\PlanOutlinePort;
use App\Modules\Learning\Domain\Entity\PlanDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Doubles\RecordingPlanDefectReporter;
use Tests\Doubles\ScriptedPlanModel;

uses(RefreshDatabase::class);

/**
 * «СОБРАТЬ ЗАНОВО» — the one move a learner has when a day burns.
 *
 * The owner's live plan of 02.09 stopped here: day 1 walked, day 2 refused twice
 * (`card.translation_missing_key`), and the only button on the screen was «Собрать план заново» —
 * which throws day 1 away and buys a new skeleton. This endpoint gives the ONE day its attempt
 * back, and nothing else.
 *
 * The fixture answers with a broken day twice and a clean one after that: the first two calls burn
 * the day exactly as production does, and the rebuild is what reaches the third.
 */
beforeEach(function (): void {
    /** @var array<string, mixed> $day */
    $day = json_decode(
        (string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-day1.v0.4.json'),
        true,
    );

    // Broken past the repair threshold, so both attempts go back whole and the day ends `failed`.
    $broken = $day;
    foreach ([0, 1, 2, 3] as $i) {
        $broken['words'][$i]['example'] = $day['words'][$i]['text'];
    }
    foreach ([0, 1] as $i) {
        $broken['chunks'][$i]['example'] = $day['chunks'][$i]['text'];
    }
    foreach ([0, 1, 2, 3] as $i) {
        $broken['say'][$i]['translation'] = 'У меня всё в порядке.';
    }

    $this->defects = new RecordingPlanDefectReporter();
    $this->model = new ScriptedPlanModel([$broken, $broken, $day]);
    $prompts = new PlanPromptLibrary();
    $ledger = app(RecordsPlanSpend::class);

    app()->instance(PlanOutlinePort::class, new PlanOutlineService($this->model, $prompts, $ledger, $this->defects));
    app()->instance(PlanDayComposer::class, new PlanDayComposer(
        $this->model,
        $prompts,
        $ledger,
        $this->defects,
        new PlanDayRepairer($this->model, $prompts, $ledger),
    ));
});

it('gives a burned day one more attempt, and it comes back ready', function () {
    [, $token, $planId] = startedPlan($this);

    $before = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->first();
    expect($before->status)->toBe('failed')
        ->and($before->generation_attempts)->toBe(PlanDay::MAX_ATTEMPTS);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$planId}/days/1/rebuild")
        ->assertOk()
        ->assertJsonPath('data.day_index', 1);

    $after = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->first();

    expect($after->status)->toBe('ready')
        ->and($after->collection_id)->not->toBeNull()
        ->and($after->fail_reason)->toBeNull()
        // One attempt back, one attempt spent: the cap still holds after the rebuild.
        ->and($after->generation_attempts)->toBe(PlanDay::MAX_ATTEMPTS);
});

it('does not reopen a day that has not burned — a second tap costs nothing', function () {
    [, $token, $planId] = startedPlan($this);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$planId}/days/1/rebuild")->assertOk();
    $calls = $this->model->dayCalls();

    // The day is `ready` now. Tapping again must not buy anything.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$planId}/days/1/rebuild")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready');

    expect($this->model->dayCalls())->toBe($calls);
});

it('refuses a plan that is not the caller\'s', function () {
    [, , $planId] = startedPlan($this);
    [, $other] = learner();

    // The guard caches the user it resolved for the FIRST request of a test, and every request in
    // one test shares an application instance — without this the second bearer token is never
    // looked at and the assertion would pass for the wrong reason.
    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$other}")
        ->postJson("/api/v1/plans/{$planId}/days/1/rebuild")
        ->assertNotFound();
});
