<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Assembly\Audio;
use App\Modules\Plan\Domain\Assembly\CardDraft;
use App\Modules\Plan\Domain\Assembly\CardObjects;
use App\Modules\Plan\Domain\Assembly\PhraseCards;
use App\Modules\Plan\Domain\Assembly\PhraseSeries;
use App\Modules\Plan\Domain\Assembly\PhrasesStage;
use App\Modules\Plan\Domain\Assembly\Rotation;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\Service\Shuffle;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * «ФРАЗЫ» (наряд SESSION-1a; SESSION-1d — фраза через разные окна): every frame met, recognised through several windows
 * and said, one phrase_combine last; how many recognitions a frame gets (two to every frame with a window, a third to the
 * most said while the stage fits in 540 s), which filler and which kind each one is, what makes it right and what its
 * wrong options are, what a beginner repeats and an intermediate learner varies, the spacing, a copy and a return — and
 * the payload of every one of the nine kinds, key by key, on the clean fake lesson: p1…p5 answer frames said once (p4
 * without a window), p6 an ask frame said twice (x7, x8), x6 a rescue.
 */

/** Seeds depend on the scene: every seeded outcome below is asserted on this one. */
function s1pSceneId(): string
{
    return '01J8SESS10N1APHRASES000000';
}

/** @return array<string, mixed> the clean fake lesson as the model writes it */
function s1pPayload(): array
{
    return FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Intermediate, null, 8, 8));
}

/** @param  (Closure(array<string, mixed>): array<string, mixed>)|null  $edit  a change to the model's answer before it is served */
function s1pScene(?Closure $edit = null, ?string $sceneId = null): SceneMaterial
{
    $id = PlanSceneId::fromString($sceneId ?? s1pSceneId());
    $packs = lessonPacks();
    $payload = s1pPayload();
    if ($edit !== null) {
        $payload = $edit($payload);
    }
    $lesson = LessonAssembly::serve((new LessonParser)->parse($payload), $id->value, $packs->for('en'));

    return new SceneMaterial(
        $id, $lesson, PlanTerm::fromLesson($id, $lesson, static fn (): PlanTermId => PlanTermId::generate()),
        $packs->for('en'), $packs->for('ru'),
    );
}

function s1pTerm(SceneMaterial $scene, string $ref): PlanTerm
{
    $term = $scene->term($ref);
    expect($term)->not->toBeNull();

    return $term;
}

/** Every kind a card of the day costs the same seconds — the stage's time is its count × `$seconds`. */
function s1pPace(int $seconds): DayPace
{
    return new DayPace(array_fill_keys(array_keys(DayPace::DEFAULTS), $seconds));
}

function s1pStage(?DayPace $pace = null): PhrasesStage
{
    return new PhrasesStage(new PhraseCards, $pace ?? new DayPace);
}

/**
 * @param  list<array<string, mixed>>  $options
 * @return array<string, mixed> the text of every option, by id
 */
function s1pTexts(array $options): array
{
    return array_column($options, 'text', 'id');
}

/**
 * The cards of one frame in the order dealt — `phrase_combine` left out (it is the day's, not the frame's series).
 *
 * @param  list<CardDraft>  $drafts
 * @return list<CardDraft>
 */
function s1pOf(array $drafts, string $ref): array
{
    return array_values(array_filter($drafts, static fn (CardDraft $d): bool => $d->unitRef === $ref && $d->kind !== CardKind::PhraseCombine));
}

/**
 * @param  list<CardDraft>  $drafts
 * @return list<CardDraft> the recognitions of one frame
 */
function s1pRecognitions(array $drafts, string $ref): array
{
    return array_values(array_filter(s1pOf($drafts, $ref), static fn (CardDraft $d): bool => in_array($d->kind, PhraseSeries::CYCLE, true)));
}

/**
 * @param  list<CardDraft>  $drafts
 * @return list<int|null> the filler each card is said with
 */
function s1pFillers(array $drafts): array
{
    return array_map(static fn (CardDraft $d): ?int => PhraseSeries::fillerOf($d->kind, $d->payload), $drafts);
}

/**
 * The fewest cards of other frames between two cards of each frame, `phrase_combine` counted as a card of its frame.
 *
 * @param  list<CardDraft>  $drafts
 * @return array<string, int>
 */
function s1pGaps(array $drafts): array
{
    $last = [];
    $gaps = [];
    foreach ($drafts as $at => $draft) {
        if (isset($last[$draft->unitRef])) {
            $gaps[$draft->unitRef] = min($gaps[$draft->unitRef] ?? PHP_INT_MAX, $at - $last[$draft->unitRef] - 1);
        }
        $last[$draft->unitRef] = $at;
    }

    return $gaps;
}

// Canon (SESSION-1d, разд. 1–2): «интро → N узнаваний → произнесение», один phrase_combine в конце. Catches a frame dealt
// without its intro or its production, a production before a recognition, a second combine or one not last.
it('deals every frame its intro, then its recognitions, then its production, and one phrase_combine last', function () {
    $scene = s1pScene();

    foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
        $drafts = s1pStage()->build($scene, $level);
        $produce = [CardKind::PhraseRepeat, CardKind::PhraseOtherSlot, CardKind::PhraseOwnSlot];

        // Five frames with a window × (intro, three recognitions, production) + p4 (intro, choose_back, repeat) + combine.
        expect($drafts)->toHaveCount(29, $level->value)
            ->and(end($drafts)->kind)->toBe(CardKind::PhraseCombine)
            ->and(array_filter($drafts, static fn (CardDraft $d): bool => $d->kind === CardKind::PhraseCombine))->toHaveCount(1)
            ->and(array_map(static fn (CardDraft $d): string => $d->kind->value, s1pOf($drafts, 'p4')))
            ->toBe(['phrase_intro', 'phrase_choose_back', 'phrase_repeat']);

        foreach (['p1', 'p2', 'p3', 'p5', 'p6'] as $ref) {
            $cards = s1pOf($drafts, $ref);
            expect($cards)->toHaveCount(5, "{$level->value} {$ref}")
                ->and($cards[0]->kind)->toBe(CardKind::PhraseIntro)
                ->and(array_map(static fn (CardDraft $d): bool => in_array($d->kind, PhraseSeries::CYCLE, true), array_slice($cards, 1, 3)))->toBe([true, true, true])
                ->and(in_array($cards[4]->kind, $produce, true))->toBeTrue("{$level->value} {$ref}");
        }
        foreach ($drafts as $draft) {
            expect($draft->kind->stage())->toBe(Stage::Phrases)
                ->and($draft->unitKind)->toBe(UnitKind::Phrase)
                ->and(array_key_first($draft->payload))->toBe('scene_id')
                ->and($draft->payload['scene_id'])->toBe(s1pSceneId());
        }
    }
});

