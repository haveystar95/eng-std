<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\RecordsPlanSpend;
use App\Modules\Generation\Application\Service\PlanDayComposer;
use App\Modules\Generation\Application\Service\PlanDayRepairer;
use App\Modules\Generation\Application\Service\PlanOutlineService;
use App\Modules\Generation\Application\Service\PlanPairCourt;
use App\Modules\Generation\Domain\Service\PlanDayValidator;
use App\Modules\Generation\Infrastructure\Prompt\PlanPromptLibrary;
use App\Modules\Learning\Application\Port\PlanOutlinePort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Doubles\RecordingPlanDefectReporter;
use Tests\Doubles\ScriptedPlanModel;

uses(RefreshDatabase::class);

/**
 * P2 v0.6 — СЦЕНА ПАРАМИ, И СУДЬЯ НА КАЖДУЮ ПАРУ (наряд DAY-FIX-2, Ч.1).
 *
 * Живой прогон 05.09: «What kinds of projects did you work on?» → «Later, I moved into an in-house
 * team.» считалось верным ответом. Гейт на строку этого не видит; видит один короткий вопрос про
 * одну пару. Четыре исхода, и они — весь контракт суда:
 *
 *   все пары устояли → ни одной правки, полки и цепочка сложены из пар, тип пары уехал в цепочку;
 *   судья отбил одну → одна правка, пара устояла с переписанной репликой;
 *   две правки не помогли → пара выброшена, счётчик знает;
 *   осталось меньше четырёх → день отбит целиком, кодом `day.pairs_too_few`.
 */
beforeEach(function (): void {
    /** @var array<string, mixed> $day */
    $this->day = planFixture('s1-day1.v0.6.json');
    $this->defects = new RecordingPlanDefectReporter();
});

/**
 * Wire the pipeline onto `$model`, WITH the court, and run it to the end of day 1.
 *
 * @return array{0: string, 1: ScriptedPlanModel}
 */
function runPairedPlanWith(ScriptedPlanModel $model, RecordingPlanDefectReporter $defects): array
{
    $prompts = new PlanPromptLibrary();
    $ledger = app(RecordsPlanSpend::class);

    app()->instance(PlanOutlinePort::class, new PlanOutlineService($model, $prompts, $ledger, $defects));
    app()->instance(PlanDayComposer::class, new PlanDayComposer(
        $model,
        $prompts,
        $ledger,
        $defects,
        new PlanDayRepairer($model, $prompts, $ledger),
        new PlanDayValidator(),
        null,
        court: new PlanPairCourt($model, $prompts, $ledger, $defects),
    ));

    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    return [startedPlanFor(test(), $token, ['event_date' => now()->addDays(2)->format('Y-m-d')]), $model];
}

/** The day 1 row. */
function pairedDayRow(string $planId): object
{
    /** @var object $row */
    $row = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->first();

    return $row;
}

it('lays the pairs onto the shelves and reads the chain off their order, pair type included', function () {
    [$planId, $model] = runPairedPlanWith(new ScriptedPlanModel([$this->day]), $this->defects);

    $row = pairedDayRow($planId);
    expect($row->status)->toBe('ready')
        // ONE JUDGE CALL PER PAIR, and not one rewrite: the fixture's five pairs fit.
        ->and($model->judgeCalls())->toBe(5)
        ->and($model->rewriteCalls())->toBe(0);

    /** @var list<array{turn: string, term_id: string, pair: string|null}> $chain */
    $chain = json_decode((string) $row->dialogue, true);
    expect($chain)->toHaveCount(10);
    // Alternating by construction, and every turn carries its pair's type — the last two pairs are
    // the invitations («Is there anything you would like to ask?») and their questions.
    $sides = array_column($chain, 'turn');
    expect($sides)->toBe(['role', 'you', 'role', 'you', 'role', 'you', 'role', 'you', 'role', 'you'])
        ->and(array_column($chain, 'pair'))
        ->toBe(['answer', 'answer', 'answer', 'answer', 'answer', 'answer', 'ask', 'ask', 'ask', 'ask']);

    // THE SHELVES are what v0.5 would have written: six on hear, four on say, two on ask.
    $shelves = DB::table('terms')
        ->join('collection_items', 'collection_items.term_id', '=', 'terms.id')
        ->where('collection_items.collection_id', $row->collection_id)
        ->pluck('terms.shelf')->countBy()->all();
    expect($shelves['hear'] ?? 0)->toBe(5)
        ->and($shelves['say'] ?? 0)->toBe(3)
        ->and($shelves['ask'] ?? 0)->toBe(2);

    // …and the ledger says what the court cost: five judge rows beside the day row.
    expect(DB::table('generation_requests')->where('plan_id', $planId)->where('prompt', 'like', 'pair_judge:%')->count())->toBe(5);
});

