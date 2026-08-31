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
use App\Modules\Learning\Domain\Entity\PlanDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Doubles\RecordingPlanDefectReporter;
use Tests\Doubles\ScriptedPlanModel;

uses(RefreshDatabase::class);

/**
 * P2R — ONE SHORT CALL INSTEAD OF A WHOLE DAY PAID FOR TWICE.
 *
 * The v0.3 live run failed three answers running, and each time on ONE card of fourteen: attempt 2
 * would have been `ready` under the present gates but for a single word, and attempt 3, asked to
 * write the day again, fixed that word and broke three other things
 * (`docs/research/plan-v0.3-run.md`). Re-rolling thirteen good cards to fix one is how a day gets
 * worse for money.
 *
 * The four cases below are the whole of the decision this feature makes:
 *
 *   one card broken → one repair call, the other thirteen untouched, the day written;
 *   the repair comes back broken → `failed`, and there is no second repair;
 *   more than half the day broken → no repair at all, the day goes back whole;
 *   the repair answers about the wrong cards → nothing is merged, and the day fails saying so.
 */
beforeEach(function (): void {
    /** @var array<string, mixed> $day */
    $this->day = json_decode(
        (string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-day1.v0.3.json'),
        true,
    );

    $this->defects = new RecordingPlanDefectReporter();
});

/**
 * Wire a plan pipeline onto `$model` and run it to the end of day 1.
 *
 * @return array{0: string, 1: ScriptedPlanModel}  the plan id and the model, for the assertions
 */
function runPlanWith(ScriptedPlanModel $model, PlanDefectReporter $defects): array
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
    ));

    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    $plan = test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', [
            'goal_text' => 'Иду к врачу, болит спина, надо объяснить и понять назначение',
            'target_lang' => 'en',
            'level' => 'basic',
            'event_date' => now()->addDays(2)->format('Y-m-d'),
            'minutes_per_day' => 20,
        ])->assertCreated()->json('data');

    test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/outline")->assertOk();
    test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    return [$plan['id'], $model];
}

/** The day 1 row, as the database has it. */
function dayRow(string $planId): object
{
    /** @var object $row */
    $row = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->first();

    return $row;
}

// ── (а) one card of fourteen ──────────────────────────────────────────────────────────────────

it('repairs ONE broken card with one short call and leaves the other thirteen alone', function () {
    // The live shape, exactly: a day that is right but for one card. Under v0.3 this cost a second
    // full day and came back worse.
    $broken = $this->day;
    $broken['words'][0]['image_api_prompt'] = '';

    $fixed = $this->day['words'][0];   // the same card, with its picture back

    [$planId, $model] = runPlanWith(
        new ScriptedPlanModel([$broken], [['cards' => [['array' => 'words', 'index' => 0, 'card' => $fixed]]]]),
        $this->defects,
    );

    $row = dayRow($planId);

    // ONE day call and ONE repair for day 1 — the whole point is that the day was not written
    // twice. (`dayCalls()` counts the plan's OTHER days too: the event is two days out, so this is
    // a short plan and the scheduler writes day 2 and day 3 straight after this one, off the end of
    // the script. Day 1's own arithmetic is on its row.)
    expect($model->repairCalls())->toBe(1)
        ->and($row->status)->toBe('ready')
        ->and($row->collection_id)->not->toBeNull()
        ->and($row->generation_attempts)->toBe(1);

    // THE REPAIR CALL SAW THE ACCEPTED DAY AND THE BROKEN CARD, and knew which was which.
    $prompt = $model->repairPrompts[0];
    expect($prompt)->toContain('phrases[0] · frame:')          // an accepted line, with its address
        ->toContain('words[1] «back pain»')                     // an accepted term
        ->not->toContain('words[0] «lower back»')               // …and not the one being repaired
        ->and($model->repairMessages[0])->toContain('"index": 0')
        ->and($model->repairMessages[0])->toContain(PlanDayValidator::IMAGE_PROMPT_MISSING);

    // THIRTEEN CARDS BYTE FOR BYTE. The merge replaces the addressed card and returns the objects
    // it was given for every other one — this is that claim, read back out of the database.
    $written = DB::table('terms')
        ->join('collection_items', 'collection_items.term_id', '=', 'terms.id')
        ->where('collection_items.collection_id', $row->collection_id)
        ->pluck('terms.text')
        ->all();

    sort($written);
    $expected = [
        ...array_map(
            static fn (array $line): string => PlanDayComposer::assemble($line['frame'], $line['filler']),
            $this->day['phrases'],
        ),
        ...array_column($this->day['words'], 'text'),
        ...array_column($this->day['chunks'], 'text'),
    ];
    sort($expected);

    expect($written)->toBe($expected);

    // TWO LEDGER ROWS, of two kinds. «Why did day 1 cost $0.08» has to be answerable.
    $spend = DB::table('generation_requests')
        ->where('plan_id', $planId)
        ->where(function ($q): void {
            $q->where('prompt', 'like', 'day: день 1 %')->orWhere('prompt', 'like', 'day_repair: починка дня 1 %');
        })
        ->orderBy('created_at')
        ->get();

    expect($spend)->toHaveCount(2)
        ->and($spend[0]->prompt)->toStartWith('day:')
        ->and($spend[1]->prompt)->toStartWith('day_repair:')
        ->and($spend[1]->status)->toBe('succeeded')
        ->and($spend[1]->prompt_version)->toBe(PlanPromptLibrary::REPAIR_VERSION)
        ->and($spend[1]->size)->toBe(1)
        ->and($spend->pluck('purpose')->unique()->all())->toBe(['plan']);
});