// Canon (SESSION-1d, решение архитектора 16.09): «два узнавания КАЖДОМУ каркасу с окном», своё наполнение каждому. Catches
// a frame given one recognition, or more than its fillers, and a frame without a window given more than one.
it('gives every frame with a window two recognitions, one per filler — a frame of one filler or none just one', function () {
    // A pace no third recognition fits in: the stage is over 540 s before any is added.
    $noThirds = s1pPace(30);
    $oneFiller = s1pScene(static function (array $payload): array {
        $payload['phrases'][4]['slot']['fillers'] = [$payload['phrases'][4]['slot']['fillers'][0]];

        return $payload;
    });

    foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
        $drafts = s1pStage($noThirds)->build($oneFiller, $level);
        $counts = [];
        foreach (['p1', 'p2', 'p3', 'p4', 'p5', 'p6'] as $ref) {
            $counts[$ref] = count(s1pRecognitions($drafts, $ref));
        }

        expect($counts)->toBe(['p1' => 2, 'p2' => 2, 'p3' => 2, 'p4' => 1, 'p5' => 1, 'p6' => 2], $level->value)
            ->and($drafts)->toHaveCount(6 + 10 + 6 + 1);
    }
});

// Canon (SESSION-1d): «третье — каркасам с наибольшим числом реплик (при равенстве — раньше в визите), пока этап ≤ 540 с по
// DayPace». Catches a third given by the frame's order instead of how often it is said, a third past the budget, a budget
// read as «< 540» (the stage that fits exactly), and a third to a frame that has only two fillers to say.
it('adds a third recognition to the most said frames first, while the stage still fits in 540 seconds by the day\'s pace', function () {
    $scene = s1pScene();
    $thirds = static function (array $drafts): array {
        $out = [];
        foreach (['p1', 'p2', 'p3', 'p4', 'p5', 'p6'] as $ref) {
            if (count(s1pRecognitions($drafts, $ref)) === 3) {
                $out[] = $ref;
            }
        }

        return $out;
    };

    // 24 cards without thirds. At 21 s a card: 504 s, one third fits (525), a second would not (546) — and it goes to p6,
    // the frame the dialogue says twice, though it stands last in the visit.
    $one = s1pStage(s1pPace(21))->build($scene, PlanLevel::Intermediate);
    // At 20 s: 480 s, three thirds make it exactly 540 — p6, then p1 and p2, said once each and earliest in the visit.
    $three = s1pStage(s1pPace(20))->build($scene, PlanLevel::Intermediate);
    // At the day's own pace every frame with three fillers gets one.
    $all = s1pStage()->build($scene, PlanLevel::Intermediate);

    expect($thirds($one))->toBe(['p6'])
        ->and(count($one) * 21)->toBe(525)
        ->and($thirds($three))->toBe(['p1', 'p2', 'p6'])
        ->and(count($three) * 20)->toBe(540)
        ->and($thirds($all))->toBe(['p1', 'p2', 'p3', 'p5', 'p6'])
        ->and(array_sum(array_map(static fn (CardDraft $d): int => (new DayPace)->seconds($d->kind), $all)))->toBeLessThanOrEqual(PhrasesStage::THIRD_BUDGET)
        // A frame of two fillers has nothing to say a third recognition with, however often the dialogue says it.
        ->and($thirds(s1pStage(s1pPace(1))->build(s1pScene(static function (array $payload): array {
            array_pop($payload['phrases'][5]['slot']['fillers']);

            return $payload;
        }), PlanLevel::Intermediate)))->toBe(['p1', 'p2', 'p3', 'p5']);
});

// Canon (SESSION-1d): «каждое узнавание берёт СВОЁ наполнение, ни одно не повторяется; первым — сказанное (said), дальше
// остальные по индексу». Catches a series that says the dialogue's filler again, or starts from index 0 whatever is said.
it('says every recognition with its own filler — the said one first, the rest by index — never one twice', function () {
    $drafts = s1pStage()->build(s1pScene(), PlanLevel::Intermediate);
    foreach (['p1', 'p2', 'p3', 'p5', 'p6'] as $ref) {
        expect(s1pFillers(s1pRecognitions($drafts, $ref)))->toBe([0, 1, 2], $ref);
    }

    // x1 says p1 with «neck»: its recognitions go neck → lower back → shoulder.
    $saysNeck = s1pScene(static function (array $payload): array {
        $payload['dialogue'][0]['messages'][1]['text_target'] = 'It hurts in his neck.';

        return $payload;
    });
    $neck = s1pStage()->build($saysNeck, PlanLevel::Beginner);
    expect($saysNeck->saidIndex(s1pTerm($saysNeck, 'p1')))->toBe(1)
        ->and(s1pFillers(s1pRecognitions($neck, 'p1')))->toBe([1, 0, 2])
        ->and(PhraseSeries::fillers($saysNeck, s1pTerm($saysNeck, 'p1')))->toBe([1, 0, 2]);
});

