<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Assembly\DayAssembler;
use App\Modules\Plan\Domain\Assembly\PhraseSeries;
use App\Modules\Plan\Domain\Assembly\ReturnedUnit;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Service\DayMetricsCalculator;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * THE DAY OF THE REGISTRY, DEALT WHOLE (наряд SESSION-1a, разд. 2; SPEC §4 «DayAssembler»): the five stages of the
 * clean fake lesson per level with their exact counts, positions running per stage, the same day dealt twice, the
 * returns at the end of their own stage (and the day's listening never among them), the review day's ten and the
 * rehearsal's twelve — and the day's numbers read off the dealt cards.
 *
 * The fake lesson (`FakePlanModel::lessonPayload`, 8 words, 8 exchanges): v1–v8 of which «lower back», «muscle strain»,
 * «heating pad», «follow-up appointment», «sick note» have two words (the checks of all eight walk one circle, SESSION-1e); p1–p6 of which p4 has no slot; x1–x5 answer,
 * x6 rescue, x7–x8 ask on p6; three listening questions.
 */

/** A fixed scene id — the rotations and shuffles inside are seeded by it. */
function s1daSceneId(int $n = 1): PlanSceneId
{
    return PlanSceneId::fromString(sprintf('01J8SESSDAYASSEMB%09d', $n));
}

function s1daScene(PlanLevel $level = PlanLevel::Intermediate, int $n = 1): SceneMaterial
{
    $sceneId = s1daSceneId($n);
    $packs = lessonPacks();
    $payload = FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', $level, null, 8, 8, FakePlanModel::roles(), new EarlierDays));
    $lesson = LessonAssembly::serve((new LessonParser)->parse($payload), $sceneId->value, $packs->for('en'));

    return new SceneMaterial($sceneId, $lesson, PlanTerm::fromLesson($sceneId, $lesson, static fn (): PlanTermId => PlanTermId::generate()), $packs->for('en'), $packs->for('ru'));
}

/** An id generator that gives the same ids in the same order every time it is made. */
function s1daIds(): Closure
{
    $n = 0;

    return static function () use (&$n): DayCardId {
        return DayCardId::fromString(sprintf('01J8SESSCARD%014d', ++$n));
    };
}

/**
 * @param  list<ReturnedUnit>  $returned
 * @param  array<string, SceneMaterial>  $extra  the scenes the returns come from
 * @return list<DayCard>
 */
function s1daDeal(SceneMaterial $scene, PlanLevel $level, array $returned = [], array $extra = [], ?Closure $ids = null): array
{
    return (new DayAssembler)->sceneDay(
        PlanDayId::fromString('01J8SESSDAY000000000000001'), $scene, [$scene->sceneId->value => $scene, ...$extra], $level, $returned, [],
        $ids ?? static fn (): DayCardId => DayCardId::generate(),
    );
}

/**
 * @param  list<DayCard>  $cards
 * @return list<DayCard>
 */
function s1daIn(array $cards, Stage $stage): array
{
    return array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === $stage));
}

/**
 * @param  list<DayCard>  $cards
 * @return list<string> `kind@ref` of each card
 */
function s1daShape(array $cards): array
{
    return array_map(static fn (DayCard $c): string => $c->kind()->value.'@'.$c->unitRef(), $cards);
}

/**
 * @param  list<DayCard>  $cards
 * @return array<string, int> kind → how many
 */
function s1daKinds(array $cards): array
{
    $out = array_count_values(array_map(static fn (DayCard $c): string => $c->kind()->value, $cards));
    ksort($out);

    return $out;
}

/**
 * Everything a dealt card is, as plain values.
 *
 * @param  list<DayCard>  $cards
 * @return list<array<string, mixed>>
 */
