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
        (string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-day1.v0.4.json'),
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
    // The example is the card itself — «пример — это дословно термин», the узор the live day 2 died
    // on. Carded and fatal, so it is one short call about one card.
    $broken['words'][0]['example'] = $this->day['words'][0]['text'];

    $fixed = $this->day['words'][0];   // the same card, with its own sentence back

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
        ->and($row->generation_attempts)->toBe(1)
        // THE REPAIR IS ON THE WRITTEN ROW TOO (Д-18). It used to be charged only when the day
        // failed, so a day that cost two calls and worked said it had cost one.
        ->and($row->repair_calls)->toBe(1);

    // THE REPAIR CALL SAW THE ACCEPTED DAY AND THE BROKEN CARD, and knew which was which.
    $prompt = $model->repairPrompts[0];
    expect($prompt)->toContain('hear[0] · frame:')             // an accepted line, with its address
        ->toContain('words[1] «prescription»')                  // an accepted term
        ->not->toContain('words[0] «lower back»')               // …and not the one being repaired
        ->and($model->repairMessages[0])->toContain('"index": 0')
        ->and($model->repairMessages[0])->toContain(PlanDayValidator::EXAMPLE_IS_A_TERM);

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
            [...$this->day['hear'], ...$this->day['say'], ...$this->day['ask'], ...$this->day['numbers']],
        ),
        ...array_column($this->day['words'], 'text'),
        ...array_column($this->day['chunks'], 'text'),
        // The five phrases the SERVER writes into day 1 — they are cards of the collection like any
        // other, and a merge that dropped them would be as wrong as one that dropped a line.
        ...array_column(config('generation.plan.rescue_kit.en.ru'), 'text'),
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

it('sends a line whose Russian lost its key to P2R, not the whole day back', function () {
    // PLAN-FIX-4 п. 1.5, the стык: `line.translation_missing_key` is a CARDED violation, so it buys
    // one short call about one line — not a second full day, which is what an unaddressed code costs.
    $broken = $this->day;
    // The line drills «half past nine» / «полдесятого» and its Russian now says nothing of the kind:
    // the learner would read the question and have no way to know which word to produce.
    $broken['say'][1]['translation'] = 'У меня всё в порядке.';

    $fixed = $this->day['say'][1];   // the same line, with its Russian back

    [$planId, $model] = runPlanWith(
        new ScriptedPlanModel([$broken], [['cards' => [['array' => 'say', 'index' => 1, 'card' => $fixed]]]]),
        $this->defects,
    );

    $row = dayRow($planId);

    expect($model->repairCalls())->toBe(1)
        ->and($row->status)->toBe('ready')
        // ONE day call: the day was never asked for again.
        ->and($row->generation_attempts)->toBe(1)
        ->and($row->repair_calls)->toBe(1)
        // And the model was told what was wrong with which card, by code.
        ->and($model->repairMessages[0])->toContain(PlanDayValidator::TRANSLATION_MISSING_KEY)
        ->and($model->repairMessages[0])->toContain('"array": "say"');
});

// ── (б) the repair itself comes back broken ───────────────────────────────────────────────────

it('buys no second repair inside the run, and leaves the day its second DAY call', function () {
    $broken = $this->day;
    $broken['words'][0]['example'] = $this->day['words'][0]['text'];

    // The example is back and the key is now the term itself — a different gate, and a fatal one.
    $stillBroken = $this->day['words'][0];
    $stillBroken['translation'] = $stillBroken['text'];

    [$planId, $model] = runPlanWith(
        new ScriptedPlanModel(
            [$broken, $broken],
            [['cards' => [['array' => 'words', 'index' => 0, 'card' => $stillBroken]]]],
        ),
        $this->defects,
    );

    $row = dayRow($planId);

    // NO SECOND REPAIR INSIDE ONE RUN — that rule is unchanged. What changed is what the run costs
    // the day: a repair is not an attempt (Д-18), so the second DAY call the cap was written to
    // allow is still there and is taken. The second answer is broken the same way and has no repair
    // left to script, so the day ends spent.
    expect($model->dayCalls())->toBe(2)
        // One repair per RUN and never two — two runs, therefore two, which is the structural
        // ceiling {@see PlanDay::MAX_REPAIR_CALLS} names.
        ->and($model->repairCalls())->toBe(2)
        ->and($row->status)->toBe('failed')
        ->and($row->generation_attempts)->toBe(PlanDay::MAX_ATTEMPTS)
        ->and($row->repair_calls)->toBe(PlanDay::MAX_REPAIR_CALLS)
        ->and($row->collection_id)->toBeNull();

    // The SECOND day call was told where the FIRST run's merged day broke — addresses, no cards.
    expect($model->dayMessages[1])->toContain('THE PREVIOUS ANSWER TO THIS DAY FAILED')
        ->toContain('words[0].translation');
});