// Canon (SESSION-1d + решение архитектора 16.09): «вид чередуется по кругу slot → choose_back → slot_listen → assemble,
// начало круга — по зерну каркаса; первым узнаванием сборка не бывает». Catches one seed for the whole scene (every frame
// opens the same way), a cycle out of order, the assembly opening a frame, and a frame of one recognition not taking its
// opener.
it('walks the recognitions round the cycle from the frame\'s own seeded opener, never opening with the assembly', function () {
    $scene = s1pScene();
    $drafts = s1pStage()->build($scene, PlanLevel::Intermediate);
    $openers = [];
    foreach (['p1', 'p2', 'p3', 'p5', 'p6'] as $ref) {
        $opener = Rotation::pick($scene->seed("{$ref}:recognize"), 0, PhraseSeries::OPENERS);
        $start = array_search($opener, PhraseSeries::CYCLE, true);
        $openers[$ref] = $opener->value;
        expect(array_map(static fn (CardDraft $d): CardKind => $d->kind, s1pRecognitions($drafts, $ref)))
            ->toBe([PhraseSeries::CYCLE[$start], PhraseSeries::CYCLE[($start + 1) % 4], PhraseSeries::CYCLE[($start + 2) % 4]], $ref);
    }
    // The frames of this scene do not all open the same way.
    expect(count(array_unique($openers)))->toBeGreaterThan(1)
        ->and(s1pRecognitions($drafts, 'p4')[0]->kind)->toBe(CardKind::PhraseChooseBack);

    // Over a dozen scenes: every opener is a choice, and more than one of them opens a frame.
    $first = [];
    foreach (range(10, 21) as $n) {
        $other = s1pScene(null, '01J8SESS10N1APHRASES0000'.$n);
        $dealt = s1pStage()->build($other, PlanLevel::Beginner);
        foreach (['p1', 'p2', 'p3', 'p5', 'p6'] as $ref) {
            $first[s1pRecognitions($dealt, $ref)[0]->kind->value] = true;
        }
    }
    expect(array_keys($first))->not->toContain('phrase_assemble')
        ->and(count($first))->toBeGreaterThan(1);

    // A frame of one filler has one recognition: the opener of its cycle.
    $oneFiller = s1pScene(static function (array $payload): array {
        $payload['phrases'][0]['slot']['fillers'] = [$payload['phrases'][0]['slot']['fillers'][0]];

        return $payload;
    });
    expect(array_map(static fn (CardDraft $d): CardKind => $d->kind, s1pRecognitions(s1pStage()->build($oneFiller, PlanLevel::Beginner), 'p1')))
        ->toBe([Rotation::pick($oneFiller->seed('p1:recognize'), 0, PhraseSeries::OPENERS)]);
});

// Canon (SESSION-1d): «верное наполнение каждого узнавания определяется родным предложением или звуком: prompt_native — из
// frame_native с native ЭТОГО наполнения; phrase_slot_listen — звучит audio этого наполнения (p{N}.f{K})». Catches a card
// that still asks for the said sentence, plays the said file, or reads its answer off another filler.
it('makes every recognition right by its own filler: that filler\'s sentence in the learner\'s language, that filler\'s sound', function () {
    $scene = s1pScene();
    $series = new PhraseSeries;
    $p1 = s1pTerm($scene, 'p1');

    $slot = $series->card(CardKind::PhraseSlot, $scene, $p1, 1)?->payload;
    $back = $series->card(CardKind::PhraseChooseBack, $scene, $p1, 1)?->payload;
    $listen = $series->card(CardKind::PhraseSlotListen, $scene, $p1, 2)?->payload;
    $assemble = $series->card(CardKind::PhraseAssemble, $scene, $p1, 2)?->payload;
    $said = $series->card(CardKind::PhraseSlotListen, $scene, $p1, 0)?->payload;

    expect($slot['prompt_native'])->toBe('У него болит шея.')
        ->and(s1pTexts($slot['options'])[$slot['correct']])->toBe('neck')
        ->and(array_column($slot['options'], 'audio', 'id')[$slot['correct']])->toBe(Audio::of('p1.f2'))
        ->and($back['prompt'])->toBe([
            'text_target' => 'It hurts in his neck.', 'pronunciation_native' => 'ит хёртс ин хиз нэк', 'filler_index' => 1, 'audio' => Audio::of('p1.f2'),
        ])
        ->and(s1pTexts($back['options'])[$back['correct']])->toBe('У него болит шея.')
        ->and($listen['filler_index'])->toBe(2)
        ->and($listen['audio'])->toBe(Audio::of('p1.f3'))
        ->and(s1pTexts($listen['options'])[$listen['correct']])->toBe('shoulder')
        ->and($assemble['target_native'])->toBe('У него болит плечо.')
        ->and($assemble['expected']['filler_index'])->toBe(2)
        // The said filler sounds as the phrase itself.
        ->and($said['audio'])->toBe(Audio::of('p1'))
        ->and(s1pTexts($said['options'])[$said['correct']])->toBe('lower back');
});

// Canon (SESSION-1d): «ложные: сначала другие наполнения ЭТОГО каркаса, добор — наполнения других каркасов; совпадений по
// тексту нет; минимум два варианта». Catches wrong options taken from the next frame before the frame's own, and a top-up
// that is not another frame's filler.
it('offers the frame\'s other fillers first and tops up with the fillers of the next frames', function () {
    $scene = s1pScene();
    $series = new PhraseSeries;

    expect(array_values(s1pTexts($series->card(CardKind::PhraseSlot, $scene, s1pTerm($scene, 'p1'), 1)?->payload['options'])))
        ->toEqualCanonicalizing(['neck', 'lower back', 'shoulder', 'three days ago'])
        ->and(array_values(s1pTexts($series->card(CardKind::PhraseSlotListen, $scene, s1pTerm($scene, 'p3'), 0)?->payload['options'])))
        // p3's next frame with a window is p5 (p4 has none).
        ->toEqualCanonicalizing(['sharp', 'dull', 'constant', 'at home'])
        ->and(array_values(s1pTexts($series->card(CardKind::PhraseChooseBack, $scene, s1pTerm($scene, 'p1'), 2)?->payload['options'])))
        ->toEqualCanonicalizing(['У него болит плечо.', 'У него болит поясница.', 'У него болит шея.', 'Началось три дня назад.'])
        // A frame without a window has no filler of its own: all three wrong ones are what the next frame says.
        ->and(array_values(s1pTexts($series->card(CardKind::PhraseChooseBack, $scene, s1pTerm($scene, 'p4'), null)?->payload['options'])))
        ->toEqualCanonicalizing(['Температуры у него нет.', 'Он будет отдыхать дома.', 'Он будет отдыхать два дня.', 'Он будет отдыхать после школы.']);

    // A frame of two fillers: one of its own, two of the next frame.
    $two = s1pScene(static function (array $payload): array {
        array_pop($payload['phrases'][0]['slot']['fillers']);

        return $payload;
    });
    expect(array_values(s1pTexts($series->card(CardKind::PhraseSlot, $two, s1pTerm($two, 'p1'), 0)?->payload['options'])))
        ->toEqualCanonicalizing(['lower back', 'neck', 'three days ago', 'last night']);
});

