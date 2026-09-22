<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Assembly\DayAssembler;
use App\Modules\Plan\Domain\Assembly\ListenCards;
use App\Modules\Plan\Domain\Assembly\PhraseCards;
use App\Modules\Plan\Domain\Assembly\PhraseSeries;
use App\Modules\Plan\Domain\Assembly\PhrasesStage;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * A FILLER THE SEAM JUDGE SAID «NO» TO (наряд SESSION-1e, разд. 4): a `filler.native_seam` finding at `pN.fK` in the scene's
 * `checks_json` is a filler whose native sentence does not read («Это у него уже уже три дня»). No card of the day shows it
 * where its native text is seen — no recognition or production is said with it, no option, no chip, no filler of a frame
 * carries it; the one the dialogue says stays as it is. A frame whose other fillers all do not read is recognised once,
 * with the said one.
 *
 * The clean fake lesson: p1 «It hurts in his ___.» — 0 lower back (said), 1 neck, 2 shoulder; p3 — 0 sharp (said), 1 dull,
 * 2 constant; p5 — 0 at home (said), 1 for two days, 2 after school; p6 — 0 an X-ray and 1 a follow-up appointment (both
 * said), 2 a sick note.
 */

const S1S_SCENE = '01J8SESS1ESEAMS00000000001';

/**
 * @param  list<string>  $unreadable
 * @param  (Closure(array<string, mixed>): array<string, mixed>)|null  $edit
 */
function s1sScene(array $unreadable, ?Closure $edit = null, string $sceneId = S1S_SCENE): SceneMaterial
{
    $id = PlanSceneId::fromString($sceneId);
    $packs = lessonPacks();
    $payload = FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Intermediate, null, 8, 8, FakePlanModel::roles(), new EarlierDays));
    if ($edit !== null) {
        $payload = $edit($payload);
    }
    $lesson = LessonAssembly::serve((new LessonParser)->parse($payload), $id->value, $packs->for('en'));

    return new SceneMaterial(
        $id, $lesson, PlanTerm::fromLesson($id, $lesson, static fn (): PlanTermId => PlanTermId::generate()),
        $packs->for('en'), $packs->for('ru'), $unreadable,
    );
}

/** @return list<DayCard> */
function s1sDay(SceneMaterial $scene, PlanLevel $level): array
{
    return (new DayAssembler)->sceneDay(
        PlanDayId::fromString('01J8SESSDAY000000000000001'), $scene, [$scene->sceneId->value => $scene], $level, [], [],
        static fn (): DayCardId => DayCardId::generate(),
    );
}

function s1sTerm(SceneMaterial $scene, string $ref): PlanTerm
{
    $term = $scene->term($ref);
    expect($term)->not->toBeNull();

    return $term;
}

/**
 * Every filler list a payload carries, wherever it stands: a frame's (`frame.slot.fillers`, by the frame's ref), the chips
 * of a phrase card (by its unit), the chips of a dialogue card (by the frame of its own line).
 *
 * @param  array<string, mixed>  $payload
 * @return list<array{ref: string, indexes: list<int>}>
 */
function s1sFillerLists(DayCard $card, array $payload): array
{
    $lists = [];
    $walk = static function (mixed $value) use (&$walk, &$lists): void {
        if (! is_array($value)) {
            return;
        }
        if (isset($value['ref'], $value['slot']['fillers']) && is_array($value['slot']['fillers'])) {
            $lists[] = ['ref' => (string) $value['ref'], 'indexes' => array_column($value['slot']['fillers'], 'index')];
        }
        foreach ($value as $item) {
            $walk($item);
        }
    };
    $walk($payload);
    if ($card->unitKind() === UnitKind::Phrase && is_array($payload['chips'] ?? null)) {
        $ref = $card->kind() === CardKind::PhraseCombine ? (string) $payload['correct_frame'] : $card->unitRef();
        $lists[] = ['ref' => $ref, 'indexes' => array_column($payload['chips'], 'index')];
    }
    if (is_array($payload['modes']['chips'] ?? null) && is_string($payload['own_line']['frame_ref'] ?? null)) {
        $lists[] = ['ref' => $payload['own_line']['frame_ref'], 'indexes' => array_column($payload['modes']['chips'], 'index')];
    }

    return $lists;
}

