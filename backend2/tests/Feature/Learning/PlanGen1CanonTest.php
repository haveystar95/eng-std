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
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Doubles\RecordingPlanDefectReporter;
use Tests\Doubles\ScriptedPlanModel;

uses(RefreshDatabase::class);

/**
 * НАРЯД GEN-1 — канон качества в конвейере (`docs/research/gen-1/README.md`, Ч.5.3).
 *
 * Четыре механики, которых до наряда не было, каждая на живом узоре прогона «было»:
 *
 *   слово, которого нет ни в одной реплике сцены, выбрасывается до суда над днём;
 *   судья отвечает четырьмя булевыми, и «fits: true» рядом с «level_fits: false» — это «нет»;
 *   пара, половину которой переписала починка, судится снова, и «нет» уносит обе карточки;
 *   `speaking_keys` доезжают до строки термина и до карточки сессии, а грейдер засчитывает их
 *   только за тумблером.
 */
beforeEach(function (): void {
    /** @var array<string, mixed> $day */
    $this->day = planFixture('s1-day1.v0.7.json');
    $this->defects = new RecordingPlanDefectReporter();
});

/**
 * @return array{0: string, 1: ScriptedPlanModel, 2: string}  plan id, model, bearer token
 */
function runGen1PlanWith(ScriptedPlanModel $model, RecordingPlanDefectReporter $defects): array
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

    return [startedPlanFor(test(), $token, ['event_date' => now()->addDays(2)->format('Y-m-d')]), $model, $token];
}

function gen1DayRow(string $planId): object
{
    /** @var object $row */
    $row = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->first();

    return $row;
}

/** @return list<string> */
function gen1DayTexts(object $row): array
{
    return DB::table('terms')
        ->join('collection_items', 'collection_items.term_id', '=', 'terms.id')
        ->where('collection_items.collection_id', $row->collection_id)
        ->pluck('terms.text')->all();
}

it('drops a word that stands in no line of the scene, and writes the day without it', function () {
    $day = $this->day;
    // «lamp» in a scene where nobody mentions a lamp — the live rent day, card for card.
    $day['words'][] = [
        'kind' => 'word',
        'skill_ref' => 's1.1',
        'text' => 'lamp',
        'translation' => 'лампа',
        'transliteration' => 'лэмп',
        'example' => 'The lamp in the bathroom is broken.',
        'example_translation' => 'Лампа в ванной сломана.',
        'image_api_prompt' => 'a small lamp on a bedside table',
    ];

    [$planId, $model] = runGen1PlanWith(new ScriptedPlanModel([$day]), $this->defects);

    $row = gen1DayRow($planId);
    expect($row->status)->toBe('ready', (string) $row->fail_reason)
        // No repair was bought: the card was dropped, not refused.
        ->and($model->repairCalls())->toBe(0)
        ->and(gen1DayTexts($row))->not->toContain('lamp')
        ->and(gen1DayTexts($row))->toContain('painkiller')
        ->and($this->defects->warnings(PlanDayComposer::WORD_OUTSIDE_LINES_DROPPED))->toBe(1);
});

it('sends the judge both translations and the level, and rewrites on a failed question even when the model says fits', function () {
    // Pair 2 (0-based 1): the model answers «level_fits: false» and, as models do, «fits: true».
    $verdicts = [
        true,
        ['answers' => true, 'not_clarification' => true, 'level_fits' => false, 'translation_exact' => true],
        true, true, true, true,
    ];
    [$planId, $model] = runGen1PlanWith(new ScriptedPlanModel([$this->day], verdicts: $verdicts), $this->defects);

    $row = gen1DayRow($planId);
    expect($row->status)->toBe('ready', (string) $row->fail_reason)
        ->and($model->rewriteCalls())->toBe(1)
        ->and($model->judgeCalls())->toBe(6);

    // The judge saw the pair WITH its translations; the rewriter was told WHICH question failed.
    expect($model->judgeMessages[1])->toContain('"A_translation"')->toContain('"B_translation"')
        ->and($model->rewriteMessages[0])->toContain('"failed_checks"')->toContain('level_fits');
});

