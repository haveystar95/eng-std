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
 * НАРЯД DAY-FIX-3, Ч.2 — БОЛЬШЕ СЛОВ И ЧЕСТНЫЕ ВАРИАНТЫ ТАКТА 1, на собранном дне (P2 v0.8).
 *
 *   слово дня — либо стоит в реплике, либо помечено `topical: true`; больше ничего;
 *   тематическое слово доезжает до строки термина, до экрана дня (`topical`) и до посадки как
 *   обычное слово, но в цепочку диалога не входит;
 *   «тематическое», которое всё-таки стоит в реплике, — слово реплики, а не «по теме»;
 *   варианты такта «что тебе сказали?» — реплики роли ДРУГОЙ функции, без перефразов.
 */
beforeEach(function (): void {
    /** @var array<string, mixed> $day */
    $this->day = planFixture('s1-day1.v0.8.json');
    $this->defects = new RecordingPlanDefectReporter();
});

/**
 * @return array{0: string, 1: ScriptedPlanModel, 2: string}  plan id, model, bearer token
 */
function runTopicalPlanWith(ScriptedPlanModel $model, RecordingPlanDefectReporter $defects): array
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

function topicalDayRow(string $planId): object
{
    /** @var object $row */
    $row = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->first();

    return $row;
}

/** @return array<string, bool> text => topical */
function topicalOf(object $row): array
{
    $out = [];
    foreach (DB::table('terms')
        ->join('collection_items', 'collection_items.term_id', '=', 'terms.id')
        ->where('collection_items.collection_id', $row->collection_id)
        ->get(['terms.text', 'terms.topical']) as $term) {
        $out[(string) $term->text] = (bool) $term->topical;
    }

    return $out;
}

it('keeps a topical word that stands in no line, drops an unmarked one, and writes the mark onto the term', function () {
    $day = $this->day;
    // «lamp» in a scene where nobody mentions a lamp and nobody called it topical — the GEN-1 rule.
    $day['words'][] = [
        'kind' => 'word', 'skill_ref' => 's1.1', 'text' => 'lamp', 'translation' => 'лампа',
        'transliteration' => 'лэмп', 'example' => 'The lamp in the bathroom is broken.',
        'example_translation' => 'Лампа в ванной сломана.', 'image_api_prompt' => 'a small lamp on a bedside table',
    ];

    [$planId, $model, $token] = runTopicalPlanWith(new ScriptedPlanModel([$day]), $this->defects);

    $row = topicalDayRow($planId);
    expect($row->status)->toBe('ready', (string) $row->fail_reason)
        ->and($model->repairCalls())->toBe(0);

    $topical = topicalOf($row);
    // The six topical words of the situation are in the day, marked — none of them stands in a line.
    foreach (['prescription', 'insurance', 'test results', 'waiting room', 'pharmacy', 'symptoms'] as $word) {
        expect($topical)->toHaveKey($word)->and($topical[$word])->toBeTrue($word);
    }
    // The pieces of the lines are not topical; the unmarked stray is gone.
    expect($topical['lower back'])->toBeFalse()
        ->and($topical['check in'])->toBeFalse()
        ->and($topical)->not->toHaveKey('lamp')
        ->and($this->defects->warnings(PlanDayComposer::WORD_OUTSIDE_LINES_DROPPED))->toBe(1)
        // No size counter for a healthy v0.8 day: 6 pieces and 6 topical words are inside both guides.
        ->and($this->defects->warnings(PlanDayValidator::SIZE_OUT_OF_RANGE))->toBe(0);

    // THE DAY SCREEN says which is which — `topical` on the row (Ч.5.4).
    $screen = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}/days/1")->assertOk()->json('data.terms');
    $byText = [];
    foreach ($screen as $term) {
        $byText[$term['text']] = $term;
    }
    expect($byText['prescription']['topical'])->toBeTrue()
        ->and($byText['prescription']['shelf'])->toBe('words')
        ->and($byText['lower back']['topical'])->toBeFalse();

    // …AND THE SITTING deals it as an ordinary word — met on day 1, never in the conversation.
    $session = planSession($this, $token, $planId);
    $dealt = [];
    foreach ($session['tasks'] as $task) {
        $dealt[$task['card']['answer']][] = $task['section_code'];
    }
    expect($dealt)->toHaveKey('prescription')
        ->and($dealt['prescription'])->toContain('words');
    foreach ($session['dialogues'] as $dialogue) {
        foreach ($dialogue['turns'] as $turn) {
            expect($turn['text'])->not->toBe('prescription');
        }
    }
});