// Canon (SESSION-1d, разд. 3): «beginner: phrase_repeat с наполнением, которого ученик ещё не говорил — не said, а следующее
// по кругу (у каркаса с одним наполнением — said); текст и звук — с этим наполнением». Catches a repeat of the said phrase,
// a sample that plays the said file, and a frame said twice repeated with its second said filler while an unsaid one is
// there.
it('has a beginner say the frame with a filler not said yet, its sample playing that filler — one filler or none: the phrase itself', function () {
    $scene = s1pScene();
    $drafts = s1pStage()->build($scene, PlanLevel::Beginner);
    $repeat = static fn (array $drafts, string $ref): array => array_values(array_filter(
        s1pOf($drafts, $ref), static fn (CardDraft $d): bool => $d->kind === CardKind::PhraseRepeat,
    ))[0]->payload;

    expect($repeat($drafts, 'p1'))->toBe([
        'scene_id' => s1pSceneId(),
        'frame' => CardObjects::frame(s1pTerm($scene, 'p1')),
        'filler_index' => 1,
        'expected_text' => 'It hurts in his neck.',
        'key' => s1pTerm($scene, 'p1')->speakingKey(),
        'coverage_min' => 0.7,
        'audio' => Audio::of('p1.f2'),
    ])
        // p6 is said with «an X-ray» and «a follow-up appointment»: the learner has not said «a sick note».
        ->and($repeat($drafts, 'p6')['filler_index'])->toBe(2)
        ->and($repeat($drafts, 'p6')['expected_text'])->toBe('Do we need a sick note?')
        ->and($repeat($drafts, 'p6')['audio'])->toBe(Audio::of('p6.f3'))
        ->and($repeat($drafts, 'p4')['filler_index'])->toBeNull()
        ->and($repeat($drafts, 'p4')['audio'])->toBe(Audio::of('p4'));

    $oneFiller = s1pScene(static function (array $payload): array {
        $payload['phrases'][4]['slot']['fillers'] = [$payload['phrases'][4]['slot']['fillers'][0]];

        return $payload;
    });
    expect($repeat(s1pStage()->build($oneFiller, PlanLevel::Beginner), 'p5'))->toMatchArray([
        'filler_index' => 0, 'expected_text' => 'He will rest at home.', 'audio' => Audio::of('p5'),
    ]);
});

// Canon (SESSION-1d, разд. 3): «intermediate: phrase_other_slot берёт наполнение, не занятое ни узнаваниями, ни said;
// свободных нет — любое, кроме said». Catches an other_slot that repeats a recognition's filler while a free one is there,
// and one that asks for the said filler.
it('has phrase_other_slot ask for a filler no recognition took, never the said one', function () {
    $scene = s1pScene();
    $cycle = [CardKind::PhraseOtherSlot, CardKind::PhraseOwnSlot];
    $others = static fn (array $drafts): array => array_map(
        static fn (CardDraft $d): array => [$d->unitRef, $d->payload['filler_index']],
        array_values(array_filter($drafts, static fn (CardDraft $d): bool => $d->kind === CardKind::PhraseOtherSlot)),
    );

    // Without thirds the recognitions take 0 and 1: the other window is 2.
    $noThirds = $others(s1pStage(s1pPace(30))->build($scene, PlanLevel::Intermediate));
    expect($noThirds)->not->toBe([]);
    foreach ($noThirds as [$ref, $index]) {
        expect($index)->toBe(2, $ref);
    }

    // With three recognitions every filler is taken: any but the said one, seeded.
    foreach ($others(s1pStage()->build($scene, PlanLevel::Intermediate)) as [$ref, $index]) {
        expect($index)->toBe(Rotation::pick($scene->seed("{$ref}:other"), 0, [1, 2]), $ref);
    }
    expect(PhraseSeries::otherFiller($scene, s1pTerm($scene, 'p1'), [0, 1]))->toBe(2)
        ->and(PhraseSeries::otherFiller($scene, s1pTerm($scene, 'p1'), [0, 2]))->toBe(1)
        ->and(PhraseSeries::otherFiller($scene, s1pTerm($scene, 'p4'), []))->toBeNull()
        ->and((new PhraseCards)->otherSlot($scene, s1pTerm($scene, 'p1'), 0))->toBeNull()
        // The rotation of the production itself is as before: other_slot → own_slot over the frames with fillers.
        ->and(array_values(array_unique(array_map(
            static fn (CardDraft $d): string => $d->kind->value,
            array_filter(s1pStage()->build($scene, PlanLevel::Intermediate), static fn (CardDraft $d): bool => in_array($d->kind, $cycle, true)),
        ))))->toEqualCanonicalizing(['phrase_other_slot', 'phrase_own_slot']);
});