it('judges a pair again after a repair rewrote its reply, and drops the pair on «no»', function () {
    $broken = $this->day;
    // `say[1]` («It hurts in my lower back.») ships a gap in its translation — carded, fatal, one
    // card of twenty — so P2R is bought for exactly that card.
    $broken['pairs'][1]['you']['translation'] = 'Болит в ___.';

    $fixed = [
        ...$this->day['pairs'][1]['you'],
        'frame' => 'It is my ___ again.',
        'translation' => 'Это снова моя поясница.',
    ];
    $repair = ['cards' => [['array' => 'say', 'index' => 1, 'card' => $fixed]]];

    // Five verdicts for the five pairs, then the re-judgement of the repaired pair: «no».
    $verdicts = [true, true, true, true, true, false];
    [$planId, $model] = runGen1PlanWith(new ScriptedPlanModel([$broken], [$repair], verdicts: $verdicts), $this->defects);

    $row = gen1DayRow($planId);
    expect($row->status)->toBe('ready', (string) $row->fail_reason)
        ->and($model->repairCalls())->toBe(1)
        ->and($model->judgeCalls())->toBe(6)
        // No rewrite after a repair: the pair is dropped whole.
        ->and($model->rewriteCalls())->toBe(0)
        ->and($this->defects->warnings(PlanPairCourt::PAIR_DROPPED))->toBe(1);

    /** @var list<array<string, mixed>> $chain */
    $chain = json_decode((string) $row->dialogue, true);
    expect($chain)->toHaveCount(8);

    $texts = gen1DayTexts($row);
    expect($texts)->not->toContain('It is my lower back again.')
        ->and($texts)->not->toContain('What is the problem today?')
        ->and($texts)->toContain('I need to check in, please.');
});

it('stores speaking_keys on the term, sends them on the card, and grades by the key alone with the toggle OFF', function () {
    // The toggle is ON by default since DAY-FIX-3 (Ч.1.6); this test pins the OTHER position — the
    // rollback of a client that judges by one key — and has to set it BEFORE the first request:
    // the router keeps the controller, and the handler built into it, for the life of the test.
    config(['learning.plan.speaking_keys_graded' => false]);

    [$planId, $model, $token] = runGen1PlanWith(new ScriptedPlanModel([$this->day]), $this->defects);

    $row = gen1DayRow($planId);
    expect($row->status)->toBe('ready', (string) $row->fail_reason);

    $line = DB::table('terms')->where('text', 'It hurts in my lower back.')->first();
    expect($line)->not->toBeNull()
        ->and($line->speaking_key)->toBe('lower back')
        ->and(json_decode((string) $line->speaking_keys, true))->toBe(['my back hurts', 'back pain']);
    // A role line and a word carry none.
    expect(DB::table('terms')->where('text', 'What is the problem today?')->value('speaking_keys'))->toBeNull()
        ->and(DB::table('terms')->where('text', 'painkiller')->value('speaking_keys'))->toBeNull();

    // THE CARD CONTRACT: every card of the sitting carries the field (empty where it does not apply).
    $session = planSession($this, $token, $planId);
    expect($session['tasks'])->not->toBeEmpty();
    foreach ($session['tasks'] as $task) {
        expect($task['card'])->toHaveKey('speaking_keys');
    }

    // THE GRADER, toggle OFF: the simpler form is «again».
    $seq = 0;
    $speak = function (string $response) use ($token, $line, &$seq): string {
        $reviewId = Ulid::generate();
        $seq++;
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/reviews/batch', ['reviews' => [[
                'id' => $reviewId,
                'term_id' => $line->id,
                'exercise_mode' => 'speaking',
                'response' => $response,
                'answered_at' => now()->toIso8601String(),
                'client_seq' => 1000 + $seq,
                'latency_ms' => 6000,
                'ladder_step' => 3,
            ]]])
            ->assertOk()
            ->assertJsonPath('data.accepted', 1);

        return (string) DB::table('reviews')->where('id', $reviewId)->value('grade');
    };

    // «my back hurts» covers half of the key «lower back» — under the one-key rule that is a lapse.
    expect($speak('my back hurts'))->toBe('again')
        ->and($speak('it hurts in my lower back'))->not->toBe('again');
});

