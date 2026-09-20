<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Assembly\Audio;
use App\Modules\Plan\Domain\Assembly\CardDraft;
use App\Modules\Plan\Domain\Assembly\CardObjects;
use App\Modules\Plan\Domain\Assembly\PhraseCards;
use App\Modules\Plan\Domain\Assembly\PhraseSeries;
use App\Modules\Plan\Domain\Assembly\PhrasesStage;
use App\Modules\Plan\Domain\Assembly\Rotation;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
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
    return FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Intermediate, null, 8, 8, FakePlanModel::roles(), new EarlierDays));
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

function s1pStage(?DayPace $pace = null, int $budget = PhrasesStage::BUDGET): PhrasesStage
{
    return new PhrasesStage(new PhraseCards, $pace ?? new DayPace, $budget);
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
        // At a ceiling nothing fits in, the ladder has spent every rung and what is left is the FLOOR — which is the
        // shape asserted below and is the same at both levels: no third recognition, no own-word round, the trainer
        // still there. Cutting the trainer away, or a frame's last recognition, would break these counts.
        $drafts = s1pStage(null, 0)->build($scene, $level);
        $produce = [CardKind::PhraseRepeat, CardKind::PhraseOtherSlot];

        // Five frames with a window × (intro, two recognitions, production) + p4 (intro, choose_back, repeat) + combine.
        expect($drafts)->toHaveCount(24, $level->value)
            ->and(end($drafts)->kind)->toBe(CardKind::PhraseCombine)
            ->and(array_filter($drafts, static fn (CardDraft $d): bool => $d->kind === CardKind::PhraseCombine))->toHaveCount(1)
            ->and(array_map(static fn (CardDraft $d): string => $d->kind->value, s1pOf($drafts, 'p4')))
            ->toBe(['phrase_intro', 'phrase_choose_back', 'phrase_repeat']);

        foreach (['p1', 'p2', 'p3', 'p5', 'p6'] as $ref) {
            $cards = s1pOf($drafts, $ref);
            expect($cards)->toHaveCount(4, "{$level->value} {$ref}")
                ->and($cards[0]->kind)->toBe(CardKind::PhraseIntro)
                ->and(array_map(static fn (CardDraft $d): bool => in_array($d->kind, PhraseSeries::CYCLE, true), array_slice($cards, 1, 2)))->toBe([true, true])
                ->and(in_array($cards[3]->kind, $produce, true))->toBeTrue("{$level->value} {$ref}");
        }
        foreach ($drafts as $draft) {
            expect($draft->kind->stage())->toBe(Stage::Phrases)
                ->and($draft->unitKind)->toBe(UnitKind::Phrase)
                ->and(array_key_first($draft->payload))->toBe('scene_id')
                ->and($draft->payload['scene_id'])->toBe(s1pSceneId());
        }
    }
});

/**
 * What the ladder left of every frame with a window: how many recognitions, how many value rounds, and whether the
 * learner's own word is still on the card.
 *
 * @param  list<CardDraft>  $drafts
 * @return array<string, string>
 */
function s1pShape(array $drafts): array
{
    $out = [];
    foreach (['p1', 'p2', 'p3', 'p5', 'p6'] as $ref) {
        $whole = array_values(array_filter(s1pOf($drafts, $ref), static fn (CardDraft $d): bool => $d->kind === CardKind::PhraseOtherSlot));
        expect($whole)->toHaveCount(1, "{$ref} keeps its trainer");
        $rounds = count($whole[0]->payload['rounds']);
        $out[$ref] = sprintf(
            '%d узнавания · %d %s%s',
            count(s1pRecognitions($drafts, $ref)),
            $rounds,
            $rounds === 1 ? 'круг' : 'круга',
            $whole[0]->payload['own_round'] === null ? '' : ' + своё',
        );
    }

    return $out;
}

// Canon (решение архитектора 20.09, доработка наряда FIX-2): «потолок этапа «Фразы» — 690 с». CATCHES the stop condition
// the live pass of 20.09 hit — «Фразы» 663 с при потолке 600 — coming back: a ceiling the clean beginner day cannot meet,
// and a ladder that cuts a day that already fits (the beginner keeps both its value rounds and its own word here).
it('builds the beginner «врач» day inside the 690-second ceiling, with nothing cut', function () {
    $stage = s1pStage();
    $drafts = $stage->build(s1pScene(), PlanLevel::Beginner);

    expect($stage->seconds($drafts))->toBeLessThanOrEqual(PhrasesStage::BUDGET)
        ->and(s1pShape($drafts))->toBe([
            'p1' => '3 узнавания · 2 круга + своё', 'p2' => '2 узнавания · 2 круга + своё', 'p3' => '2 узнавания · 2 круга + своё',
            'p5' => '2 узнавания · 2 круга + своё', 'p6' => '3 узнавания · 2 круга + своё',
        ]);
});