// Canon (SESSION-1d, разд. 2): «между двумя карточками одного каркаса — минимум две карточки других каркасов; интро идут
// первыми в своей волне; разнесение детерминировано». Catches a frame's cards dealt in a row, intros out of the frames'
// order, and a stage that is not the same twice.
it('keeps at least two cards of other frames between any two cards of a frame, the intros in the frames\' order', function () {
    foreach ([s1pSceneId(), '01J8SESS10N1APHRASES000011', '01J8SESS10N1APHRASES000017'] as $id) {
        foreach ([[PlanLevel::Beginner, null], [PlanLevel::Intermediate, null], [PlanLevel::Intermediate, s1pPace(21)]] as [$level, $pace]) {
            $drafts = s1pStage($pace)->build(s1pScene(null, $id), $level);
            $intros = array_values(array_map(
                static fn (CardDraft $d): string => $d->unitRef,
                array_filter($drafts, static fn (CardDraft $d): bool => $d->kind === CardKind::PhraseIntro),
            ));

            expect(min(s1pGaps($drafts)))->toBeGreaterThanOrEqual(2, "{$id} {$level->value}")
                ->and($intros)->toBe(['p1', 'p2', 'p3', 'p4', 'p5', 'p6'])
                ->and(s1pStage($pace)->build(s1pScene(null, $id), $level))->toEqual($drafts);
        }
    }
});

it('builds phrase_combine on an answer exchange whose frame is said once, its two wrong frames from the farthest exchanges', function () {
    $scene = s1pScene();
    $drafts = s1pStage()->build($scene, PlanLevel::Intermediate);
    $card = end($drafts);
    $payload = $card->payload;
    $step = $payload['exchange']['step'];
    $exchange = $scene->exchange($step);
    $correct = s1pTerm($scene, $payload['correct_frame']);
    $eligible = array_values(array_filter($scene->lesson->exchanges, static fn (Exchange $e): bool => in_array($e->step, [1, 2, 3, 5], true)));
    // Canon (SESSION-1d, 5.2): the frames of the farthest exchanges by |Δstep|, the lower step between two as far.
    $far = [];
    foreach ($scene->farthestFrom($step) as $other) {
        $term = $scene->phraseTerm($other->learner()?->phraseId);
        if ($term !== null && PhraseCards::hasSlot($term) && $term->ref() !== $correct->ref() && ! in_array($term->ref(), $far, true)) {
            $far[] = $term->ref();
        }
    }

    expect(array_keys($payload))->toBe(['scene_id', 'exchange', 'partner_line', 'frames', 'correct_frame', 'chips', 'correct_filler'])
        ->and($card->kind)->toBe(CardKind::PhraseCombine)
        ->and($card->unitRef)->toBe($payload['correct_frame'])
        ->and($exchange?->kind)->toBe(ExchangeKind::Answer)
        ->and($step)->toBe(Rotation::pick($scene->seed('phrases:combine'), 0, $eligible)->step)
        ->and($payload['exchange'])->toBe(['ref' => 'x'.$step, 'step' => $step, 'kind' => 'answer'])
        ->and($exchange?->learner()?->phraseId)->toBe($payload['correct_frame'])
        ->and($scene->lesson->linesOf($payload['correct_frame']))->toHaveCount(1)
        ->and($payload['partner_line'])->toBe([
            'ref' => 'x'.$step, 'text_target' => $exchange?->partner()?->textTarget, 'text_native' => $exchange?->partner()?->textNative, 'audio' => Audio::of('x'.$step),
        ])
        ->and($payload['frames'])->toHaveCount(3)
        ->and(array_column($payload['frames'], 'ref'))->toEqualCanonicalizing([$correct->ref(), ...array_slice($far, 0, 2)])
        ->and($payload['frames'])->toBe(array_map(static function (PlanTerm $t): array {
            return ['ref' => $t->ref(), 'frame_target' => $t->frame()?->frameTarget, 'frame_native' => $t->frame()?->frameNative];
        }, Shuffle::seeded($scene->seed('phrases:combine:frames'), [$correct, ...array_map(static fn (string $r): PlanTerm => s1pTerm($scene, $r), array_slice($far, 0, 2))])))
        ->and($payload['chips'])->toBe(CardObjects::fillers($correct))
        ->and($payload['correct_filler'])->toBe(0);

    // p2 is answered in x2: the farthest exchanges are x8 and x7 (both p6), x6 (a rescue, no frame), x5 (p5) — never
    // the neighbours x1 and x3.
    $forP2 = (new PhraseCards)->combine($scene, s1pTerm($scene, 'p2'))?->payload;
    expect($forP2['exchange']['step'] ?? null)->toBe(2)
        ->and(array_column($forP2['frames'] ?? [], 'ref'))->toEqualCanonicalizing(['p2', 'p6', 'p5']);

    // Seeded by the scene; never x4 (no window), x6 (a rescue), x7/x8 (an ask frame said twice).
    $steps = [];
    foreach (range(10, 21) as $n) {
        $combine = (new PhraseCards)->combine(s1pScene(null, '01J8SESS10N1APHRASES0000'.$n));
        $steps[$combine?->payload['exchange']['step']] = true;
        expect($combine?->unitRef)->not->toBe('p6');
    }
    expect(array_diff(array_keys($steps), [1, 2, 3, 5]))->toBe([])
        ->and(count($steps))->toBeGreaterThan(1);
});

it('falls back to exchange 1 when no answer frame is said once, and deals no combine without another frame with a window', function () {
    // Every answer line now stands on p1 — said four times; x1 says it with its second filler.
    $saidOften = s1pScene(static function (array $payload): array {
        foreach ([0 => 'It hurts in his neck.', 1 => 'It hurts in his shoulder.', 2 => 'It hurts in his lower back.', 4 => 'It hurts in his neck.'] as $i => $text) {
            $payload['dialogue'][$i]['messages'][1]['phrase_id'] = 'p1';
            $payload['dialogue'][$i]['messages'][1]['text_target'] = $text;
        }

        return $payload;
    });
    $combine = (new PhraseCards)->combine($saidOften);

    expect($combine?->payload['exchange'])->toBe(['ref' => 'x1', 'step' => 1, 'kind' => 'answer'])
        ->and($combine?->payload['correct_frame'])->toBe('p1')
        ->and($combine?->payload['correct_filler'])->toBe(1)
        ->and($combine?->payload['chips'])->toBe(CardObjects::fillers(s1pTerm($saidOften, 'p1')));

    $alone = s1pScene(static function (array $payload): array {
        foreach ([1, 2, 4, 5] as $i) {
            $payload['phrases'][$i]['slot'] = null;
        }

        return $payload;
    });
    $drafts = s1pStage()->build($alone, PlanLevel::Beginner);

    expect((new PhraseCards)->combine($alone))->toBeNull()
        ->and(array_filter($drafts, static fn (CardDraft $d): bool => $d->kind === CardKind::PhraseCombine))->toBe([]);
});

