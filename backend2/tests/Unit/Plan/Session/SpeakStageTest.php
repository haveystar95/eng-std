<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Assembly\Audio;
use App\Modules\Plan\Domain\Assembly\CardDraft;
use App\Modules\Plan\Domain\Assembly\PartnerLines;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Assembly\SpeakStage;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Service\Shuffle;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\Service\UnitStates;
use App\Modules\Plan\Domain\Service\Words;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * «ГОВОРЮ САМ» (наряд SESSION-1a, разд. 1–2; SPEC §4): at most six `speak_answer` in the order of the visit, then
 * `speak_echo` and `speak_retell` on partner lines the pace card did not take; the review day's ten and the
 * rehearsal's twelve. The fake lesson: x1–x5 answer (x4 on a frame without a slot), x6 rescue, x7–x8 ask on one frame.
 */

/** A fixed scene id — every shuffle and rotation is seeded by it. */
function s1spSceneId(int $n = 1): PlanSceneId
{
    return PlanSceneId::fromString(sprintf('01J8SPEAKSTAGE0000000000%02d', $n));
}

function s1spLesson(PlanSceneId $sceneId): Lesson
{
    $payload = FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Intermediate, null, 8, 8, FakePlanModel::roles(), new EarlierDays));

    return LessonAssembly::serve((new LessonParser)->parse($payload), $sceneId->value, lessonPacks()->for('en'));
}

/**
 * @param  (callable(Lesson): Lesson)|null  $edit  a change to the served lesson; the terms stay those of the lesson as served
 * @param  (callable(PlanTerm): bool)|null  $keepTerm
 */
function s1spScene(int $n = 1, ?callable $edit = null, ?callable $keepTerm = null): SceneMaterial
{
    $sceneId = s1spSceneId($n);
    $packs = lessonPacks();
    $lesson = s1spLesson($sceneId);
    $terms = PlanTerm::fromLesson($sceneId, $lesson, static fn (): PlanTermId => PlanTermId::generate());
    if ($keepTerm !== null) {
        $terms = array_values(array_filter($terms, $keepTerm));
    }

    return new SceneMaterial($sceneId, $edit === null ? $lesson : $edit($lesson), $terms, $packs->for('en'), $packs->for('ru'));
}

/** @return list<string> `kind@ref` of each draft */
function s1spShape(array $drafts): array
{
    return array_map(static fn (CardDraft $d): string => $d->kind->value.'@'.$d->unitRef, $drafts);
}

/** @return list<string> `sceneId:xN` of each draft */
function s1spAddresses(array $drafts): array
{
    return array_map(static fn (CardDraft $d): string => $d->payload['scene_id'].':'.$d->unitRef, $drafts);
}

/** The lesson with the partner line of exchange `$step` saying `$text`. */
function s1spPartnerSays(Lesson $lesson, int $step, string $text): Lesson
{
    return $lesson->withExchanges(array_map(
        static fn (Exchange $e): Exchange => $e->step !== $step ? $e : $e->withMessages(array_map(
            static fn (Message $m): Message => $m->isLearner() ? $m : $m->withText($text),
            $e->messages,
        )),
        $lesson->exchanges,
    ));
}

it('deals six answers in the order of the visit, then the echo, then the retell — eight cards', function () {
    $drafts = (new SpeakStage)->build(s1spScene());

    expect(s1spShape($drafts))->toBe([
        'speak_answer@x1', 'speak_answer@x2', 'speak_answer@x3', 'speak_answer@x4', 'speak_answer@x5', 'speak_answer@x7',
        'speak_echo@x5', 'speak_retell@x1',
    ]);
    foreach ($drafts as $draft) {
        expect($draft->unitKind)->toBe(UnitKind::Exchange)
            ->and(array_key_first($draft->payload))->toBe('scene_id')
            ->and($draft->payload['scene_id'])->toBe(s1spSceneId()->value)
            ->and($draft->payload['exchange']['ref'])->toBe($draft->unitRef);
    }
});