// Canon (SESSION-1e, разд. 4): «находка filler.native_seam по адресу pN.fK — наполнение, чей родной шов не читается;
// исключение — наполнение said: его говорит диалог, оно остаётся как есть». Catches a finding read at the wrong place in the
// slot (`f1` is the first), a said filler hidden, a filler the dialogue says hidden, and a filler hidden with no finding.
it('hides a filler the seam judge said does not read — unless the dialogue says it, or the phrase itself is said with it', function () {
    $scene = s1sScene(['p1.f1', 'p1.f2', 'p6.f2', 'p3.f3', 'p9.f1']);

    expect($scene->hides('p1', 1))->toBeTrue()
        ->and($scene->hides('p3', 2))->toBeTrue()
        // «lower back» is said in x1 and is the phrase itself; «a follow-up appointment» is said in x8.
        ->and($scene->hides('p1', 0))->toBeFalse()
        ->and($scene->hides('p6', 1))->toBeFalse()
        ->and($scene->hides('p1', 2))->toBeFalse()
        ->and($scene->hides('p3', 1))->toBeFalse()
        ->and($scene->hides('p9', 0))->toBeFalse()
        ->and($scene->hides('p4', 0))->toBeFalse()
        ->and(s1sScene([])->hides('p1', 1))->toBeFalse();

    // p2 said by no line of the dialogue (x2 now stands on p1): the phrase is the frame with its first filler — it stays.
    $unsaid = s1sScene(['p2.f1', 'p2.f2'], static function (array $payload): array {
        $payload['dialogue'][1]['messages'][1]['phrase_id'] = 'p1';
        $payload['dialogue'][1]['messages'][1]['text_target'] = 'It hurts in his shoulder.';

        return $payload;
    });
    expect($unsaid->lesson->linesOf('p2'))->toBe([])
        ->and($unsaid->saidIndex(s1sTerm($unsaid, 'p2')))->toBe(0)
        ->and($unsaid->hides('p2', 0))->toBeFalse()
        ->and($unsaid->hides('p2', 1))->toBeTrue();
});

// Canon (SESSION-1e, разд. 4): «такое наполнение не показывается нигде, где виден его родной текст: узнавания, phrase_other_slot,
// phrase_repeat начинающего, чипы phrase_intro и ложные варианты у других каркасов». Catches a recognition or a production
// said with it, a wrong option that is its sentence or its value, a chip or a filler of a frame that still carries it — on
// any card of the day at either level — and the said filler hidden with it.
it('shows a hidden filler on no card of the day — no recognition, no production, no option, no chip, no frame — at both levels', function () {
    // Hidden: p1 «neck», p3 «constant», p5 «for two days». Kept though found: p1 «lower back» (said), p6 «a follow-up
    // appointment» (said in x8).
    $scene = s1sScene(['p1.f1', 'p1.f2', 'p3.f3', 'p5.f2', 'p6.f2']);
    $hidden = ['p1' => [1], 'p3' => [2], 'p5' => [1]];
    $targets = ['neck', 'constant', 'for two days'];
    $sentences = ['У него болит шея.', 'Боль постоянная, когда он наклоняется.', 'Он будет отдыхать два дня.'];
    $natives = ['шея', 'постоянная', 'два дня'];

    foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
        $cards = s1sDay($scene, $level);
        $seen = [];
        foreach ($cards as $card) {
            $payload = $card->payload();
            $label = "{$level->value} {$card->kind()->value}@{$card->unitRef()}";
            foreach (s1sFillerLists($card, $payload) as $list) {
                expect(array_intersect($list['indexes'], $hidden[$list['ref']] ?? []))->toBe([], $label);
                foreach ($list['indexes'] as $index) {
                    $seen[$list['ref']][$index] = true;
                }
            }
            if ($card->unitKind() === UnitKind::Phrase) {
                expect(in_array(PhraseSeries::fillerOf($card->kind(), $payload), $hidden[$card->unitRef()] ?? [], true))->toBeFalse($label);
            }
            foreach ($payload['options'] ?? [] as $option) {
                // `listen_predict` offers whole lines: its text key is `text_target` (наряд BACK-TAILS-1 §1.2).
                foreach ([$option['text'] ?? null, $option['text_target'] ?? null, $option['text_native'] ?? null] as $text) {
                    expect(in_array($text, [...$targets, ...$sentences], true))->toBeFalse($label);
                }
            }
            $spoken = [
                ...($payload['examples'] ?? []),
                ...($payload['own_round']['examples'] ?? []),
                ...array_column($payload['rounds'] ?? [], 'task_native'),
                $payload['task_native'] ?? null,
                $payload['own_round']['task_native'] ?? null,
            ];
            foreach ($spoken as $text) {
                expect(in_array($text, [...$natives, ...$sentences], true))->toBeFalse($label);
            }
            foreach ($payload['rounds'] ?? [] as $round) {
                expect(in_array($round['filler_index'], $hidden[$card->unitRef()] ?? [], true))->toBeFalse($label)
                    ->and(in_array($round['expected_text'], $targets, true))->toBeFalse($label);
            }
            foreach ($payload['frames'] ?? [] as $frame) {
                expect(in_array($frame['said']['index'], $hidden[$frame['ref']] ?? [], true))->toBeFalse($label);
            }
        }

        // What the dialogue says stays everywhere: p1 «lower back» and p6 «a follow-up appointment» are on the cards.
        $p1 = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->unitRef() === 'p1' && in_array($c->kind(), PhraseSeries::CYCLE, true)));
        expect($seen['p1'] ?? [])->toBe([0 => true, 2 => true], $level->value)
            ->and(array_keys($seen['p6'] ?? []))->toEqualCanonicalizing([0, 1, 2])
            ->and(array_map(static fn (DayCard $c): ?int => PhraseSeries::fillerOf($c->kind(), $c->payload()), $p1))->toContain(0);
    }
});