it('sends a single addressed card to P2R rather than rewriting the day (Д-18)', function () {
    // `day.example_is_a_term` on ONE card — the exact violation the live run's day 2 died on. It
    // names a card, so it is repairable, and the day must not be bought again whole.
    $broken = $this->day;
    $broken['words'][0]['example'] = $this->day['words'][1]['text'];

    [$planId, $model] = runPlanWith(
        new ScriptedPlanModel(
            [$broken],
            [['cards' => [['array' => 'words', 'index' => 0, 'card' => $this->day['words'][0]]]]],
        ),
        $this->defects,
    );

    $row = dayRow($planId);

    // ONE day call for day 1 — read off the row, because `dayCalls()` counts the short plan's
    // other days too, which run off the end of the script.
    expect($model->repairCalls())->toBe(1)
        ->and($model->repairMessages[0])->toContain(PlanDayValidator::EXAMPLE_IS_A_TERM)
        ->and($model->repairMessages[0])->toContain('"index": 0')
        ->and($row->status)->toBe('ready')
        ->and($row->generation_attempts)->toBe(1)
        ->and($row->repair_calls)->toBe(1);
});

// ── (в) more than half the day ────────────────────────────────────────────────────────────────

it('does not call the repair at all when more than half the day is broken', function () {
    // Eight cards of fourteen. Past the half, the answer is not a good day with defects — it is a
    // bad day, and paying to keep the half that passed is paying for the wrong thing.
    $broken = $this->day;
    // Eleven cards of eighteen: the lines that HAVE a key lose it, and every piece's example
    // becomes the piece itself. (A formula has no key card to lose, which is why the say shelf
    // contributes three and not four — the half is counted in CARDS the verdict names.)
    foreach ([0, 1, 2, 3] as $i) {
        $broken['say'][$i]['translation'] = 'У меня всё в порядке.';
    }
    foreach ([0, 1] as $i) {
        $broken['ask'][$i]['translation'] = 'У меня всё в порядке.';
    }
    foreach ([0, 1, 2, 3] as $i) {
        $broken['words'][$i]['example'] = $this->day['words'][$i]['text'];
    }
    foreach ([0, 1] as $i) {
        $broken['chunks'][$i]['example'] = $this->day['chunks'][$i]['text'];
    }

    [$planId, $model] = runPlanWith(
        new ScriptedPlanModel([$broken, $broken]),
        $this->defects,
    );

    $row = dayRow($planId);

    // The API carries the CODE of what broke, so the screen can say it in its own words (Д-19).
    expect($row->fail_code)->toBe(PlanDayValidator::EXAMPLE_IS_A_TERM);

    expect($model->repairCalls())->toBe(0)
        // The old path, unchanged: two whole-day calls, and the second is told where the first broke.
        ->and($model->dayCalls())->toBe(2)
        ->and($model->dayMessages[1])->toContain('THE PREVIOUS ANSWER TO THIS DAY FAILED')
        ->and($model->dayMessages[1])->toContain('say[0].translation')
        ->and($row->status)->toBe('failed')
        ->and($row->generation_attempts)->toBe(PlanDay::MAX_ATTEMPTS)
        // Nothing was patched, so nothing is charged as a patch.
        ->and($row->repair_calls)->toBe(0);
});

// ── (г) the repair answers about the wrong cards ───────────────────────────────────────────────

it('refuses a repair that answers about a card nobody asked about', function (array $cards) {
    $broken = $this->day;
    $broken['words'][0]['example'] = $this->day['words'][0]['text'];

    [$planId, $model] = runPlanWith(
        new ScriptedPlanModel([$broken], [['cards' => $cards]]),
        $this->defects,
    );

    $row = dayRow($planId);

    // The run is over: the merge did not happen, and the day is refused. It still has the second
    // DAY call the cap allows — a repair is not an attempt (Д-18) — and takes it; the script is
    // spent by then, so the day ends `failed` on the empty answer.
    expect($model->dayCalls())->toBe(2)
        ->and($row->status)->toBe('failed')
        ->and($row->generation_attempts)->toBe(PlanDay::MAX_ATTEMPTS)
        ->and($row->collection_id)->toBeNull();

    // What the FIRST run recorded is the assertion: off-target, and the original defect still there.
    /** @var list<string> $violations */
    $violations = json_decode((string) json_encode($model->dayMessages[1]), true);

    expect($violations)->toContain(PlanDayRepairer::OFF_TARGET)
        // …and the day is still failing on what it was failing on. Nothing was merged, so nothing
        // was quietly fixed and nothing was quietly overwritten.
        ->and($violations)->toContain(PlanDayValidator::EXAMPLE_IS_A_TERM);
})->with([
    'the wrong index' => [fn () => [[
        'array' => 'words',
        'index' => 3,
        'card' => json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-day1.v0.4.json'), true)['words'][0],
    ]]],
    'the wrong array' => [fn () => [[
        'array' => 'chunks',
        'index' => 0,
        'card' => json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-day1.v0.4.json'), true)['words'][0],
    ]]],
    'one card too many' => [fn () => [
        [
            'array' => 'words',
            'index' => 0,
            'card' => json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-day1.v0.4.json'), true)['words'][0],
        ],
        [
            'array' => 'words',
            'index' => 1,
            'card' => json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-day1.v0.4.json'), true)['words'][1],
        ],
    ]],
]);