it('rewrites a reply the judge refused, once, and keeps the pair with the new line', function () {
    // Pair 3 (0-based 2) does not follow; the rewrite does.
    $verdicts = [true, true, false, true, true, true, true];
    [$planId, $model] = runPairedPlanWith(new ScriptedPlanModel([$this->day], verdicts: $verdicts), $this->defects);

    $row = pairedDayRow($planId);
    expect($row->status)->toBe('ready')
        ->and($model->rewriteCalls())->toBe(1)
        // Five pairs plus one re-judgement of the rewritten one.
        ->and($model->judgeCalls())->toBe(6);

    // THE REWRITE SAW BOTH LINES AND THE REASON — that is the whole of what it needs.
    expect($model->rewriteMessages[0])->toContain('"A"')
        ->toContain('"B"')
        ->toContain('scripted: does not follow')
        ->toContain('DAY WORDS');

    // The rewritten line is a card of the day, the refused one is not.
    $texts = DB::table('terms')
        ->join('collection_items', 'collection_items.term_id', '=', 'terms.id')
        ->where('collection_items.collection_id', $row->collection_id)
        ->pluck('terms.text')->all();
    expect($texts)->toContain('Scripted rewritten line.')
        ->and($texts)->not->toContain('Sorry, could you say that again?');

    expect($this->defects->warnings(PlanPairCourt::PAIR_REWRITTEN))->toBe(1)
        ->and($this->defects->warnings(PlanPairCourt::PAIR_DROPPED))->toBe(0);
});

it('drops a pair no two rewrites could save, and the scene goes on without it', function () {
    // Pair 1 fails, both rewrites fail; everything else fits.
    $verdicts = [false, false, false];
    [$planId, $model] = runPairedPlanWith(new ScriptedPlanModel([$this->day], verdicts: $verdicts), $this->defects);

    $row = pairedDayRow($planId);
    expect($row->status)->toBe('ready')
        ->and($model->rewriteCalls())->toBe(PlanPairCourt::MAX_REWRITES);

    /** @var list<array<string, mixed>> $chain */
    $chain = json_decode((string) $row->dialogue, true);
    expect($chain)->toHaveCount(8)
        ->and($this->defects->warnings(PlanPairCourt::PAIR_DROPPED))->toBe(1);

    // The dropped pair's cards are not in the day at all — neither the reply nor the question it
    // did not answer.
    $texts = DB::table('terms')
        ->join('collection_items', 'collection_items.term_id', '=', 'terms.id')
        ->where('collection_items.collection_id', $row->collection_id)
        ->pluck('terms.text')->all();
    expect($texts)->not->toContain('Do you have an appointment?')
        ->and($texts)->not->toContain('I need to check in, please.');
});

it('refuses the day whole when fewer than four pairs survive', function () {
    // Every judgement says no — on BOTH attempts: five pairs, two rewrites each, fifteen verdicts a
    // day, all dropped. (A script that ran out after the first attempt let the retry's pairs fit by
    // default, and the day came out `ready`.)
    $verdicts = array_fill(0, 30, false);
    [$planId] = runPairedPlanWith(new ScriptedPlanModel([$this->day, $this->day], verdicts: $verdicts), $this->defects);

    $row = pairedDayRow($planId);
    // Two day calls spent (the retry got the same script), and the code names the reason.
    expect($row->status)->toBe('failed', "status={$row->status} fail_code={$row->fail_code} reason={$row->fail_reason}")
        ->and($row->fail_code)->toBe(PlanDayValidator::PAIRS_TOO_FEW, "fail_code={$row->fail_code} reason={$row->fail_reason}");
});