// Canon (SESSION-1e, разд. 4): «помечены все несказанные — серия узнаваний короче (одно узнавание на said)»; and (наряд FIX-3
// §3): «наполнений ≥ 2 → кругов ≥ 2 (+ своё)» — the owner's «How heavy should ___ be?», three values, one round, because the
// seam judge had hidden the other two. Catches a series that still counts the hidden fillers (two recognitions, one said
// twice), a recognition built on a hidden filler, «Скажи целиком» left with one round, a round that shows the sentence the
// judge said does not read, and more hidden values taken than the second round needs.
it('recognises a frame whose other fillers all do not read once — with the said one — and says it in two rounds all the same', function () {
    foreach ([S1S_SCENE, '01J8SESS1ESEAMS00000000003'] as $id) {
        $scene = s1sScene(['p1.f2', 'p1.f3'], null, $id);
        $p1 = s1sTerm($scene, 'p1');

        expect(PhraseSeries::fillers($scene, $p1))->toBe([0])
            ->and(PhraseSeries::most($scene, $p1))->toBe(1)
            ->and(PhraseSeries::repeatFillers($scene, $p1))->toBe([]);

        foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
            $label = "{$id} {$level->value}";
            $phrases = array_values(array_filter(s1sDay($scene, $level), static fn (DayCard $c): bool => $c->stage() === Stage::Phrases));
            $ofP1 = array_values(array_filter($phrases, static fn (DayCard $c): bool => $c->unitRef() === 'p1' && $c->kind() !== CardKind::PhraseCombine));
            $kinds = array_map(static fn (DayCard $c): CardKind => $c->kind(), $ofP1);
            $whole = $ofP1[2]->payload();

            expect($ofP1)->toHaveCount(3, $label)
                ->and($kinds[0])->toBe(CardKind::PhraseIntro)
                ->and(in_array($kinds[1], PhraseSeries::OPENERS, true))->toBeTrue($label)
                ->and(PhraseSeries::fillerOf($ofP1[1]->kind(), $ofP1[1]->payload()))->toBe(0)
                ->and($kinds[2])->toBe(CardKind::PhraseOtherSlot, $label)
                // Two rounds: the said value, and the first hidden one — with its VALUE as the line, not «У него болит шея.»
                // the judge said does not read; the third stays hidden.
                ->and($whole['rounds'])->toBe([
                    ['filler_index' => 0, 'expected_text' => 'It hurts in his lower back.', 'task_native' => 'У него болит поясница.'],
                    ['filler_index' => 1, 'expected_text' => 'It hurts in his neck.', 'task_native' => 'Шея'],
                ])
                ->and(array_column($whole['frame']['slot']['fillers'], 'index'))->toBe([0, 1])
                ->and(array_column($whole['frame']['slot']['fillers'], 'native_line'))->toBe(['У него болит поясница.', 'Шея'])
                ->and($whole['own_round']['examples'])->toBe(['поясница', 'шея'])
                // Every other card shows the said value alone, as before.
                ->and(array_column($ofP1[0]->payload()['frame']['slot']['fillers'], 'index'))->toBe([0]);
        }
    }
});