// Canon: speak_echo — the longest partner line of ≤ 18 words the pace card did not take; speak_retell — the next one.
it('echoes the longest partner line of at most eighteen words that is not the pace line, and retells the next', function () {
    $scene = s1spScene();
    $drafts = (new SpeakStage)->build($scene);
    $echo = $drafts[6]->payload;
    $retell = $drafts[7]->payload;
    $pace = PartnerLines::pace($scene)['step'];

    // Independently: every partner line of ≤ 18 words but the pace line, longest first, the lower step between equals.
    $lengths = [];
    foreach ($scene->lesson->exchanges as $exchange) {
        $count = Words::count($exchange->partner()->textTarget);
        if ($exchange->step !== $pace && $count <= 18) {
            $lengths[$exchange->step] = $count;
        }
    }
    uksort($lengths, static fn (int $a, int $b): int => [$lengths[$b], $a] <=> [$lengths[$a], $b]);
    [$first, $second] = array_keys($lengths);

    expect($pace)->toBe(3)
        ->and($echo['exchange']['step'])->toBe($first)->toBe(5)
        ->and($retell['exchange']['step'])->toBe($second)->toBe(1)
        ->and($echo['partner_line']['ref'])->not->toBe(SpokenLines::partnerRef($pace))
        ->and($retell['partner_line']['ref'])->not->toBe(SpokenLines::partnerRef($pace))
        ->and($retell['partner_line']['ref'])->not->toBe($echo['partner_line']['ref']);

    // Every partner line ≤ 10 words: the longest of ≤ 18 IS the pace line (x3) — the echo must pass it by.
    $short = s1spScene(1, static fn (Lesson $l): Lesson => $l->withExchanges(array_values(array_filter(
        $l->exchanges,
        static fn (Exchange $e): bool => Words::count($e->partner()->textTarget) <= 10,
    ))));
    expect(PartnerLines::pace($short)['step'])->toBe(3)
        ->and(s1spShape(array_slice((new SpeakStage)->build($short), -2)))->toBe(['speak_echo@x7', 'speak_retell@x2']);

    // Eighteen words is still a line to echo; nineteen is not.
    $eighteen = 'It looks like a muscle strain, so he should rest at home and use a heating pad today.';
    $nineteen = 'It looks like a muscle strain, so he should rest at home and use a warm heating pad today.';
    expect(Words::count($eighteen))->toBe(18)->and(Words::count($nineteen))->toBe(19)
        ->and(s1spShape(array_slice((new SpeakStage)->build(s1spScene(1, static fn (Lesson $l): Lesson => s1spPartnerSays($l, 5, $eighteen))), -2)))
        ->toBe(['speak_echo@x5', 'speak_retell@x1'])
        ->and(s1spShape(array_slice((new SpeakStage)->build(s1spScene(1, static fn (Lesson $l): Lesson => s1spPartnerSays($l, 5, $nineteen))), -2)))
        ->toBe(['speak_echo@x1', 'speak_retell@x7']);

    // No partner line fits: neither card is dealt.
    $long = s1spScene(1, static function (Lesson $l) use ($nineteen): Lesson {
        foreach ($l->exchanges as $e) {
            $l = s1spPartnerSays($l, $e->step, $nineteen);
        }

        return $l;
    });
    expect(PartnerLines::pace($long))->toBeNull()
        ->and(s1spShape((new SpeakStage)->build($long)))->toBe([
            'speak_answer@x1', 'speak_answer@x2', 'speak_answer@x3', 'speak_answer@x4', 'speak_answer@x5', 'speak_answer@x7',
        ]);
});