it('demotes a «topical» card that stands in a line after all — «по теме» never lies about a word of the dialogue', function () {
    $day = $this->day;
    // The model put «appointment» among the topical cards, but «Do you have an appointment?» is a
    // role line: it is a piece of the lines, whatever array it came in.
    $appointment = $day['words'][2];
    array_splice($day['words'], 2, 1);
    $day['topical'][] = $appointment;

    [$planId] = runTopicalPlanWith(new ScriptedPlanModel([$day]), $this->defects);

    $row = topicalDayRow($planId);
    expect($row->status)->toBe('ready', (string) $row->fail_reason)
        ->and(topicalOf($row)['appointment'])->toBeFalse()
        ->and($this->defects->warnings(PlanDayComposer::TOPICAL_IN_LINE))->toBe(1);
});

it('counts a v0.8 day short of topical words', function () {
    $thin = $this->day;
    $thin['topical'] = [];
    [$planId] = runTopicalPlanWith(new ScriptedPlanModel([$thin]), $this->defects);
    expect(topicalDayRow($planId)->status)->toBe('ready')
        ->and($this->defects->warnings(PlanDayValidator::SIZE_OUT_OF_RANGE))->toBe(1);
});

it('never holds the topical guide against a v0.7 day, which is read as «ничего не по теме»', function () {
    // A v0.7-shaped answer (no `topical` on its words) is not counted short of a shelf its prompt
    // never asked for — and every one of its words lands with the mark off.
    [$planId] = runTopicalPlanWith(new ScriptedPlanModel([planFixture('s1-day1.v0.7.json')]), $this->defects);
    $row = topicalDayRow($planId);
    expect($row->status)->toBe('ready')
        ->and($this->defects->warnings(PlanDayValidator::SIZE_OUT_OF_RANGE))->toBe(0)
        ->and(array_filter(topicalOf($row)))->toBe([]);
});

it('offers meanings of role lines of ANOTHER function on такт 1, never two paraphrases (Ч.2.2)', function () {
    [$planId, , $token] = runTopicalPlanWith(new ScriptedPlanModel([$this->day]), $this->defects);

    // What each role line IS FOR, off the fixture: its translation → (skill, pair kind).
    $function = [];
    foreach ($this->day['pairs'] as $pair) {
        $role = $pair['role'];
        $function[$role['translation']] = $pair['kind'] === 'ask' ? 'ask' : $role['skill_ref'];
    }
    // …and whether the rule has anything to offer a line at all: a role line of another function
    // of the SAME family (a question among questions). «Please wait here for a few minutes.» is
    // the scene's one statement — every other-function line is a question, the family gate
    // refuses them all, and the card falls back to the old neighbours rather than starve the day.
    $isQuestion = static fn (string $t): bool => str_ends_with(trim($t), '?');
    $ruleCanHold = static function (string $own) use ($function, $isQuestion): bool {
        foreach ($function as $text => $f) {
            if ($text !== $own && $f !== $function[$own] && $isQuestion($text) === $isQuestion($own)) {
                return true;
            }
        }

        return false;
    };

    $session = planSession($this, $token, $planId);
    $hear = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['card']['exercise_mode'] === 'situational_hear',
    ));
    expect($hear)->not->toBeEmpty();

    // THE PAIR'S TYPE REACHES THE WIRE — it was dropped on hydration for every day ever written
    // (`PlanMapper::dialogueOf`), and it is what tells an invitation from a question here.
    $pairs = array_map(static fn (array $t): ?string => $t['pair'] ?? null, $session['dialogues'][0]['turns']);
    expect($pairs)->toContain('answer')->toContain('ask');

    foreach ($hear as $task) {
        $card = $task['card'];
        // The correct option is this line's own meaning; every other option is a role line of
        // another function of the scene — never one that asks the same thing in other words.
        $own = $card['prompt'];
        $ownMeaning = null;
        foreach ($card['options'] as $i => $option) {
            if ($card['option_ids'][$i] === $card['answer']) {
                $ownMeaning = $option;
            }
        }
        expect($ownMeaning)->not->toBeNull("no meaning for «{$own}»")
            ->and(array_key_exists($ownMeaning, $function))->toBeTrue("«{$own}» is not a role line of the scene");
        expect(count($card['options']))->toBeGreaterThanOrEqual(2);
        if (! $ruleCanHold($ownMeaning)) {
            $starved[] = $own;

            continue;
        }
        foreach ($card['options'] as $i => $option) {
            if ($card['option_ids'][$i] === $card['answer']) {
                continue;
            }
            expect(array_key_exists($option, $function))->toBeTrue("«{$option}» is not a role line of the scene")
                ->and($function[$option])->not->toBe($function[$ownMeaning], "«{$option}» has the same function as «{$own}»");
        }
        $held[] = $own;
    }
    // The rule held for every question of the scene and starved only on its one statement — the
    // card was still dealt (the day has to be passable), off the old neighbours.
    expect($held ?? [])->not->toBeEmpty()
        ->and($starved ?? [])->toBe(['Please wait here for a few minutes.']);
});