// Canon (решение архитектора 20.09, уточнено при приёмке): «порядок урезания — детерминированный и единственный:
// (1) третье узнавание каркаса, (2) третий круг «Скажи целиком», (3) ВТОРОЙ круг значений — у каркасов с наименьшим
// числом реплик в диалоге (при равенстве — позже по визиту). Круг «со своим словом» лестница не снимает НИКОГДА;
// нижняя граница — «2 узнавания + 1 круг + своё».»
// CATCHES: ступень не по порядку — второй круг, снятый пока где-то стоит третий, третье узнавание, оставшееся пока
// уходят круги; урезание, которое не останавливается на попадании под потолок; каркас ниже нижней границы; и — то,
// ради чего граница и названа — своё слово, снятое лестницей, или тренажёр, снятый целиком.
it('cuts «Фразы» in exactly one order under a lower ceiling and stops at the floor — the own word survives it', function () {
    $scene = s1pScene();
    $shape = static fn (int $budget): array => s1pShape(s1pStage(null, $budget)->build($scene, PlanLevel::Intermediate));

    // Nothing to cut: every frame with three fillers has its third recognition, its three value rounds and its own word.
    expect($shape(9999))->toBe([
        'p1' => '3 узнавания · 3 круга + своё', 'p2' => '3 узнавания · 3 круга + своё', 'p3' => '3 узнавания · 3 круга + своё',
        'p5' => '3 узнавания · 3 круга + своё', 'p6' => '3 узнавания · 3 круга + своё',
    ]);

    // RUNG 1, then RUNG 2 — at the day's own ceiling no third recognition fits, and the third round goes off the frames
    // the dialogue says least: p3 and p5 (one line each, latest in the visit), then p2. p1 and p6 keep theirs — the
    // stage fits before their turn comes, which is what «останавливается» means.
    expect($shape(PhrasesStage::BUDGET))->toBe([
        'p1' => '2 узнавания · 3 круга + своё', 'p2' => '2 узнавания · 2 круга + своё', 'p3' => '2 узнавания · 2 круга + своё',
        'p5' => '2 узнавания · 2 круга + своё', 'p6' => '2 узнавания · 3 круга + своё',
    ]);

    // RUNG 3 — at 600 every third round is gone and the SECOND round starts going, again off the least said first:
    // p3, p5. Not one second round goes while a third is still standing, and not one own word goes at all.
    expect($shape(600))->toBe([
        'p1' => '2 узнавания · 2 круга + своё', 'p2' => '2 узнавания · 2 круга + своё', 'p3' => '2 узнавания · 1 круг + своё',
        'p5' => '2 узнавания · 1 круг + своё', 'p6' => '2 узнавания · 2 круга + своё',
    ]);

    // THE FLOOR — a ceiling nothing fits in spends every rung and stops there: two recognitions, ONE value round and
    // the learner's own word on every frame with a window. The stage is dealt over the ceiling rather than broken.
    $floor = s1pStage(null, 0);
    $drafts = $floor->build($scene, PlanLevel::Intermediate);
    expect(array_unique(array_values(s1pShape($drafts))))->toBe(['2 узнавания · 1 круг + своё'])
        ->and(array_filter($drafts, static fn (CardDraft $d): bool => $d->kind === CardKind::PhraseOtherSlot))->toHaveCount(5)
        // Over the ceiling it was given, and dealt all the same: the excess is a signal, not a refusal to build.
        ->and($floor->seconds($drafts))->toBeGreaterThan(0);
});

// Canon (SESSION-1d, решение архитектора 16.09): «два узнавания КАЖДОМУ каркасу с окном», своё наполнение каждому. Catches
// a frame given one recognition, or more than its fillers, and a frame without a window given more than one.
it('gives every frame with a window two recognitions, one per filler — a frame of one filler or none just one', function () {
    // A ceiling no third recognition fits in: the ladder starts cutting before any is added.
    $noThirds = 0;
    $oneFiller = s1pScene(static function (array $payload): array {
        $payload['phrases'][4]['slot']['fillers'] = [$payload['phrases'][4]['slot']['fillers'][0]];

        return $payload;
    });

    foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
        $drafts = s1pStage(null, $noThirds)->build($oneFiller, $level);
        $counts = [];
        foreach (['p1', 'p2', 'p3', 'p4', 'p5', 'p6'] as $ref) {
            $counts[$ref] = count(s1pRecognitions($drafts, $ref));
        }

        expect($counts)->toBe(['p1' => 2, 'p2' => 2, 'p3' => 2, 'p4' => 1, 'p5' => 1, 'p6' => 2], $level->value)
            ->and($drafts)->toHaveCount(6 + 10 + 6 + 1);
    }
});

// Canon (SESSION-1d; потолок — решение архитектора 20.09): «третье — каркасам с наибольшим числом реплик (при равенстве —
// раньше в визите), пока этап влезает в потолок по DayPace». Catches a third given by the frame's order instead of how
// often it is said, a third past the ceiling, a ceiling read as «<» (the stage that fits exactly), and a third to a frame
// that has only two fillers to say.
it('adds a third recognition to the most said frames first, while the stage still fits in its ceiling by the day\'s pace', function () {
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

    // At 20 s a card the intermediate stage is 780 s without thirds (24 cards, «Скажи целиком» priced per round:
    // three values and the own word). A ceiling of 800 takes ONE third — and it goes to p6, the frame the dialogue
    // says twice, though it stands last in the visit. 840 takes exactly three: p6, then p1 and p2, said once each and
    // earliest in the visit; a fourth would make 860.
    $flat = s1pPace(20);
    $one = s1pStage($flat, 800)->build($scene, PlanLevel::Intermediate);
    $three = s1pStage($flat, 840)->build($scene, PlanLevel::Intermediate);
    // At the day's own pace and its own ceiling the intermediate stage has no room for a third: it gives up round
    // three instead (rung 2 of the ladder), which is the order the ceiling decision names.
    $all = s1pStage()->build($scene, PlanLevel::Intermediate);

    expect($thirds($one))->toBe(['p6'])
        ->and(s1pStage($flat, 800)->seconds($one))->toBe(800)
        ->and($thirds($three))->toBe(['p1', 'p2', 'p6'])
        ->and(s1pStage($flat, 840)->seconds($three))->toBe(840)
        ->and($thirds($all))->toBe([])
        // A frame of two fillers has nothing to say a third recognition with, however often the dialogue says it.
        ->and($thirds(s1pStage(s1pPace(1), 9999)->build(s1pScene(static function (array $payload): array {
            array_pop($payload['phrases'][5]['slot']['fillers']);

            return $payload;
        }), PlanLevel::Intermediate)))->toBe(['p1', 'p2', 'p3', 'p5']);
});