// Canon (SESSION-1e, разд. 4; SESSION-1d, разд. 4): the copy and the return of a phrase card are the same kind said with another
// filler — never a hidden one. Catches a copy or a return said with a filler no card of the day may show.
it('says no copy and no return with a hidden filler, and builds no card on one', function () {
    $scene = s1sScene(['p1.f2']);
    $p1 = s1sTerm($scene, 'p1');
    $stage = new PhrasesStage;
    $cards = new PhraseCards;
    $filler = static fn (?object $draft): ?int => $draft === null ? null : PhraseSeries::fillerOf($draft->kind, $draft->payload);

    // Failed on 0 with 0 taken: without the finding the copy would be 1; it is 2.
    $mid = PlanLevel::Intermediate;

    expect($filler($stage->again($scene, $p1, CardKind::PhraseSlot, 0, [0], $mid)))->toBe(2)
        ->and($filler($stage->returned($scene, $p1, CardKind::PhraseChooseBack, 0, $mid)))->toBe(2)
        ->and($filler($stage->again($scene, $p1, CardKind::PhraseRepeat, 2, [0, 2], $mid)))->toBe(0)
        ->and(PhraseSeries::repeatFillers($scene, $p1))->toBe([2])
        // «Скажи целиком» walks the values a card may show, and the hidden one is not among them.
        ->and(array_column($cards->sayWhole($scene, $p1, $mid)?->payload['rounds'] ?? [], 'filler_index'))->toBe([0, 2])
        ->and($cards->slot($scene, $p1, 1))->toBeNull()
        ->and($cards->chooseBack($scene, $p1, 1))->toBeNull()
        ->and($cards->slotListen($scene, $p1, 1))->toBeNull()
        ->and($cards->assemble($scene, $p1, 1))->toBeNull()
        ->and($cards->repeat($scene, $p1, 1))->toBeNull()
        ->and($cards->slot(s1sScene([]), s1sTerm(s1sScene([]), 'p1'), 1))->not->toBeNull();
});

// Canon (SESSION-1e, разд. 4): «нигде, где виден его родной текст» — the value of a number card's wrong option is read off the
// fillers too. Catches a hidden filler's native value offered beside the number of the visit. Наряд BACK-TAILS-1 §1.3
// narrowed what an option may be at all, so the filler to hide here is an AMOUNT: «два дня», which the card would offer.
it('leaves the value of a hidden filler out of listen_number', function () {
    $shown = [];
    $hidden = [];
    foreach (range(10, 21) as $n) {
        $id = '01J8SESS1ESEAMS0000000'.$n.'00';
        foreach (array_column(ListenCards::number(s1sScene([], null, $id))?->payload['options'] ?? [], 'text') as $text) {
            $shown[$text] = true;
        }
        foreach (array_column(ListenCards::number(s1sScene(['p5.f2'], null, $id))?->payload['options'] ?? [], 'text') as $text) {
            $hidden[$text] = true;
        }
    }

    // p5's «два дня» is a value of the day — until the judge says its sentence does not read.
    expect(array_intersect(array_keys($shown), ['Два дня']))->not->toBe([])
        ->and(array_intersect(array_keys($hidden), ['Два дня']))->toBe([]);
});