it('recognises a frame without a window back and has it repeated, at both levels', function () {
    $scene = s1pScene();
    $p4 = s1pTerm($scene, 'p4');
    $cards = new PhraseCards;

    foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
        expect(array_map(static fn (CardDraft $d): string => $d->kind->value, s1pOf(s1pStage()->build($scene, $level), 'p4')))
            ->toBe(['phrase_intro', 'phrase_choose_back', 'phrase_repeat']);
    }

    $intro = $cards->intro($scene, $p4)->payload;
    $back = $cards->chooseBack($scene, $p4, null)?->payload;

    expect($intro['frame']['slot'])->toBeNull()
        ->and($intro['said'])->toBe([
            'filler_index' => null, 'text_target' => "He doesn't have a fever.", 'text_native' => 'Температуры у него нет.',
            'pronunciation_native' => 'хи дазнт хэв э фивер', 'audio' => Audio::of('p4'),
        ])
        ->and($back['prompt']['filler_index'])->toBeNull()
        ->and($back['prompt']['audio'])->toBe(Audio::of('p4'))
        ->and(s1pTexts($back['options'])[$back['correct']])->toBe('Температуры у него нет.')
        ->and($back['options'])->toHaveCount(4)
        ->and($cards->chooseBack($scene, $p4, 0))->toBeNull()
        ->and($cards->assemble($scene, $p4, null))->toBeNull()
        ->and($cards->slot($scene, $p4, null))->toBeNull()
        ->and($cards->slotListen($scene, $p4, null))->toBeNull()
        ->and($cards->otherSlot($scene, $p4, null))->toBeNull()
        ->and($cards->ownSlot($scene, $p4))->toBeNull()
        ->and(s1pStage()->returned($scene, $p4, CardKind::PhraseChooseBack, null)?->payload)->toEqual($back);
});

it('deals no phrase_choose_back with nothing to choose between: a lone frame without a window is met and repeated, and goes unrecognised', function () {
    $full = s1pScene();
    $p4 = s1pTerm($full, 'p4');
    $lone = new SceneMaterial($full->sceneId, $full->lesson, [$p4], $full->target, $full->native);

    foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
        expect(array_map(static fn (CardDraft $d): string => $d->kind->value.':'.$d->unitRef, s1pStage()->build($lone, $level)))
            ->toBe(['phrase_intro:p4', 'phrase_repeat:p4'], $level->value);
    }

    expect((new PhraseCards)->chooseBack($lone, $p4, null))->toBeNull()
        ->and(s1pStage()->returned($lone, $p4, CardKind::PhraseChooseBack, null))->toBeNull()
        ->and((new PhraseCards)->chooseBack($full, $p4, null)?->kind)->toBe(CardKind::PhraseChooseBack);
});

// Canon (SESSION-1d, разд. 4): «провалил карточку фразы → копия того же вида, с наполнением, следующим по кругу, которого ещё
// не было ни в одной карточке каркаса сегодня; свободных нет — любое, кроме проваленного». Catches a copy said with the
// failed filler again while another is there, a free filler passed over for a taken one, and a copy of another kind.
it('says the copy of a failed phrase card as the same kind with the next filler no card of the frame has taken', function () {
    $scene = s1pScene();
    $stage = s1pStage();
    $p1 = s1pTerm($scene, 'p1');
    $filler = static fn (?CardDraft $d): ?int => $d === null ? null : PhraseSeries::fillerOf($d->kind, $d->payload);

    // Failed on 0 while 0 and 1 are taken: 2 is free.
    expect($stage->again($scene, $p1, CardKind::PhraseSlot, 0, [0, 1])?->kind)->toBe(CardKind::PhraseSlot)
        ->and($filler($stage->again($scene, $p1, CardKind::PhraseSlot, 0, [0, 1])))->toBe(2)
        // Failed on 2 while 0 is taken: round after 2 comes 0 (taken), then 1 (free) — the free one.
        ->and($filler($stage->again($scene, $p1, CardKind::PhraseChooseBack, 2, [0, 2])))->toBe(1)
        // Every filler taken: the next round the slot after the failed one.
        ->and($filler($stage->again($scene, $p1, CardKind::PhraseSlotListen, 1, [0, 1, 2])))->toBe(2)
        ->and($filler($stage->again($scene, $p1, CardKind::PhraseAssemble, 2, [0, 1, 2])))->toBe(0)
        ->and($stage->again($scene, $p1, CardKind::PhraseAssemble, 2, [0, 1, 2])?->kind)->toBe(CardKind::PhraseAssemble)
        // Said aloud too: a repeat with the next filler, an other_slot never with the said one.
        ->and($filler($stage->again($scene, $p1, CardKind::PhraseRepeat, 1, [0, 1, 2])))->toBe(2)
        ->and($filler($stage->again($scene, $p1, CardKind::PhraseOtherSlot, 2, [0, 1, 2])))->toBe(1)
        // No filler of its own: nothing to vary — the copy is the card as it was.
        ->and($stage->again($scene, $p1, CardKind::PhraseCombine, 0, [0]))->toBeNull()
        ->and($stage->again($scene, $p1, CardKind::PhraseOwnSlot, null, []))->toBeNull()
        ->and($filler($stage->again($scene, s1pTerm($scene, 'p4'), CardKind::PhraseChooseBack, null, [])))->toBeNull();

    // A frame of one filler says its copy with that filler again.
    $oneFiller = s1pScene(static function (array $payload): array {
        $payload['phrases'][0]['slot']['fillers'] = [$payload['phrases'][0]['slot']['fillers'][0]];

        return $payload;
    });
    expect($filler(s1pStage()->again($oneFiller, s1pTerm($oneFiller, 'p1'), CardKind::PhraseSlot, 0, [0])))->toBe(0);
});