it('writes speak_answer with exactly its keys: the exchange, the line answered, the own line, the frame with its fillers', function () {
    $scene = s1spScene();
    $exchange = $scene->exchange(1);
    $payload = (new SpeakStage)->speakAnswer($scene, $exchange)->payload;

    expect(array_keys($payload))->toBe(['scene_id', 'exchange', 'partner_line', 'own_line', 'task_native', 'frame', 'key', 'coverage_min', 'hint', 'judge'])
        ->and($payload['exchange'])->toBe(['ref' => 'x1', 'step' => 1, 'kind' => 'answer'])
        ->and($payload['partner_line'])->toBe([
            'ref' => 'x1',
            'text_target' => 'Where does it hurt: his upper back or his lower back?',
            'text_native' => 'Где болит: вверху спины или в пояснице?',
            'audio' => Audio::of('x1'),
        ])
        ->and($payload['own_line'])->toBe([
            'ref' => 'x1b',
            'text_target' => 'It hurts in his lower back.',
            'text_native' => 'У него болит поясница.',
            'frame_ref' => 'p1',
            'filler_index' => 0,
            'key' => $exchange->learner()->speakingKey,
            'audio' => Audio::of('x1b'),
        ])
        ->and($payload['own_line']['key'])->not->toBeNull()
        ->and($payload['task_native'])->toBe('У него болит поясница.')
        ->and($payload['key'])->toBe($exchange->learner()->speakingKey)
        ->and($payload['hint'])->toBe('It hurts in his ___.')
        ->and($payload['judge'])->toBeTrue()
        // «It hurts in his» — four counted words: most of them; «It started» — two: all.
        ->and($payload['coverage_min'])->toBe(0.7)
        ->and((new SpeakStage)->speakAnswer($scene, $scene->exchange(2))->payload['coverage_min'])->toBe(1.0);

    $frame = $payload['frame'];
    expect(array_keys($frame))->toBe(['ref', 'kind', 'frame_target', 'frame_native', 'frame_pronunciation_native', 'slot'])
        ->and($frame['ref'])->toBe('p1')
        ->and($frame['kind'])->toBe('answer')
        ->and($frame['frame_target'])->toBe('It hurts in his ___.')
        ->and($frame['frame_native'])->toBe('У него болит ___.')
        ->and($frame['frame_pronunciation_native'])->toBe('ит хёртс ин хиз ___')
        ->and(array_keys($frame['slot']))->toBe(['hint_native', 'fillers'])
        ->and($frame['slot']['hint_native'])->toBe('где болит')
        ->and($frame['slot']['fillers'])->toBe([
            ['index' => 0, 'target' => 'lower back', 'native' => 'поясница', 'pronunciation_native' => 'лоуэр бэк', 'in_dialogue' => true, 'native_line' => 'У него болит поясница.', 'audio' => Audio::of('p1')],
            ['index' => 1, 'target' => 'neck', 'native' => 'шея', 'pronunciation_native' => 'нэк', 'in_dialogue' => false, 'native_line' => 'У него болит шея.', 'audio' => Audio::of('p1.f2')],
            ['index' => 2, 'target' => 'shoulder', 'native' => 'плечо', 'pronunciation_native' => 'шоулдер', 'in_dialogue' => false, 'native_line' => 'У него болит плечо.', 'audio' => Audio::of('p1.f3')],
        ]);

    // A frame without a slot: no slot, no filler in the line.
    $noSlot = (new SpeakStage)->speakAnswer($scene, $scene->exchange(4))->payload;
    expect($noSlot['frame']['slot'])->toBeNull()
        ->and($noSlot['frame']['ref'])->toBe('p4')
        ->and($noSlot['own_line']['filler_index'])->toBeNull()
        ->and($noSlot['hint'])->toBe("He doesn't have a fever.");
});

