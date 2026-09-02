<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\PlanDefectReporter;
use App\Modules\Generation\Application\Port\RecordsPlanSpend;
use App\Modules\Generation\Application\Service\PlanDayComposer;
use App\Modules\Generation\Application\Service\PlanDayRepairer;
use App\Modules\Generation\Application\Service\PlanOutlineService;
use App\Modules\Generation\Domain\Service\PlanDayValidator;
use App\Modules\Generation\Infrastructure\Prompt\PlanPromptLibrary;
use App\Modules\Learning\Application\Port\PlanOutlinePort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Doubles\RecordingPlanDefectReporter;
use Tests\Doubles\ScriptedPlanModel;

uses(RefreshDatabase::class);

/**
 * A LINE HAS NO EXAMPLE — the contract, on the write.
 *
 * «Примеры только у `words` и `chunks`» (канон §7). The live day 1 of 02.09 had one on every one of
 * its thirteen lines, and the sentence the learner was shown for the card «I see, without
 * utilities.» was «When the power went out, I realized that I see, without utilities, life
 * becomes…» — the card word for word, padded into nonsense.
 *
 * Two places wrote them and both are pinned here: the day writer, when the model sends one anyway,
 * and the SERVER's own rescue kit, which carried a sentence out of the language pack.
 */
beforeEach(function (): void {
    /** @var array<string, mixed> $day */
    $day = json_decode(
        (string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-day1.v0.4.json'),
        true,
    );

    // The model, ignoring the contract on ONE card — everything else is the clean fixture, so the
    // day is written and the example is the only thing to look at.
    $day['say'][0]['example'] = 'At the hotel I need to check in, please, before eight.';
    $day['say'][0]['example_translation'] = 'В отеле мне нужно зарегистрироваться, пожалуйста, до восьми.';

    $this->defects = new RecordingPlanDefectReporter();
    $model = new ScriptedPlanModel([$day]);
    $prompts = new PlanPromptLibrary();
    $ledger = app(RecordsPlanSpend::class);

    app()->instance(PlanDefectReporter::class, $this->defects);
    app()->instance(PlanOutlinePort::class, new PlanOutlineService($model, $prompts, $ledger, $this->defects));
    app()->instance(PlanDayComposer::class, new PlanDayComposer(
        $model,
        $prompts,
        $ledger,
        $this->defects,
        new PlanDayRepairer($model, $prompts, $ledger),
    ));
});

it('writes no example for any line of a day — the model\'s, and the rescue kit\'s', function () {
    [, $token, $planId] = startedPlan($this);

    $collectionId = DB::table('learning_plan_days')
        ->where('plan_id', $planId)->where('day_index', 1)->value('collection_id');
    expect($collectionId)->not->toBeNull('the day was written');

    $lineExamples = DB::table('term_examples as e')
        ->join('terms as t', 't.id', '=', 'e.term_id')
        ->join('collection_items as ci', 'ci.term_id', '=', 't.id')
        ->where('ci.collection_id', $collectionId)
        ->where('t.kind', 'line')
        ->pluck('e.sentence')
        ->all();

    expect($lineExamples)->toBe([]);

    // …and the words and connectors still have theirs: this is a contract about lines, not a
    // deletion of examples.
    $wordExamples = DB::table('term_examples as e')
        ->join('terms as t', 't.id', '=', 'e.term_id')
        ->join('collection_items as ci', 'ci.term_id', '=', 't.id')
        ->where('ci.collection_id', $collectionId)
        ->whereIn('t.kind', ['word', 'chunk'])
        ->count();

    expect($wordExamples)->toBeGreaterThan(0);
});

it('counts the drop, so a prompt that keeps sending them is visible', function () {
    startedPlan($this);

    expect($this->defects->warnings(PlanDayValidator::LINE_EXAMPLE_DROPPED))->toBe(1)
        ->and($this->defects->reportedCounters())->toContain(PlanDayValidator::LINE_EXAMPLE_DROPPED);
});