// Canon (SESSION-1d): «каждое узнавание берёт СВОЁ наполнение, ни одно не повторяется; первым — сказанное (said), дальше
// остальные по индексу». Catches a series that says the dialogue's filler again, or starts from index 0 whatever is said.
it('says every recognition with its own filler — the said one first, the rest by index — never one twice', function () {
    $drafts = s1pStage(s1pPace(20), 840)->build(s1pScene(), PlanLevel::Intermediate);
    foreach (['p1', 'p2', 'p6'] as $ref) {
        expect(s1pFillers(s1pRecognitions($drafts, $ref)))->toBe([0, 1, 2], $ref);
    }

    // x1 says p1 with «neck»: its recognitions go neck → lower back → shoulder.
    $saysNeck = s1pScene(static function (array $payload): array {
        $payload['dialogue'][0]['messages'][1]['text_target'] = 'It hurts in his neck.';

        return $payload;
    });
    $neck = s1pStage(s1pPace(20), 840)->build($saysNeck, PlanLevel::Beginner);
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
    // A pace that lets every third recognition in, so the cycle is read over three cards and not two.
    $drafts = s1pStage(s1pPace(1))->build($scene, PlanLevel::Intermediate);
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

    $slot = $series->card(CardKind::PhraseSlot, $scene, $p1, 1, PlanLevel::Intermediate)?->payload;
    $back = $series->card(CardKind::PhraseChooseBack, $scene, $p1, 1, PlanLevel::Intermediate)?->payload;
    $listen = $series->card(CardKind::PhraseSlotListen, $scene, $p1, 2, PlanLevel::Intermediate)?->payload;
    $assemble = $series->card(CardKind::PhraseAssemble, $scene, $p1, 2, PlanLevel::Intermediate)?->payload;
    $said = $series->card(CardKind::PhraseSlotListen, $scene, $p1, 0, PlanLevel::Intermediate)?->payload;

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

// Canon (SESSION-1d): a card built FOR THE WINDOW offers the frame's own other fillers first and tops up with the next
// frames'. Catches wrong options taken from the next frame before the frame's own, and a top-up that is not another
// frame's filler.
it('offers the frame\'s other fillers first and tops up with the fillers of the next frames', function () {
    $scene = s1pScene();
    $series = new PhraseSeries;
    $level = PlanLevel::Intermediate;

    expect(array_values(s1pTexts($series->card(CardKind::PhraseSlot, $scene, s1pTerm($scene, 'p1'), 1, $level)?->payload['options'])))
        ->toEqualCanonicalizing(['neck', 'lower back', 'shoulder', 'three days ago'])
        ->and(array_values(s1pTexts($series->card(CardKind::PhraseSlotListen, $scene, s1pTerm($scene, 'p3'), 0, $level)?->payload['options'])))
        // p3's next frame with a window is p5 (p4 has none).
        ->toEqualCanonicalizing(['sharp', 'dull', 'constant', 'at home']);

    // A frame of two fillers: one of its own, two of the next frame.
    $two = s1pScene(static function (array $payload): array {
        array_pop($payload['phrases'][0]['slot']['fillers']);

        return $payload;
    });
    expect(array_values(s1pTexts($series->card(CardKind::PhraseSlot, $two, s1pTerm($two, 'p1'), 0, $level)?->payload['options'])))
        ->toEqualCanonicalizing(['lower back', 'neck', 'three days ago', 'last night']);
});

// Canon (наряд FIX-2, п. 1): «варианты — целые text_native реплик; ложные — из ДРУГИХ каркасов, никогда наполнения того
// же каркаса; кандидат с ≥ 50 % общих слов — в отбой». Catches the set that marked a right answer wrong on the owner's
// phone: «Что мне нужно принести на приём?» beside «…на визит?» is one sentence offered twice, and the window is what
// `phrase_slot` is for.
it('never offers two values of one window as two meanings, and drops a neighbour that reads like the right one', function () {
    $scene = s1pScene();
    $series = new PhraseSeries;
    $level = PlanLevel::Intermediate;

    $p1 = s1pTexts($series->card(CardKind::PhraseChooseBack, $scene, s1pTerm($scene, 'p1'), 2, $level)?->payload['options']);
    expect(array_values($p1))->toEqualCanonicalizing(['У него болит плечо.', 'Началось три дня назад.', 'Боль острая, когда он наклоняется.', 'Температуры у него нет.'])
        // Not one of p1's own other windows — they say the same sentence about another body part.
        ->and(array_values($p1))->not->toContain('У него болит поясница.')
        ->and(array_values($p1))->not->toContain('У него болит шея.')
        // A frame without a window is said as itself, and its options are the other frames' too.
        ->and(array_values(s1pTexts($series->card(CardKind::PhraseChooseBack, $scene, s1pTerm($scene, 'p4'), null, $level)?->payload['options'])))
        ->toEqualCanonicalizing(['Температуры у него нет.', 'Он будет отдыхать дома.', 'Нам нужно сделать рентген?', 'У него болит поясница.']);

    // The live pair, put into the day: the frame that comes next says almost the same sentence, so it is passed over
    // and the one after it takes its place. Without the rule the card would offer «на приём» and «на визит» together
    // and mark the right answer wrong, which is what the owner saw.
    $alike = s1pScene(static function (array $payload): array {
        $payload['phrases'][1]['frame_native'] = 'Что мне нужно принести на ___?';
        $payload['phrases'][1]['slot']['fillers'][1]['native'] = 'приём';
        $payload['dialogue'][2]['messages'][1]['text_native'] = 'Что мне нужно принести на визит?';

        return $payload;
    });
    $options = array_values(s1pTexts($series->card(CardKind::PhraseChooseBack, $alike, s1pTerm($alike, 'p2'), 1, $level)?->payload['options']));
    expect(CardObjects::said($alike, s1pTerm($alike, 'p3'))['text_native'])->toBe('Что мне нужно принести на визит?')
        ->and($options)->toContain('Что мне нужно принести на приём?')
        ->and($options)->not->toContain('Что мне нужно принести на визит?');
});

// Canon (наряд FIX-2, п. 5): «Скажи целиком» — один тренажёр для всех уровней, круги по значениям каркаса, последний
// круг «со своим словом»; разница уровней — только в числе кругов (beginner: 2 значения + своё; intermediate — все
// видимые). Catches a beginner left with nothing but choices and a repeat (проход 20.09, п. 5), a level given another
// TRAINER instead of another number of rounds, and a card dealt with no own-word round at the end.
it('says every frame with a window as «Скажи целиком» at both levels — the values in rounds, the learner\'s own last', function () {
    $scene = s1pScene();
    $whole = static fn (array $drafts, string $ref): array => array_values(array_filter(
        s1pOf($drafts, $ref), static fn (CardDraft $d): bool => $d->kind === CardKind::PhraseOtherSlot,
    ))[0]->payload;

    // A ceiling the stage fits in whole: what the LEVEL deals is the question here, and the ladder's cuts have a
    // canon test of their own below.
    $beginner = s1pStage(null, 9999)->build($scene, PlanLevel::Beginner);
    $intermediate = s1pStage(null, 9999)->build($scene, PlanLevel::Intermediate);

    foreach (['p1', 'p2', 'p3', 'p5', 'p6'] as $ref) {
        expect(array_column($whole($beginner, $ref)['rounds'], 'filler_index'))->toBe([0, 1], $ref)
            ->and(array_column($whole($intermediate, $ref)['rounds'], 'filler_index'))->toBe([0, 1, 2], $ref)
            ->and($whole($beginner, $ref)['own_round']['judge'])->toBeTrue($ref)
            ->and($whole($beginner, $ref)['speech_mode'])->toBe('repeat', $ref)
            ->and($whole($beginner, $ref)['own_round']['speech_mode'])->toBe('free', $ref);
    }

    expect($whole($beginner, 'p1')['rounds'])->toBe([
        ['filler_index' => 0, 'expected_text' => 'It hurts in his lower back.', 'task_native' => 'У него болит поясница.'],
        ['filler_index' => 1, 'expected_text' => 'It hurts in his neck.', 'task_native' => 'У него болит шея.'],
    ])
        ->and($whole($beginner, 'p1')['own_round'])->toBe([
            'task_native' => 'У него болит ___.',
            'examples' => ['поясница', 'шея', 'плечо'],
            'speech_mode' => 'free',
            'judge' => true,
        ])
        // The frame is said next to the line it is said in — read by the JUDGE, not shown (кадр 32-7 has no partner line).
        ->and($whole($beginner, 'p1')['partner_line'])->toBe([
            'ref' => 'x1', 'text_target' => 'Where does it hurt: his upper back or his lower back?',
            'text_native' => 'Где болит: вверху спины или в пояснице?', 'audio' => Audio::of('x1'),
        ]);

    // A frame of ONE value says it once and then the learner's own — the level cannot cut it below that.
    $oneFiller = s1pScene(static function (array $payload): array {
        $payload['phrases'][4]['slot']['fillers'] = [$payload['phrases'][4]['slot']['fillers'][0]];

        return $payload;
    });
    expect(array_column($whole(s1pStage()->build($oneFiller, PlanLevel::Beginner), 'p5')['rounds'], 'filler_index'))->toBe([0])
        ->and(array_column($whole(s1pStage()->build($oneFiller, PlanLevel::Intermediate), 'p5')['rounds'], 'filler_index'))->toBe([0]);
});

// Canon (наряд FIX-2, п. 5): a frame WITHOUT a window has no value to put anywhere — it is repeated, at both levels.
// Catches «Скажи целиком» dealt to a frame with nothing to say it with, and a repeat that still carries a share.
it('repeats a frame without a window instead, with the mode of a line on the screen', function () {
    $scene = s1pScene();
    $repeat = static fn (array $drafts, string $ref): array => array_values(array_filter(
        s1pOf($drafts, $ref), static fn (CardDraft $d): bool => $d->kind === CardKind::PhraseRepeat,
    ))[0]->payload;

    foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
        $drafts = s1pStage()->build($scene, $level);
        expect($repeat($drafts, 'p4'))->toBe([
            'scene_id' => s1pSceneId(),
            'frame' => CardObjects::frame($scene, s1pTerm($scene, 'p4')),
            'filler_index' => null,
            'expected_text' => "He doesn't have a fever.",
            'key' => s1pTerm($scene, 'p4')->speakingKey(),
            'speech_mode' => 'repeat',
            'audio' => Audio::of('p4'),
        ], $level->value)
            ->and(array_filter($drafts, static fn (CardDraft $d): bool => $d->kind === CardKind::PhraseRepeat))->toHaveCount(1, $level->value);
    }
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

// Canon (SESSION-1e, разд. 3): «phrase_combine — только на вопрос собеседника: answer-обмен, чья реплика A кончается вопросом
// (sentence_ends пакета цели); кандидаты — все такие обмены с каркасом с окном; выбор — тот, чей каркас в дне единственный на
// своём обмене, иначе первый по визиту»; ложные — каркасы самых далёких обменов (SESSION-1d). Catches a combine on a
// statement, a choice seeded among the once-said frames instead of the first in the visit, a frame said twice taken over one
// said once, and wrong frames that are not the farthest exchanges'.
it('builds phrase_combine on the first answer whose partner asks and whose frame is said there only, its wrong frames from the farthest exchanges', function () {
    $scene = s1pScene();
    $drafts = s1pStage()->build($scene, PlanLevel::Intermediate);
    $card = end($drafts);
    $payload = $card->payload;
    $exchange = $scene->exchange(1);
    $correct = s1pTerm($scene, 'p1');

    // x1…x4 ask, x5 does not; p1 is said once, in x1 — the first such exchange of the visit.
    expect(array_keys($payload))->toBe(['scene_id', 'exchange', 'partner_line', 'frames', 'correct_frame', 'chips', 'correct_filler'])
        ->and($card->kind)->toBe(CardKind::PhraseCombine)
        ->and($card->unitRef)->toBe('p1')
        ->and($payload['exchange'])->toBe(['ref' => 'x1', 'step' => 1, 'kind' => 'answer'])
        ->and($scene->asks((string) $exchange?->partner()?->textTarget))->toBeTrue()
        ->and($scene->asks((string) $scene->exchange(5)?->partner()?->textTarget))->toBeFalse()
        ->and($payload['partner_line'])->toBe([
            'ref' => 'x1', 'text_target' => $exchange?->partner()?->textTarget, 'text_native' => $exchange?->partner()?->textNative, 'audio' => Audio::of('x1'),
        ])
        ->and($payload['correct_frame'])->toBe('p1')
        // x1's farthest exchanges: x8 and x7 (both p6), x6 (a rescue, no frame), x5 (p5).
        ->and(array_column($payload['frames'], 'ref'))->toBe(array_map(
            static fn (PlanTerm $t): string => $t->ref(),
            Shuffle::seeded($scene->seed('phrases:combine:frames'), [$correct, s1pTerm($scene, 'p6'), s1pTerm($scene, 'p5')]),
        ))
        ->and($payload['chips'])->toBe(CardObjects::fillers($scene, $correct))
        ->and($payload['correct_filler'])->toBe(0);

    // The same on every scene: the choice is the visit's, not a seed's.
    foreach (range(10, 21) as $n) {
        expect((new PhraseCards)->combine(s1pScene(null, '01J8SESS10N1APHRASES0000'.$n))?->payload['exchange']['step'])->toBe(1, (string) $n);
    }

    // p1 said twice (x1 and x3): the first asking exchange whose frame is said there only is x2.
    $twice = s1pScene(static function (array $payload): array {
        $payload['dialogue'][2]['messages'][1]['phrase_id'] = 'p1';
        $payload['dialogue'][2]['messages'][1]['text_target'] = 'It hurts in his neck.';

        return $payload;
    });
    $forP2 = (new PhraseCards)->combine($twice)?->payload;
    expect($forP2['exchange']['step'] ?? null)->toBe(2)
        ->and($forP2['correct_frame'] ?? null)->toBe('p2')
        // p2 is answered in x2: the farthest exchanges are x8 and x7 (both p6), x6 (a rescue), x5 (p5) — never x1 and x3.
        ->and(array_column($forP2['frames'] ?? [], 'ref'))->toEqualCanonicalizing(['p2', 'p6', 'p5']);

    // Every asking exchange's frame said more than once: the first asking exchange of the visit, its own filler.
    $saidOften = s1pScene(static function (array $payload): array {
        foreach ([0 => 'It hurts in his neck.', 1 => 'It hurts in his shoulder.', 2 => 'It hurts in his lower back.', 4 => 'It hurts in his neck.'] as $i => $text) {
            $payload['dialogue'][$i]['messages'][1]['phrase_id'] = 'p1';
            $payload['dialogue'][$i]['messages'][1]['text_target'] = $text;
        }

        return $payload;
    });
    $often = (new PhraseCards)->combine($saidOften);
    expect($often?->payload['exchange'])->toBe(['ref' => 'x1', 'step' => 1, 'kind' => 'answer'])
        ->and($often?->payload['correct_frame'])->toBe('p1')
        ->and($often?->payload['correct_filler'])->toBe(1)
        ->and($often?->payload['chips'])->toBe(CardObjects::fillers($saidOften, s1pTerm($saidOften, 'p1')));
});

// Canon (SESSION-1e, разд. 3): «ни одного обмена на вопрос — карточки нет (и в возврате тоже)». Catches a combine dealt on a
// statement when nothing asks, a return built on a statement, and a target without `sentence_ends` read as asking.
it('deals no phrase_combine when no answer exchange asks — not as a return either — nor without another frame with a window', function () {
    // x1, x2 and x4 now say; x3 alone asks: the combine is x3's.
    $say = static fn (string $text): string => rtrim($text, '?').'.';
    $onlyX3 = s1pScene(static function (array $payload) use ($say): array {
        foreach ([0, 1, 3] as $i) {
            $payload['dialogue'][$i]['messages'][0]['text_target'] = $say($payload['dialogue'][$i]['messages'][0]['text_target']);
        }

        return $payload;
    });
    expect((new PhraseCards)->combine($onlyX3)?->payload['exchange']['step'])->toBe(3)
        // A frame coming back as a combine: its own asking exchange, or none — p1's x1 says now.
        ->and((new PhraseCards)->combine($onlyX3, s1pTerm($onlyX3, 'p3'))?->payload['exchange']['step'])->toBe(3)
        ->and((new PhraseCards)->combine($onlyX3, s1pTerm($onlyX3, 'p1')))->toBeNull()
        ->and((new PhraseCards)->combine($onlyX3, s1pTerm($onlyX3, 'p5')))->toBeNull()
        ->and(s1pStage()->returned($onlyX3, s1pTerm($onlyX3, 'p1'), CardKind::PhraseCombine, 0, PlanLevel::Intermediate)?->kind)
        ->toBe(PhraseSeries::kind($onlyX3, s1pTerm($onlyX3, 'p1'), 0));

    // Nothing asks: no combine, today or back.
    $noQuestion = s1pScene(static function (array $payload) use ($say): array {
        foreach ([0, 1, 2, 3] as $i) {
            $payload['dialogue'][$i]['messages'][0]['text_target'] = $say($payload['dialogue'][$i]['messages'][0]['text_target']);
        }

        return $payload;
    });
    foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
        $drafts = s1pStage(null, 0)->build($noQuestion, $level);
        expect(array_filter($drafts, static fn (CardDraft $d): bool => $d->kind === CardKind::PhraseCombine))->toBe([], $level->value)
            ->and($drafts)->toHaveCount(23, $level->value);
    }
    expect((new PhraseCards)->combine($noQuestion))->toBeNull()
        ->and((new PhraseCards)->combine($noQuestion, s1pTerm($noQuestion, 'p2')))->toBeNull()
        ->and(s1pStage()->returned($noQuestion, s1pTerm($noQuestion, 'p2'), CardKind::PhraseCombine, 0, PlanLevel::Intermediate)?->kind)->not->toBe(CardKind::PhraseCombine);

    // A target whose pack names no sentence ends cannot tell a question: no combine.
    $clean = s1pScene();
    $unmarked = new SceneMaterial($clean->sceneId, $clean->lesson, $clean->terms, LanguagePack::none('en'), $clean->native);
    expect((new PhraseCards)->combine($unmarked))->toBeNull();

    // No other frame with a window to tell the right one from.
    $alone = s1pScene(static function (array $payload): array {
        foreach ([1, 2, 4, 5] as $i) {
            $payload['phrases'][$i]['slot'] = null;
        }

        return $payload;
    });
    expect((new PhraseCards)->combine($alone))->toBeNull()
        ->and(array_filter(s1pStage()->build($alone, PlanLevel::Beginner), static fn (CardDraft $d): bool => $d->kind === CardKind::PhraseCombine))->toBe([]);
});

// Canon (SESSION-1e, разд. 3): «у каждого из frames[] — said {index, text_target, text_native, audio}: наполнение, сказанное
// этим каркасом в диалоге; нет сказанного — первое». Catches frames without their whole phrase, the right frame said with
// another filler than the exchange answers with, a wrong frame said with its first filler though the dialogue says another,
// and a sound that is not that phrase's.
it('says every frame of phrase_combine whole — the right one with the filler its exchange says, a wrong one with the filler the dialogue says it with', function () {
    // x1 says; p1 answers x2 with «shoulder» and x3 with «neck» (first said, in x1, with «lower back»); p5 is said «for two days».
    $scene = s1pScene(static function (array $payload): array {
        $payload['dialogue'][0]['messages'][0]['text_target'] = 'Tell me where it hurts.';
        // Both sides of a line move together: the card shows the model's own translation of it (наряд BACK-TAILS-1 §2.3).
        foreach ([1 => ['It hurts in his shoulder.', 'У него болит плечо.'], 2 => ['It hurts in his neck.', 'У него болит шея.']] as $i => [$text, $native]) {
            $payload['dialogue'][$i]['messages'][1]['phrase_id'] = 'p1';
            $payload['dialogue'][$i]['messages'][1]['text_target'] = $text;
            $payload['dialogue'][$i]['messages'][1]['text_native'] = $native;
        }
        $payload['dialogue'][4]['messages'][1]['text_target'] = 'Okay, he will rest for two days.';

        return $payload;
    });
    $payload = (new PhraseCards)->combine($scene)?->payload;
    $frames = array_column($payload['frames'] ?? [], null, 'ref');

    expect($payload['exchange']['step'] ?? null)->toBe(2)
        ->and($payload['correct_frame'] ?? null)->toBe('p1')
        ->and($payload['correct_filler'] ?? null)->toBe(2)
        ->and($scene->saidIndex(s1pTerm($scene, 'p1')))->toBe(0)
        ->and(array_keys($frames))->toEqualCanonicalizing(['p1', 'p6', 'p5'])
        ->and(array_map(static fn (array $f): array => array_keys($f), $frames))->each->toBe(['ref', 'frame_target', 'frame_native', 'said'])
        ->and($frames['p1']['said'])->toBe(['index' => 2, 'text_target' => 'It hurts in his shoulder.', 'text_native' => 'У него болит плечо.', 'audio' => Audio::of('p1.f3')])
        ->and($frames['p5']['said'])->toBe(['index' => 1, 'text_target' => 'He will rest for two days.', 'text_native' => 'Он будет отдыхать два дня.', 'audio' => Audio::of('p5')])
        ->and($frames['p6']['said'])->toBe(['index' => 0, 'text_target' => 'Do we need an X-ray?', 'text_native' => 'Нам нужно сделать рентген?', 'audio' => Audio::of('p6')]);

    // A frame the dialogue does not say: its phrase is the frame with its first filler.
    $p2 = s1pTerm($scene, 'p2');
    expect($scene->lesson->linesOf('p2'))->toBe([])
        ->and(CardObjects::whole($scene, $p2, $scene->saidIndex($p2)))->toBe([
            'index' => 0, 'text_target' => 'It started three days ago.', 'text_native' => 'Началось три дня назад.', 'audio' => Audio::of('p2'),
        ])
        ->and(CardObjects::whole($scene, $p2, null))->toBe(CardObjects::whole($scene, $p2, 0));
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
        ->and($cards->sayWhole($scene, $p4, PlanLevel::Intermediate))->toBeNull()
        ->and(s1pStage()->returned($scene, $p4, CardKind::PhraseChooseBack, null, PlanLevel::Intermediate)?->payload)->toEqual($back);
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
        ->and(s1pStage()->returned($lone, $p4, CardKind::PhraseChooseBack, null, PlanLevel::Intermediate))->toBeNull()
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
    $mid = PlanLevel::Intermediate;

    expect($stage->again($scene, $p1, CardKind::PhraseSlot, 0, [0, 1], $mid)?->kind)->toBe(CardKind::PhraseSlot)
        ->and($filler($stage->again($scene, $p1, CardKind::PhraseSlot, 0, [0, 1], $mid)))->toBe(2)
        // Failed on 2 while 0 is taken: round after 2 comes 0 (taken), then 1 (free) — the free one.
        ->and($filler($stage->again($scene, $p1, CardKind::PhraseChooseBack, 2, [0, 2], $mid)))->toBe(1)
        // Every filler taken: the next round the slot after the failed one.
        ->and($filler($stage->again($scene, $p1, CardKind::PhraseSlotListen, 1, [0, 1, 2], $mid)))->toBe(2)
        ->and($filler($stage->again($scene, $p1, CardKind::PhraseAssemble, 2, [0, 1, 2], $mid)))->toBe(0)
        ->and($stage->again($scene, $p1, CardKind::PhraseAssemble, 2, [0, 1, 2], $mid)?->kind)->toBe(CardKind::PhraseAssemble)
        // Said aloud: a repeat with the next filler.
        ->and($filler($stage->again($scene, $p1, CardKind::PhraseRepeat, 1, [0, 1, 2], $mid)))->toBe(2)
        // «Скажи целиком» says every value of the window already (наряд FIX-2, п. 5): its copy is the whole card again.
        ->and($stage->again($scene, $p1, CardKind::PhraseOtherSlot, 2, [0, 1, 2], $mid)?->payload)
        ->toEqual((new PhraseCards)->sayWhole($scene, $p1, $mid)?->payload)
        // No filler of its own: nothing to vary — the copy is the card as it was.
        ->and($stage->again($scene, $p1, CardKind::PhraseCombine, 0, [0], $mid))->toBeNull()
        ->and($filler($stage->again($scene, s1pTerm($scene, 'p4'), CardKind::PhraseChooseBack, null, [], $mid)))->toBeNull();

    // A frame of one filler says its copy with that filler again.
    $oneFiller = s1pScene(static function (array $payload): array {
        $payload['phrases'][0]['slot']['fillers'] = [$payload['phrases'][0]['slot']['fillers'][0]];

        return $payload;
    });
    expect($filler(s1pStage()->again($oneFiller, s1pTerm($oneFiller, 'p1'), CardKind::PhraseSlot, 0, [0], PlanLevel::Intermediate)))->toBe(0);
});

// Canon (SESSION-1d, разд. 4): «в день возврата единица «фраза» приходит видом, которым её провалили последний раз, снова с
// другим наполнением». Catches a frame always coming back as phrase_slot, a return said with the failed filler, and a
// production coming back as a recognition.
it('brings a frame back as the kind it failed as the last time, said with another filler', function () {
    $scene = s1pScene();
    $stage = s1pStage();
    $p1 = s1pTerm($scene, 'p1');
    $shape = static fn (?CardDraft $d): ?array => $d === null ? null : [$d->kind, PhraseSeries::fillerOf($d->kind, $d->payload)];

    $mid = PlanLevel::Intermediate;

    expect($shape($stage->returned($scene, $p1, CardKind::PhraseSlotListen, 1, $mid)))->toBe([CardKind::PhraseSlotListen, 2])
        ->and($shape($stage->returned($scene, $p1, CardKind::PhraseChooseBack, 2, $mid)))->toBe([CardKind::PhraseChooseBack, 0])
        ->and($shape($stage->returned($scene, $p1, CardKind::PhraseAssemble, 0, $mid)))->toBe([CardKind::PhraseAssemble, 1])
        ->and($shape($stage->returned($scene, $p1, CardKind::PhraseRepeat, 1, $mid)))->toBe([CardKind::PhraseRepeat, 2])
        ->and($stage->returned($scene, $p1, CardKind::PhraseOtherSlot, 2, $mid)?->kind)->toBe(CardKind::PhraseOtherSlot)
        ->and($stage->returned($scene, $p1, CardKind::PhraseCombine, 0, $mid)?->payload['correct_frame'])->toBe('p1')
        ->and($stage->returned($scene, $p1, CardKind::PhraseCombine, 0, $mid)?->kind)->toBe(CardKind::PhraseCombine)
        // What it failed as is not known: its first recognition.
        ->and($shape($stage->returned($scene, $p1, null, null, $mid)))->toBe([PhraseSeries::kind($scene, $p1, 0), 0]);
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
        ->and(CardObjects::fillers($scene, s1pTerm($scene, 'p3'))[1]['native_line'])->toBe('Боль ноющая, когда он наклоняется.');
});

// Canon (наряд BACK-TAILS-1 §2.3): «перевод реплики для сказанного наполнения — text_native реплики модели, а не сборка
// «родной каркас + родное наполнение»». The model translated a whole sentence and made it read; the glue of two strings
// leaves «Это у него уже уже три дня». Catches the assembly served where a line of the visit says the filler — and the
// quote taken from a line said after conversational glue, where the target side of the card has no glue and the native
// side would.
it('shows the model\'s own translation of a line for the filler it says, and the assembly for every other', function () {
    $scene = s1pScene(static function (array $payload): array {
        // x1 says p1 with «lower back» and the model writes a sentence of its own, not «У него болит поясница.».
        $payload['dialogue'][0]['messages'][1]['text_native'] = 'Поясница у него болит.';
        // x5 says p5 with «at home» AFTER glue: its translation is of the longer line.
        $payload['dialogue'][4]['messages'][1]['text_target'] = 'Okay, he will rest at home.';
        $payload['dialogue'][4]['messages'][1]['text_native'] = 'Хорошо, он будет отдыхать дома.';

        return $payload;
    });
    $p1 = CardObjects::fillers($scene, s1pTerm($scene, 'p1'));
    $p5 = CardObjects::fillers($scene, s1pTerm($scene, 'p5'));

    expect(array_column($p1, 'native_line'))->toBe([
        // Said in x1: the model's line.
        'Поясница у него болит.',
        // Said by no line: the frame's translation with the filler's.
        'У него болит шея.',
        'У него болит плечо.',
    ])
        ->and(CardObjects::said($scene, s1pTerm($scene, 'p1'))['text_native'])->toBe('Поясница у него болит.')
        // The glued line is not quoted: the card shows «He will rest at home.», and its translation has no «Хорошо».
        ->and($p5[0]['native_line'])->toBe('Он будет отдыхать дома.')
        ->and(CardObjects::said($scene, s1pTerm($scene, 'p5'))['text_native'])->toBe('Он будет отдыхать дома.');
});

it('asks phrase_assemble for the frame\'s words and two words of the frames after it, lower-cased but «I»', function () {
    $scene = s1pScene();
    $cards = new PhraseCards;
    $p3 = $cards->assemble($scene, s1pTerm($scene, 'p3'), 0)?->payload;

    expect(array_keys($p3))->toBe(['scene_id', 'frame', 'target_native', 'tiles', 'chips', 'expected'])
        ->and($p3['frame'])->toBe(CardObjects::frame($scene, s1pTerm($scene, 'p3')))
        ->and($p3['target_native'])->toBe('Боль острая, когда он наклоняется.')
        // The next frame p4 gives two words p3 does not have («he» it has).
        ->and($p3['tiles'])->toBe(Shuffle::seeded($scene->seed('p3:assemble'), ['the', 'pain', 'is', 'when', 'he', 'bends', "doesn't", 'have']))
        ->and($p3['chips'])->toBe(CardObjects::fillers($scene, s1pTerm($scene, 'p3')))
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

it('gives every one of the eight kinds its exact keys over the deals of both levels', function () {
    $keys = [
        'phrase_intro' => ['scene_id', 'frame', 'said'],
        'phrase_assemble' => ['scene_id', 'frame', 'target_native', 'tiles', 'chips', 'expected'],
        'phrase_choose_back' => ['scene_id', 'prompt', 'options', 'correct'],
        'phrase_slot' => ['scene_id', 'frame', 'prompt_native', 'options', 'correct'],
        'phrase_slot_listen' => ['scene_id', 'frame', 'filler_index', 'audio', 'options', 'correct'],
        'phrase_repeat' => ['scene_id', 'frame', 'filler_index', 'expected_text', 'key', 'speech_mode', 'audio'],
        'phrase_other_slot' => ['scene_id', 'frame', 'partner_line', 'key', 'rounds', 'speech_mode', 'own_round'],
        'phrase_combine' => ['scene_id', 'exchange', 'partner_line', 'frames', 'correct_frame', 'chips', 'correct_filler'],
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
                foreach ($draft->payload['rounds'] ?? [] as $round) {
                    expect(array_keys($round))->toBe(['filler_index', 'expected_text', 'task_native']);
                }
                if (isset($draft->payload['own_round'])) {
                    expect(array_keys($draft->payload['own_round']))->toBe(['task_native', 'examples', 'speech_mode', 'judge']);
                }
                foreach ($draft->payload['frames'] ?? [] as $frame) {
                    expect(array_keys($frame))->toBe(['ref', 'frame_target', 'frame_native', 'said'])
                        ->and(array_keys($frame['said']))->toBe(['index', 'text_target', 'text_native', 'audio']);
                }
                if (isset($draft->payload['correct'])) {
                    expect(array_column($draft->payload['options'], 'id'))->toContain($draft->payload['correct'])
                        ->and(array_unique(array_map('mb_strtolower', array_column($draft->payload['options'], 'text'))))->toHaveCount(count($draft->payload['options']));
                }
            }
        }
    }

    expect(array_keys($seen))->toEqualCanonicalizing(array_keys($keys))
        ->and(array_keys(CardObjects::fillers(s1pScene(), s1pTerm(s1pScene(), 'p2'))[0]))->toBe(['index', 'target', 'native', 'pronunciation_native', 'in_dialogue', 'native_line', 'audio']);
});