function s1daSnapshot(array $cards): array
{
    return array_map(static fn (DayCard $c): array => [
        'id' => $c->id()->value, 'day' => $c->dayId()->value, 'stage' => $c->stage()->value, 'position' => $c->position(),
        'kind' => $c->kind()->value, 'unit' => [$c->unitKind()->value, $c->unitRef()], 'source' => $c->source()->value,
        'source_day' => $c->sourceDayId()?->value, 'retry_of' => $c->retryOf()?->value, 'result' => $c->result()?->value,
        'attempts' => $c->attempts(), 'returns' => $c->returns(), 'response' => $c->response(),
        'payload' => json_encode($c->payload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ], $cards);
}

// Canon (разд. 2; SESSION-1d «Фразы» через разные окна): the day's stages, counted exactly on the clean lesson. Catches a
// stage dealing a card too many or too few — a lost spacing slot, a recognition too many or too few, a second
// phrase_combine, a rescue dealt twice, a listen card per ask missing, a seventh speak_answer.
it('deals the clean lesson in five stages of exactly 24, 29, 13, 9 and 8 cards, at either level', function (PlanLevel $level) {
    $cards = s1daDeal(s1daScene($level), $level);
    $words = s1daIn($cards, Stage::Words);
    $phrases = s1daIn($cards, Stage::Phrases);

    // Words: 8 terms × 3 (intro, repeat, check) = 24. The checks walk one circle over all the words (SESSION-1e): every
    // kind twice — a chunk is not always assembled, a single word not always chosen.
    expect(count($words))->toBe(24)
        ->and(s1daKinds($words))->toBe([
            'word_assemble' => 2, 'word_choose' => 2, 'word_in_line' => 2, 'word_intro' => 8, 'word_listen' => 2, 'word_repeat' => 8,
        ]);

    // Phrases (SESSION-1d): five frames with a window × (intro, three recognitions — two to every frame, a third while the
    // stage fits in 540 s — production) = 25, p4 without a slot: intro, phrase_choose_back, phrase_repeat = 3, + one
    // phrase_combine = 29.
    $production = array_values(array_filter($phrases, static fn (DayCard $c): bool => in_array($c->kind(), [CardKind::PhraseRepeat, CardKind::PhraseOtherSlot, CardKind::PhraseOwnSlot], true)));
    $recognitions = array_values(array_filter($phrases, static fn (DayCard $c): bool => in_array($c->kind(), PhraseSeries::CYCLE, true)));
    expect(count($phrases))->toBe(29)
        ->and(count($recognitions))->toBe(16)
        ->and(s1daKinds($phrases)['phrase_intro'])->toBe(6)
        ->and(s1daKinds($phrases)['phrase_combine'])->toBe(1)
        ->and(end($phrases)->kind())->toBe(CardKind::PhraseCombine)
        ->and(count($production))->toBe(6)
        ->and(s1daShape(array_values(array_filter($phrases, static fn (DayCard $c): bool => $c->unitRef() === 'p4'))))
        ->toBe(['phrase_intro@p4', 'phrase_choose_back@p4', 'phrase_repeat@p4']);
    if ($level === PlanLevel::Beginner) {
        // A Beginner repeats every frame.
        expect(array_unique(array_map(static fn (DayCard $c): string => $c->kind()->value, $production)))->toBe(['phrase_repeat']);
    } else {
        // An Intermediate repeats only p4 and varies the five frames with fillers (other_slot / own_slot, seeded).
        expect(count(array_filter($production, static fn (DayCard $c): bool => $c->kind() === CardKind::PhraseRepeat)))->toBe(1);
    }

    // Dialogue: x1–x4 answer → partner + answer (8); x5 answer followed by the rescue x6 → partner(x5), rescue(x6),
    // answer(x5) (3, D-17); x7, x8 ask → ONE card each, which carries the check itself (2, наряд BACK-TAILS-1 §1.5).
    // 8 + 3 + 2 = 13.
    expect(s1daShape(s1daIn($cards, Stage::Dialogue)))->toBe([
        'dialogue_partner@x1', 'dialogue_answer@x1', 'dialogue_partner@x2', 'dialogue_answer@x2',
        'dialogue_partner@x3', 'dialogue_answer@x3', 'dialogue_partner@x4', 'dialogue_answer@x4',
        'dialogue_partner@x5', 'dialogue_rescue@x6', 'dialogue_answer@x5',
        'dialogue_ask@x7', 'dialogue_ask@x8',
    ])
        // Listen: the visit (1) + a question each (3) + the review (1) + a prediction per ask exchange (2) + the pace
        // line (1) + the number («three days ago», 1) = 9. Every card is the day's, the questions each their own L{n}.
        ->and(s1daShape(s1daIn($cards, Stage::Listen)))->toBe([
            'listen_dialogue@day', 'listen_question@L1', 'listen_question@L2', 'listen_question@L3', 'listen_review@day',
            'listen_predict@day', 'listen_predict@day', 'listen_pace@day', 'listen_number@day',
        ])
        ->and(array_unique(array_map(static fn (DayCard $c): string => $c->unitKind()->value, s1daIn($cards, Stage::Listen))))->toBe(['day'])
        // Speak: speak_answer on the seven eligible exchanges (x1–x5, x7, x8; the rescue has no frame), the first six
        // kept; the echo on the longest partner line of ≤ 18 words the pace line (x3) did not take (x5, 14 words);
        // the retell on the learner's own line of x8 — the one exchange the six answers left (наряд BACK-TAILS-1 §1.1).
        // 6 + 1 + 1 = 8.
        ->and(s1daShape(s1daIn($cards, Stage::Speak)))->toBe([
            'speak_answer@x1', 'speak_answer@x2', 'speak_answer@x3', 'speak_answer@x4', 'speak_answer@x5', 'speak_answer@x7',
            'speak_echo@x5', 'speak_retell@x8',
        ])
        ->and(count($cards))->toBe(24 + 29 + 13 + 9 + 8);

    foreach (Stage::ordered() as $stage) {
        expect(array_map(static fn (DayCard $c): int => $c->position(), s1daIn($cards, $stage)))->toBe(range(1, count(s1daIn($cards, $stage))));
    }
    expect(array_filter($cards, static fn (DayCard $c): bool => ! $c->kind()->isDealt()))->toBe([])
        ->and(array_unique(array_map(static fn (DayCard $c): string => $c->source()->value, $cards)))->toBe(['today'])
        ->and(array_unique(array_map(static fn (DayCard $c): mixed => $c->payload()['scene_id'] ?? null, $cards)))->toBe([s1daSceneId()->value]);
})->with([PlanLevel::Beginner, PlanLevel::Intermediate]);

// Canon (разд. 0): «повторная сборка даёт тот же день». Catches a shuffle or a rotation seeded by chance or by time,
// and anything of a term's generated id leaking into a payload.
it('deals the same day twice with the same ids: every card, its position and its payload identical', function (PlanLevel $level) {
    $first = s1daDeal(s1daScene($level), $level, ids: s1daIds());
    $second = s1daDeal(s1daScene($level), $level, ids: s1daIds());

    expect(s1daSnapshot($second))->toBe(s1daSnapshot($first))
        ->and($first[0]->id()->value)->toBe('01J8SESSCARD00000000000001');
})->with([PlanLevel::Beginner, PlanLevel::Intermediate]);

// Canon (§6, разд. 2; SESSION-1d разд. 4): «провал дважды → в следующий день одной карточкой в конце своего этапа; фраза —
// видом последнего провала, с другим наполнением». Catches a return dealt in the middle of its stage, dealt twice, a frame
// dealt as another kind than it failed as or with the failed filler, a day unit brought back, a lost source day.
it('deals the returns once each at the end of their stage: a word as word_choose, a frame as it failed, an exchange as speak_answer, never the day', function () {
    $today = s1daScene(PlanLevel::Beginner);
    $yesterday = s1daScene(PlanLevel::Beginner, 2);
    $failedOn = PlanDayId::fromString('01J8SESSDAY000000000000000');
    $from = static fn (UnitKind $kind, string $ref, ?PlanSceneId $scene = null, ?CardKind $as = null, ?int $filler = null): ReturnedUnit => new ReturnedUnit($scene ?? $yesterday->sceneId, $kind, $ref, $failedOn, $as, $filler);

    $cards = s1daDeal($today, PlanLevel::Beginner, [
        $from(UnitKind::Word, 'v3'),
        $from(UnitKind::Day, 'day'),            // the day's listening never returns
        $from(UnitKind::Day, 'L1'),             // nor one of its questions
        $from(UnitKind::Phrase, 'p2', null, CardKind::PhraseSlotListen, 1),   // failed as slot_listen said with filler 1
        $from(UnitKind::Exchange, 'x4'),
        $from(UnitKind::Phrase, 'p4', null, CardKind::PhraseChooseBack),     // a frame without a slot: said as itself
        $from(UnitKind::Phrase, 'p3', null, CardKind::PhraseRepeat, 1),       // said aloud and given up on: said again
        $from(UnitKind::Phrase, 'p5'),          // failed as a kind not known: its first recognition
        $from(UnitKind::Word, 'v3'),            // failed on two earlier days: dealt once
        $from(UnitKind::Exchange, 'x6'),        // a rescue has no speak_answer: nothing to deal
        $from(UnitKind::Word, 'v99'),           // a ref the scene does not have
        $from(UnitKind::Word, 'v1', s1daSceneId(9)), // a scene the dealer did not load
    ], [$yesterday->sceneId->value => $yesterday]);

    $back = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->source() === CardSource::Returned));
    $words = s1daIn($cards, Stage::Words);
    $phrases = s1daIn($cards, Stage::Phrases);
    $speak = s1daIn($cards, Stage::Speak);

    $firstOfP5 = PhraseSeries::kind($yesterday, $yesterday->term('p5'), 0)->value;
    expect(s1daShape($back))->toBe(['word_choose@v3', 'phrase_slot_listen@p2', 'phrase_choose_back@p4', 'phrase_repeat@p3', $firstOfP5.'@p5', 'speak_answer@x4'])
        ->and(array_map(static fn (DayCard $c): ?int => PhraseSeries::fillerOf($c->kind(), $c->payload()), array_slice($back, 1, 4)))->toBe([2, null, 2, 0])
        ->and(array_map(static fn (DayCard $c): ?string => $c->sourceDayId()?->value, $back))->toBe(array_fill(0, 6, $failedOn->value))
        ->and(array_unique(array_map(static fn (DayCard $c): string => $c->payload()['scene_id'], $back)))->toBe([$yesterday->sceneId->value])
        // Today's stages as they were, the returns after them.
        ->and(count($words))->toBe(24 + 1)
        ->and(end($words)->source())->toBe(CardSource::Returned)
        ->and(end($words)->position())->toBe(25)
        ->and(count($phrases))->toBe(29 + 4)
        ->and(s1daShape(array_slice($phrases, 29)))->toBe(['phrase_slot_listen@p2', 'phrase_choose_back@p4', 'phrase_repeat@p3', $firstOfP5.'@p5'])
        ->and(array_slice($phrases, 28, 1)[0]->kind())->toBe(CardKind::PhraseCombine)
        ->and(count(s1daIn($cards, Stage::Dialogue)))->toBe(13)
        ->and(count(s1daIn($cards, Stage::Listen)))->toBe(9)
        ->and(count($speak))->toBe(8 + 1)
        ->and(end($speak)->unitRef())->toBe('x4')
        ->and(end($speak)->position())->toBe(9)
        ->and(array_filter($back, static fn (DayCard $c): bool => $c->unitKind() === UnitKind::Day))->toBe([]);
});