// Canon (SESSION-1d, разд. 4): «в день возврата единица «фраза» приходит видом, которым её провалили последний раз, снова с
// другим наполнением». Catches a frame always coming back as phrase_slot, a return said with the failed filler, and a
// production coming back as a recognition.
it('brings a frame back as the kind it failed as the last time, said with another filler', function () {
    $scene = s1pScene();
    $stage = s1pStage();
    $p1 = s1pTerm($scene, 'p1');
    $shape = static fn (?CardDraft $d): ?array => $d === null ? null : [$d->kind, PhraseSeries::fillerOf($d->kind, $d->payload)];

    expect($shape($stage->returned($scene, $p1, CardKind::PhraseSlotListen, 1)))->toBe([CardKind::PhraseSlotListen, 2])
        ->and($shape($stage->returned($scene, $p1, CardKind::PhraseChooseBack, 2)))->toBe([CardKind::PhraseChooseBack, 0])
        ->and($shape($stage->returned($scene, $p1, CardKind::PhraseAssemble, 0)))->toBe([CardKind::PhraseAssemble, 1])
        ->and($shape($stage->returned($scene, $p1, CardKind::PhraseRepeat, 1)))->toBe([CardKind::PhraseRepeat, 2])
        ->and($shape($stage->returned($scene, $p1, CardKind::PhraseOtherSlot, 2)))->toBe([CardKind::PhraseOtherSlot, 1])
        ->and($stage->returned($scene, $p1, CardKind::PhraseCombine, 0)?->payload['correct_frame'])->toBe('p1')
        ->and($stage->returned($scene, $p1, CardKind::PhraseCombine, 0)?->kind)->toBe(CardKind::PhraseCombine)
        // What it failed as is not known: its first recognition.
        ->and($shape($stage->returned($scene, $p1, null, null)))->toBe([PhraseSeries::kind($scene, $p1, 0), 0]);
});

it('lays out the frame, every filler with the file it sounds as, and the said phrase on phrase_intro', function () {
    $scene = s1pScene();
    $cards = new PhraseCards;

    expect($cards->intro($scene, s1pTerm($scene, 'p1'))->payload)->toBe([
        'scene_id' => s1pSceneId(),
        'frame' => [
            'ref' => 'p1', 'kind' => 'answer', 'frame_target' => 'It hurts in his ___.', 'frame_native' => 'У него болит ___.',
            'frame_pronunciation_native' => 'ит хёртс ин хиз ___',
            'slot' => ['hint_native' => 'где болит', 'fillers' => [
                ['index' => 0, 'target' => 'lower back', 'native' => 'поясница', 'pronunciation_native' => 'лоуэр бэк', 'in_dialogue' => true, 'native_line' => 'У него болит поясница.', 'audio' => Audio::of('p1')],
                ['index' => 1, 'target' => 'neck', 'native' => 'шея', 'pronunciation_native' => 'нэк', 'in_dialogue' => false, 'native_line' => 'У него болит шея.', 'audio' => Audio::of('p1.f2')],
                ['index' => 2, 'target' => 'shoulder', 'native' => 'плечо', 'pronunciation_native' => 'шоулдер', 'in_dialogue' => false, 'native_line' => 'У него болит плечо.', 'audio' => Audio::of('p1.f3')],
            ]],
        ],
        'said' => [
            'filler_index' => 0, 'text_target' => 'It hurts in his lower back.', 'text_native' => 'У него болит поясница.',
            'pronunciation_native' => 'ит хёртс ин хиз лоуэр бэк', 'audio' => Audio::of('p1'),
        ],
    ]);

    // p6 is said twice: both said fillers are marked, only the first is the phrase's own file.
    $p6 = $cards->intro($scene, s1pTerm($scene, 'p6'))->payload;
    $fillers = $p6['frame']['slot']['fillers'];
    expect(array_column(array_column($fillers, 'audio'), 'ref'))->toBe(['p6', 'p6.f2', 'p6.f3'])
        ->and(array_column($fillers, 'in_dialogue'))->toBe([true, true, false])
        ->and(array_column($fillers, 'native_line'))->toBe(['Нам нужно сделать рентген?', 'Нам нужно прийти на повторный приём?', 'Нам нужно взять справку для школы?'])
        ->and($p6['said']['text_target'])->toBe('Do we need an X-ray?')
        ->and($p6['frame']['kind'])->toBe('ask')
        ->and(CardObjects::fillers(s1pTerm($scene, 'p3'))[1]['native_line'])->toBe('Боль ноющая, когда он наклоняется.');
});

it('asks phrase_assemble for the frame\'s words and two words of the frames after it, lower-cased but «I»', function () {
    $scene = s1pScene();
    $cards = new PhraseCards;
    $p3 = $cards->assemble($scene, s1pTerm($scene, 'p3'), 0)?->payload;

    expect(array_keys($p3))->toBe(['scene_id', 'frame', 'target_native', 'tiles', 'chips', 'expected'])
        ->and($p3['frame'])->toBe(CardObjects::frame(s1pTerm($scene, 'p3')))
        ->and($p3['target_native'])->toBe('Боль острая, когда он наклоняется.')
        // The next frame p4 gives two words p3 does not have («he» it has).
        ->and($p3['tiles'])->toBe(Shuffle::seeded($scene->seed('p3:assemble'), ['the', 'pain', 'is', 'when', 'he', 'bends', "doesn't", 'have']))
        ->and($p3['chips'])->toBe(CardObjects::fillers(s1pTerm($scene, 'p3')))
        // The answer is spelled the way the tiles are: the client matches what it assembled tile by tile.
        ->and($p3['expected'])->toBe(['words' => ['the', 'pain', 'is', 'when', 'he', 'bends'], 'slot_at' => 3, 'filler_index' => 0]);

    // p2 «It started ___.» has one word p1 lacks; the second comes from the frame after it.
    expect($cards->assemble($scene, s1pTerm($scene, 'p1'), 0)?->payload['tiles'])
        ->toBe(Shuffle::seeded($scene->seed('p1:assemble'), ['it', 'hurts', 'in', 'his', 'started', 'the']));

    $mine = s1pScene(static function (array $payload): array {
        $payload['phrases'][4]['frame_target'] = 'I will rest ___.';
        $payload['dialogue'][4]['messages'][1]['text_target'] = 'Okay, I will rest at home.';

        return $payload;
    });
    $p5 = $cards->assemble($mine, s1pTerm($mine, 'p5'), 1)?->payload;
    expect($p5['tiles'])->toEqualCanonicalizing(['I', 'will', 'rest', 'do', 'we'])
        ->and($p5['expected'])->toBe(['words' => ['I', 'will', 'rest'], 'slot_at' => 3, 'filler_index' => 1])
        ->and($p5['target_native'])->toBe('Он будет отдыхать два дня.');
});