// D-22: an ask is the learner's question — the line it answers is the partner line of the exchange before it.
it('gives an ask exchange the partner line of the previous exchange, and none when it opens the visit', function () {
    $scene = s1spScene();
    $stage = new SpeakStage;
    $x7 = $stage->speakAnswer($scene, $scene->exchange(7))->payload;
    $x8 = $stage->speakAnswer($scene, $scene->exchange(8))->payload;

    expect($x7['exchange'])->toBe(['ref' => 'x7', 'step' => 7, 'kind' => 'ask'])
        ->and($x7['partner_line'])->toBe([
            'ref' => 'x6',
            'text_target' => 'He should rest and use a heating pad.',
            'text_native' => 'Ему нужен покой и грелка.',
            'audio' => Audio::of('x6'),
        ])
        ->and($x7['own_line']['ref'])->toBe('x7b')
        ->and($x7['own_line']['text_target'])->toBe('Do we need an X-ray?')
        ->and($x7['own_line']['filler_index'])->toBe(0)
        ->and($x7['frame']['kind'])->toBe('ask')
        ->and($x8['partner_line']['ref'])->toBe('x7')
        ->and($x8['partner_line']['text_target'])->toBe('No, an X-ray is not needed for a muscle strain.')
        ->and($x8['own_line']['filler_index'])->toBe(1)
        // The exchange before is found by the step, not by the instance handed in.
        ->and($stage->speakAnswer($scene, $scene->exchange(7)->withStep(7))->payload['partner_line']['ref'])->toBe('x6')
        // An answer keeps its own partner line.
        ->and($stage->speakAnswer($scene, $scene->exchange(3))->payload['partner_line']['ref'])->toBe('x3');

    $askFirst = s1spScene(1, static fn (Lesson $l): Lesson => $l->withExchanges([$l->exchange(7), $l->exchange(8)]));
    expect($stage->speakAnswer($askFirst, $askFirst->exchange(7))->payload['partner_line'])->toBeNull()
        ->and($stage->speakAnswer($askFirst, $askFirst->exchange(8))->payload['partner_line']['ref'])->toBe('x7');
});

it('deals no speak_answer for a rescue, a line on no frame, a frame with no term or an exchange missing a line', function () {
    $scene = s1spScene();
    $stage = new SpeakStage;
    $x1 = $scene->exchange(1);
    $learner = $x1->learner();
    $unframed = new Message(
        $learner->speaker, $learner->roleTarget, $learner->roleNative, $learner->textTarget, $learner->textNative,
        $learner->pronunciationNative, $learner->speakingKey, $learner->simplifiedVariants, null, null,
    );

    expect($scene->exchange(6)->kind)->toBe(ExchangeKind::Rescue)
        ->and($stage->speakAnswer($scene, $scene->exchange(6)))->toBeNull()
        ->and($stage->speakAnswer($scene, $x1->withMessages([$x1->partner(), $unframed])))->toBeNull()
        ->and($stage->speakAnswer($scene, $x1->withMessages([$x1->partner()])))->toBeNull()
        ->and($stage->speakAnswer($scene, $x1->withMessages([$learner])))->toBeNull();

    // A phrase term stored without its frame is no frame to answer on.
    $p1 = $scene->term('p1');
    $frameless = PlanTerm::reconstitute(
        $p1->id(), $p1->sceneId(), $p1->kind(), $p1->ref(), $p1->position(), $p1->textTarget(), $p1->textNative(),
        $p1->pronunciationNative(), null, null, null, $p1->speakingKey(), [], null, null,
    );
    $withFramelessP1 = new SceneMaterial(
        $scene->sceneId, $scene->lesson,
        array_map(static fn (PlanTerm $t): PlanTerm => $t->ref() === 'p1' ? $frameless : $t, $scene->terms),
        $scene->target, $scene->native,
    );
    expect($withFramelessP1->phraseTerm('p1'))->not->toBeNull()
        ->and($stage->speakAnswer($withFramelessP1, $withFramelessP1->exchange(1)))->toBeNull();

    // A step the lesson repeats (the model's slip) is one exchange — the first — and one card.
    $repeated = s1spScene(1, static fn (Lesson $l): Lesson => $l->withExchanges(array_map(
        static fn (Exchange $e): Exchange => $e->step === 2 ? $e->withStep(1) : $e,
        $l->exchanges,
    )));
    expect(array_slice(s1spShape($stage->build($repeated)), 0, 6))
        ->toBe(['speak_answer@x1', 'speak_answer@x3', 'speak_answer@x4', 'speak_answer@x5', 'speak_answer@x7', 'speak_answer@x8'])
        ->and($stage->build($repeated)[0]->payload['own_line']['text_target'])->toBe('It hurts in his lower back.');

    // p1 is no term of the day: x1 has no card, and the sixth answer is x8.
    $withoutP1 = s1spScene(1, null, static fn (PlanTerm $t): bool => $t->ref() !== 'p1');
    expect($stage->speakAnswer($withoutP1, $withoutP1->exchange(1)))->toBeNull()
        ->and(array_slice(s1spShape($stage->build($withoutP1)), 0, 6))
        ->toBe(['speak_answer@x2', 'speak_answer@x3', 'speak_answer@x4', 'speak_answer@x5', 'speak_answer@x7', 'speak_answer@x8']);
});