// Canon (разд. 2): «Говорю сам — speak_answer по обменам двух предыдущих сцен, ≤ 10 (seeded)»; the returns at the end.
// Catches a review over the cap, an exchange dealt both as a return and as a review card, a pick in shuffled order,
// a review that is not the same twice.
it('deals a review day of at most ten speak_answer over two scenes in their order, the returned exchange left to its return', function () {
    $a = s1daScene(PlanLevel::Beginner, 1);
    $b = s1daScene(PlanLevel::Beginner, 2);
    $failedOn = PlanDayId::fromString('01J8SESSDAY000000000000000');
    $returned = [
        new ReturnedUnit($a->sceneId, UnitKind::Word, 'v1', $failedOn),
        new ReturnedUnit($a->sceneId, UnitKind::Exchange, 'x2', $failedOn),
        new ReturnedUnit($b->sceneId, UnitKind::Day, 'day', $failedOn),
    ];
    $review = static fn (): array => (new DayAssembler)->reviewDay(
        PlanDayId::fromString('01J8SESSDAY000000000000003'), [$a, $b], [$a->sceneId->value => $a, $b->sceneId->value => $b],
        $returned, [], s1daIds(),
    );
    $cards = $review();
    $speak = s1daIn($cards, Stage::Speak);
    $today = array_values(array_filter($speak, static fn (DayCard $c): bool => $c->source() === CardSource::Today));
    $order = array_map(static fn (DayCard $c): array => [$c->payload()['scene_id'], (int) substr($c->unitRef(), 1)], $today);
    $sorted = $order;
    sort($sorted);

    // 7 eligible exchanges a scene (x1–x5, x7, x8) × 2 = 14, less the returned A:x2 = 13 → the seeded first 10.
    expect(s1daShape(s1daIn($cards, Stage::Words)))->toBe(['word_choose@v1'])
        ->and(count($today))->toBe(10)
        ->and(array_unique(array_map(static fn (DayCard $c): string => $c->kind()->value, $speak)))->toBe(['speak_answer'])
        ->and($order)->toBe($sorted)
        ->and(in_array([$a->sceneId->value, 2], $order, true))->toBeFalse()
        ->and(s1daShape(array_slice($speak, 10)))->toBe(['speak_answer@x2'])
        ->and(array_slice($speak, 10)[0]->source())->toBe(CardSource::Returned)
        ->and(array_map(static fn (DayCard $c): int => $c->position(), $speak))->toBe(range(1, 11))
        ->and(count(s1daIn($cards, Stage::Phrases)) + count(s1daIn($cards, Stage::Dialogue)) + count(s1daIn($cards, Stage::Listen)))->toBe(0)
        ->and(s1daSnapshot($review()))->toBe(s1daSnapshot($cards));
});