it('asks for a filler other than the said one on phrase_other_slot, by its translation', function () {
    $scene = s1pScene();

    expect((new PhraseCards)->otherSlot($scene, s1pTerm($scene, 'p1'), 2)?->payload)->toBe([
        'scene_id' => s1pSceneId(),
        'frame' => CardObjects::frame(s1pTerm($scene, 'p1')),
        'filler_index' => 2,
        'task_native' => 'плечо',
        'expected_text' => 'It hurts in his shoulder.',
        'slot_expected' => 'shoulder',
        'key' => s1pTerm($scene, 'p1')->speakingKey(),
        'coverage_min' => 0.7,
    ]);
});

it('has the learner\'s own value judged on phrase_own_slot, with the partner line the frame is said to', function () {
    $scene = s1pScene();
    $cards = new PhraseCards;
    $p1 = $cards->ownSlot($scene, s1pTerm($scene, 'p1'))?->payload;

    expect($p1)->toBe([
        'scene_id' => s1pSceneId(),
        'frame' => CardObjects::frame(s1pTerm($scene, 'p1')),
        'partner_line' => [
            'ref' => 'x1', 'text_target' => 'Where does it hurt: his upper back or his lower back?',
            'text_native' => 'Где болит: вверху спины или в пояснице?', 'audio' => Audio::of('x1'),
        ],
        'task_native' => 'У него болит ___.',
        'key' => s1pTerm($scene, 'p1')->speakingKey(),
        'coverage_min' => 0.7,
        'examples' => ['поясница', 'шея', 'плечо'],
        'chips' => CardObjects::fillers(s1pTerm($scene, 'p1')),
        'judge' => true,
    ])
        // «It started» — two words: all of them.
        ->and($cards->ownSlot($scene, s1pTerm($scene, 'p2'))?->payload['coverage_min'])->toBe(1.0)
        // p6 is first asked in x7: the learner speaks first, the line before it is the partner's line of x6.
        ->and($cards->ownSlot($scene, s1pTerm($scene, 'p6'))?->payload['partner_line'])->toBe([
            'ref' => 'x6', 'text_target' => 'He should rest and use a heating pad.', 'text_native' => 'Ему нужен покой и грелка.', 'audio' => Audio::of('x6'),
        ]);
});

it('gives every one of the nine kinds its exact keys over the deals of both levels', function () {
    $keys = [
        'phrase_intro' => ['scene_id', 'frame', 'said'],
        'phrase_assemble' => ['scene_id', 'frame', 'target_native', 'tiles', 'chips', 'expected'],
        'phrase_choose_back' => ['scene_id', 'prompt', 'options', 'correct'],
        'phrase_slot' => ['scene_id', 'frame', 'prompt_native', 'options', 'correct'],
        'phrase_slot_listen' => ['scene_id', 'frame', 'filler_index', 'audio', 'options', 'correct'],
        'phrase_repeat' => ['scene_id', 'frame', 'filler_index', 'expected_text', 'key', 'coverage_min', 'audio'],
        'phrase_other_slot' => ['scene_id', 'frame', 'filler_index', 'task_native', 'expected_text', 'slot_expected', 'key', 'coverage_min'],
        'phrase_combine' => ['scene_id', 'exchange', 'partner_line', 'frames', 'correct_frame', 'chips', 'correct_filler'],
        'phrase_own_slot' => ['scene_id', 'frame', 'partner_line', 'task_native', 'key', 'coverage_min', 'examples', 'chips', 'judge'],
    ];
    $seen = [];
    foreach ([s1pSceneId(), '01J8SESS10N1APHRASES000011'] as $id) {
        $scene = s1pScene(null, $id);
        foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
            foreach (s1pStage()->build($scene, $level) as $draft) {
                $seen[$draft->kind->value] = true;
                expect(array_keys($draft->payload))->toBe($keys[$draft->kind->value]);
                if (isset($draft->payload['frame'])) {
                    expect(array_keys($draft->payload['frame']))->toBe(['ref', 'kind', 'frame_target', 'frame_native', 'frame_pronunciation_native', 'slot']);
                }
                foreach ($draft->payload['options'] ?? [] as $option) {
                    expect(array_slice(array_keys($option), 0, 2))->toBe(['id', 'text']);
                }
                if (isset($draft->payload['correct'])) {
                    expect(array_column($draft->payload['options'], 'id'))->toContain($draft->payload['correct'])
                        ->and(array_unique(array_map('mb_strtolower', array_column($draft->payload['options'], 'text'))))->toHaveCount(count($draft->payload['options']));
                }
            }
        }
    }

    expect(array_keys($seen))->toEqualCanonicalizing(array_keys($keys))
        ->and(array_keys(CardObjects::fillers(s1pTerm(s1pScene(), 'p2'))[0]))->toBe(['index', 'target', 'native', 'pronunciation_native', 'in_dialogue', 'native_line', 'audio']);
});