it('writes speak_echo and speak_retell with exactly their keys', function () {
    $drafts = (new SpeakStage)->build(s1spScene());
    $x5 = [
        'ref' => 'x5',
        'text_target' => 'It looks like a muscle strain, so he should rest and use a heating pad.',
        'text_native' => 'Похоже на растяжение мышцы, так что ему нужен покой и грелка.',
        'audio' => Audio::of('x5'),
    ];

    expect($drafts[6]->kind)->toBe(CardKind::SpeakEcho)
        ->and($drafts[6]->payload)->toBe([
            'scene_id' => s1spSceneId()->value,
            'exchange' => ['ref' => 'x5', 'step' => 5, 'kind' => 'answer'],
            'partner_line' => $x5,
            'expected_text' => 'It looks like a muscle strain, so he should rest and use a heating pad.',
            'coverage_min' => 0.7,
            'pause_ms' => 3000,
        ])
        ->and($drafts[7]->kind)->toBe(CardKind::SpeakRetell)
        ->and($drafts[7]->payload)->toBe([
            'scene_id' => s1spSceneId()->value,
            'exchange' => ['ref' => 'x1', 'step' => 1, 'kind' => 'answer'],
            'partner_line' => [
                'ref' => 'x1',
                'text_target' => 'Where does it hurt: his upper back or his lower back?',
                'text_native' => 'Где болит: вверху спины или в пояснице?',
                'audio' => Audio::of('x1'),
            ],
            'reveal' => ['text_target' => 'Where does it hurt: his upper back or his lower back?', 'text_native' => 'Где болит: вверху спины или в пояснице?'],
            'judge' => true,
        ]);
});

// Canon: the review day says aloud the exchanges of the two previous scenes, ≤ 10, seeded; a returned exchange is not dealt twice.
it('reviews two scenes with at most ten answers, seeded, the returned exchanges left out, in scene and step order', function () {
    $scenes = [s1spScene(1), s1spScene(2)];
    $stage = new SpeakStage;
    $seed = 'review:'.s1spSceneId(1)->value.':'.s1spSceneId(2)->value;
    $review = $stage->review($scenes, [], $seed);

    // Seven eligible exchanges per scene (x1–x5, x7, x8): fourteen, of which the seed keeps ten.
    $pool = [];
    foreach ([1, 2] as $n) {
        foreach ([1, 2, 3, 4, 5, 7, 8] as $step) {
            $pool[] = s1spSceneId($n)->value.':x'.$step;
        }
    }
    $expected = array_slice(Shuffle::seeded($seed, $pool), 0, 10);
    usort($expected, static fn (string $a, string $b): int => array_search($a, $pool, true) <=> array_search($b, $pool, true));

    expect($review)->toHaveCount(10)
        ->and(s1spAddresses($review))->toBe($expected)
        ->and(array_map(static fn (CardDraft $d): CardKind => $d->kind, $review))->each->toBe(CardKind::SpeakAnswer)
        ->and($stage->review([s1spScene(1), s1spScene(2)], [], $seed))->toEqual($review)
        ->and(s1spAddresses($stage->review($scenes, [], 'review:another')))->not->toBe($expected);

    // The shuffle's own order is not what is served: back to scene, then step.
    expect(array_slice(Shuffle::seeded($seed, $pool), 0, 10))->not->toBe($expected);

    $excluded = [
        UnitStates::key(s1spSceneId(1)->value, UnitKind::Exchange, 'x1'),
        UnitStates::key(s1spSceneId(2)->value, UnitKind::Exchange, 'x7'),
    ];
    $withoutReturned = $stage->review($scenes, $excluded, $seed);
    $left = array_values(array_diff($pool, [s1spSceneId(1)->value.':x1', s1spSceneId(2)->value.':x7']));
    $expectedLeft = array_slice(Shuffle::seeded($seed, $left), 0, 10);
    usort($expectedLeft, static fn (string $a, string $b): int => array_search($a, $pool, true) <=> array_search($b, $pool, true));

    expect(s1spAddresses($withoutReturned))->toBe($expectedLeft)
        ->and(s1spAddresses($withoutReturned))->not->toContain(s1spSceneId(1)->value.':x1')
        ->and(s1spAddresses($withoutReturned))->not->toContain(s1spSceneId(2)->value.':x7');

    // Fewer than ten left: every one of them, in order.
    $many = array_map(static fn (string $address): string => str_replace(':x', ':exchange:x', $address), array_slice($pool, 0, 11));
    expect(s1spAddresses($stage->review($scenes, $many, $seed)))->toBe(array_slice($pool, 11));
});