// Canon (разд. 2): «Репетиция: speak_answer по всем сценам плана, ≤ 12 (по одному-два на сцену, seeded)». Catches a
// rehearsal over the cap, a scene given three, a second card before every scene has its first, a scene left out
// while there is room.
it('deals a rehearsal of at most twelve speak_answer — one a scene first, then a second in order while there is room', function (int $scenes, array $perScene) {
    $material = array_map(static fn (int $n): SceneMaterial => s1daScene(PlanLevel::Intermediate, $n), range(1, $scenes));
    $byId = [];
    foreach ($material as $scene) {
        $byId[$scene->sceneId->value] = $scene;
    }
    $cards = (new DayAssembler)->rehearsalDay(PlanDayId::fromString('01J8SESSDAY000000000000004'), $material, $byId, [], s1daIds());

    $counts = [];
    foreach ($material as $scene) {
        $counts[] = count(array_filter($cards, static fn (DayCard $c): bool => $c->payload()['scene_id'] === $scene->sceneId->value));
    }
    $order = array_map(static fn (DayCard $c): array => [$c->payload()['scene_id'], (int) substr($c->unitRef(), 1)], $cards);
    $sorted = $order;
    sort($sorted);

    expect($counts)->toBe($perScene)
        ->and(count($cards))->toBe(array_sum($perScene))
        ->and(array_unique(array_map(static fn (DayCard $c): string => $c->kind()->value, $cards)))->toBe(['speak_answer'])
        ->and(array_unique(array_map(static fn (DayCard $c): string => $c->stage()->value, $cards)))->toBe(['speak'])
        ->and(array_map(static fn (DayCard $c): int => $c->position(), $cards))->toBe(range(1, count($cards)))
        ->and($order)->toBe($sorted);
})->with([
    // Two scenes: one each, then a second each — 4, well under the cap.
    'two scenes' => [2, [2, 2]],
    // Eight scenes: 8 firsts, then seconds for the first four until 12.
    'eight scenes' => [8, [2, 2, 2, 2, 1, 1, 1, 1]],
    // Thirteen scenes: the first twelve get one card, the thirteenth none.
    'thirteen scenes' => [13, [...array_fill(0, 12, 1), 0]],
]);