it('grades by the simpler forms BY DEFAULT — the grader is never stricter than the phone (DAY-FIX-3, Ч.1.6)', function () {
    // THE LOCK on the contract invariant: the client judges a spoken line by `speaking_key` PLUS
    // `speaking_keys` (`SessionCard.spokenTargets`), so the server must accept the same list out
    // of the box. Nothing is set here on purpose — the default of `config/learning.php` is what is
    // under test; flipping it off would make the phone show «верно» over an answer the log grades
    // `again`, which is the one direction the pair is forbidden to drift.
    expect(config('learning.plan.speaking_keys_graded'))->toBeTrue();

    [$planId, $model, $token] = runGen1PlanWith(new ScriptedPlanModel([$this->day]), $this->defects);
    $line = DB::table('terms')->where('text', 'It hurts in my lower back.')->first();

    $reviewId = Ulid::generate();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => $reviewId,
            'term_id' => $line->id,
            'exercise_mode' => 'speaking',
            'response' => 'my back hurts',
            'answered_at' => now()->toIso8601String(),
            'client_seq' => 1001,
            'latency_ms' => 6000,
            'ladder_step' => 3,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    expect((string) DB::table('reviews')->where('id', $reviewId)->value('grade'))->not->toBe('again');
});

it('brings misnumbered skill_refs back onto the scene ids and writes the day without a repair', function () {
    // The live shape (V14): the model counted abilities its own way — «s1», «s2.0», «s3» — on a
    // scene whose ids are s1.1 and s1.2.
    $day = $this->day;
    $day['pairs'][0]['you']['skill_ref'] = 's1';
    $day['pairs'][1]['you']['skill_ref'] = 's1.0';
    $day['pairs'][2]['role']['skill_ref'] = 's2';
    $day['words'][0]['skill_ref'] = 's9.2';

    [$planId, $model] = runGen1PlanWith(new ScriptedPlanModel([$day]), $this->defects);

    $row = gen1DayRow($planId);
    expect($row->status)->toBe('ready', (string) $row->fail_reason)
        ->and($model->repairCalls())->toBe(0)
        // One P2 call for day 1 (the model's other calls are day 2's, which the script leaves empty).
        ->and($row->generation_attempts)->toBe(1)
        ->and($this->defects->warnings(PlanDayComposer::SKILL_REF_REPAIRED))->toBe(4);

    $refs = DB::table('terms')
        ->join('collection_items', 'collection_items.term_id', '=', 'terms.id')
        ->where('collection_items.collection_id', $row->collection_id)
        ->whereIn('terms.text', ['I need to check in, please.', 'It hurts in my lower back.', 'Please wait here for a few minutes.', 'lower back'])
        ->pluck('terms.skill_ref', 'terms.text')->all();
    ksort($refs);
    expect($refs)->toBe([
        'I need to check in, please.' => 's1.1',
        'It hurts in my lower back.' => 's1.1',
        'Please wait here for a few minutes.' => 's1.2',
        'lower back' => 's1.2',
    ]);
});

it('rewrites the day whole — not card by card — when a skill_ref cannot be read', function () {
    $broken = $this->day;
    $broken['pairs'][0]['you']['skill_ref'] = 's1.7';   // no seventh ability, on any scene

    [$planId, $model] = runGen1PlanWith(new ScriptedPlanModel([$broken, $this->day]), $this->defects);

    $row = gen1DayRow($planId);
    expect($row->status)->toBe('ready', (string) $row->fail_reason)
        // Two day calls for day 1, no repair: the unreadable ref has no address, so P2R was never
        // bought. The retry was told the code, and only the code.
        ->and($row->generation_attempts)->toBe(2)
        ->and($model->repairCalls())->toBe(0)
        ->and($model->dayMessages[1])->toContain('card.skill_ref_invalid');
});