// ── (б) the repair itself comes back broken ───────────────────────────────────────────────────

it('fails the day when the repaired card breaks something new, and buys no second repair', function () {
    $broken = $this->day;
    $broken['words'][0]['image_api_prompt'] = '';

    // The picture is back and the key is now the term itself — a different gate, and a fatal one.
    $stillBroken = $this->day['words'][0];
    $stillBroken['translation'] = $stillBroken['text'];

    [$planId, $model] = runPlanWith(
        new ScriptedPlanModel(
            [$broken],
            [['cards' => [['array' => 'words', 'index' => 0, 'card' => $stillBroken]]]],
        ),
        $this->defects,
    );

    $row = dayRow($planId);

    expect($model->repairCalls())->toBe(1)
        // NO SECOND REPAIR, and no second day either: the run spent two calls and the counter is
        // the money.
        ->and($model->dayCalls())->toBe(1)
        ->and($row->status)->toBe('failed')
        ->and($row->generation_attempts)->toBe(PlanDay::MAX_ATTEMPTS)
        ->and($row->collection_id)->toBeNull();

    /** @var list<string> $violations */
    $violations = json_decode((string) $row->generation_violations, true);

    expect(implode(' ', $violations))->toContain(PlanDayValidator::KEY_IS_THE_TERM)
        ->and(implode(' ', $violations))->toContain('words[0].translation');
});

// ── (в) more than half the day ────────────────────────────────────────────────────────────────

it('does not call the repair at all when more than half the day is broken', function () {
    // Eight cards of fourteen. Past the half, the answer is not a good day with defects — it is a
    // bad day, and paying to keep the half that passed is paying for the wrong thing.
    $broken = $this->day;
    foreach ([0, 1, 2, 3, 4, 5] as $i) {
        $broken['phrases'][$i]['image_api_prompt'] = '';
    }
    foreach ([0, 1] as $i) {
        $broken['words'][$i]['image_api_prompt'] = '';
    }

    [$planId, $model] = runPlanWith(
        new ScriptedPlanModel([$broken, $broken]),
        $this->defects,
    );

    $row = dayRow($planId);

    expect($model->repairCalls())->toBe(0)
        // The old path, unchanged: two whole-day calls, and the second is told where the first broke.
        ->and($model->dayCalls())->toBe(2)
        ->and($model->dayMessages[1])->toContain('THE PREVIOUS ANSWER TO THIS DAY FAILED')
        ->and($model->dayMessages[1])->toContain('phrases[0].image_api_prompt')
        ->and($row->status)->toBe('failed')
        ->and($row->generation_attempts)->toBe(PlanDay::MAX_ATTEMPTS);
});

// ── (г) the repair answers about the wrong cards ───────────────────────────────────────────────

it('refuses a repair that answers about a card nobody asked about', function (array $cards) {
    $broken = $this->day;
    $broken['words'][0]['image_api_prompt'] = '';

    [$planId, $model] = runPlanWith(
        new ScriptedPlanModel([$broken], [['cards' => $cards]]),
        $this->defects,
    );

    $row = dayRow($planId);

    expect($model->repairCalls())->toBe(1)
        ->and($model->dayCalls())->toBe(1)
        ->and($row->status)->toBe('failed')
        ->and($row->collection_id)->toBeNull();

    /** @var list<string> $violations */
    $violations = json_decode((string) $row->generation_violations, true);

    expect(implode(' ', $violations))->toContain(PlanDayRepairer::OFF_TARGET)
        // …and the day is still failing on what it was failing on. Nothing was merged, so nothing
        // was quietly fixed and nothing was quietly overwritten.
        ->and(implode(' ', $violations))->toContain(PlanDayValidator::IMAGE_PROMPT_MISSING);
})->with([
    'the wrong index' => [fn () => [[
        'array' => 'words',
        'index' => 3,
        'card' => json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-day1.v0.3.json'), true)['words'][0],
    ]]],
    'the wrong array' => [fn () => [[
        'array' => 'chunks',
        'index' => 0,
        'card' => json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-day1.v0.3.json'), true)['words'][0],
    ]]],
    'one card too many' => [fn () => [
        [
            'array' => 'words',
            'index' => 0,
            'card' => json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-day1.v0.3.json'), true)['words'][0],
        ],
        [
            'array' => 'words',
            'index' => 1,
            'card' => json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-day1.v0.3.json'), true)['words'][1],
        ],
    ]],
]);