// Canon: the rehearsal says aloud every scene of the plan, ≤ 12, one or two per scene, seeded.
it('rehearses three scenes with two answers each, by step, seeded by the scene', function () {
    $scenes = [s1spScene(1), s1spScene(2), s1spScene(3)];
    $rehearsal = (new SpeakStage)->rehearsal($scenes);

    $expected = [];
    foreach ([1, 2, 3] as $n) {
        $steps = array_slice(Shuffle::seeded('rehearsal:'.s1spSceneId($n)->value, [1, 2, 3, 4, 5, 7, 8]), 0, 2);
        sort($steps);
        foreach ($steps as $step) {
            $expected[] = s1spSceneId($n)->value.':x'.$step;
        }
    }
    $perScene = array_count_values(array_map(static fn (CardDraft $d): string => $d->payload['scene_id'], $rehearsal));

    expect(count($rehearsal))->toBeLessThanOrEqual(12)
        ->and(s1spAddresses($rehearsal))->toBe($expected)
        ->and($perScene)->toBe([s1spSceneId(1)->value => 2, s1spSceneId(2)->value => 2, s1spSceneId(3)->value => 2])
        ->and(array_map(static fn (CardDraft $d): CardKind => $d->kind, $rehearsal))->each->toBe(CardKind::SpeakAnswer)
        ->and((new SpeakStage)->rehearsal([s1spScene(1), s1spScene(2), s1spScene(3)]))->toEqual($rehearsal);
});

it('rehearses thirteen scenes with exactly twelve answers, one per scene, and seven with the second round in order', function () {
    $thirteen = array_map(static fn (int $n): SceneMaterial => s1spScene($n), range(1, 13));
    $rehearsal = (new SpeakStage)->rehearsal($thirteen);
    $perScene = array_count_values(array_map(static fn (CardDraft $d): string => $d->payload['scene_id'], $rehearsal));

    $expected = [];
    foreach (range(1, 12) as $n) {
        $expected[] = s1spSceneId($n)->value.':x'.Shuffle::seeded('rehearsal:'.s1spSceneId($n)->value, [1, 2, 3, 4, 5, 7, 8])[0];
    }

    expect($rehearsal)->toHaveCount(12)
        ->and(max($perScene))->toBe(1)
        ->and(array_keys($perScene))->toBe(array_map(static fn (int $n): string => s1spSceneId($n)->value, range(1, 12)))
        ->and(s1spAddresses($rehearsal))->toBe($expected);

    // Seven scenes: one each (7), then a second for the first five (12) — the last two keep one.
    $seven = (new SpeakStage)->rehearsal(array_slice($thirteen, 0, 7));
    expect(array_values(array_count_values(array_map(static fn (CardDraft $d): string => $d->payload['scene_id'], $seven))))
        ->toBe([2, 2, 2, 2, 2, 1, 1]);
});