// Canon (SESSION-1a, хвост): a unit comes back on the nearest following day of ANY type — the rehearsal takes yesterday's
// returns at the end of their stages, and an exchange coming back is not also one of the rehearsal's own cards.
it('deals yesterday\'s returns on a rehearsal at the end of their stages, the returned exchange left to its return', function () {
    $material = array_map(static fn (int $n): SceneMaterial => s1daScene(PlanLevel::Beginner, $n), [1, 2]);
    $byId = [];
    foreach ($material as $scene) {
        $byId[$scene->sceneId->value] = $scene;
    }
    $failedOn = PlanDayId::fromString('01J8SESSDAY000000000000003');
    $returned = [
        new ReturnedUnit($material[1]->sceneId, UnitKind::Word, 'v3', $failedOn),
        new ReturnedUnit($material[0]->sceneId, UnitKind::Exchange, 'x1', $failedOn),
    ];
    $plain = (new DayAssembler)->rehearsalDay(PlanDayId::fromString('01J8SESSDAY000000000000004'), $material, $byId, [], s1daIds());
    $cards = (new DayAssembler)->rehearsalDay(PlanDayId::fromString('01J8SESSDAY000000000000004'), $material, $byId, $returned, s1daIds());

    $back = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->source() === CardSource::Returned));
    $speak = s1daIn($cards, Stage::Speak);
    $own = array_values(array_filter($speak, static fn (DayCard $c): bool => $c->source() === CardSource::Today));

    expect(s1daShape($back))->toBe(['word_choose@v3', 'speak_answer@x1'])
        ->and(s1daShape(s1daIn($cards, Stage::Words)))->toBe(['word_choose@v3'])
        ->and(end($speak)->source())->toBe(CardSource::Returned)
        ->and(end($speak)->unitRef())->toBe('x1')
        // The returned exchange is not also a rehearsal card of its scene.
        ->and(array_filter($own, static fn (DayCard $c): bool => $c->unitRef() === 'x1' && $c->payload()['scene_id'] === $material[0]->sceneId->value))->toBe([])
        ->and(count($own))->toBe(count($plain));
});

// Moved from the assembly test of the old registry (its kinds are gone): the day's numbers are its dealt cards'.
// Catches a pause over ten minutes counted, and a total or a count that is not the cards'.
it('computes the day metrics from a dealt day: dealt, done, minutes without the long pauses', function () {
    $cards = s1daDeal(s1daScene(PlanLevel::Beginner), PlanLevel::Beginner);
    $t = new DateTimeImmutable('2026-09-15T10:00:00Z');
    foreach (array_slice($cards, 0, 20) as $i => $card) {
        // Twenty answers 30 s apart, with one hour's pause before the eleventh: 9 × 30 s + 9 × 30 s = 9 minutes.
        $card->answer($card->kind()->isJudged() ? CardResult::Skipped : CardResult::Passed, 1, null, $t->modify('+'.($i * 30 + ($i >= 10 ? 3600 : 0)).' seconds'));
    }

    $metrics = (new DayMetricsCalculator)->calculate($cards);

    expect($metrics->cardsTotal)->toBe(83)
        ->and($metrics->cardsDone)->toBe(20)
        ->and($metrics->minutesSpent)->toBe(9);
});
