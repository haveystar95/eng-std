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
 * «ГОВОРЮ САМ» (наряд SESSION-1a, разд. 1–2; SPEC §4; наряд BACK-TAILS-1 §1.1): at most six `speak_answer` in the order
 * of the visit, then `speak_echo` on a partner line the pace card did not take, and `speak_retell` — «Повтори свою
 * реплику» — on a line OF THE LEARNER'S OWN that no `speak_answer` of the day took; the review day's ten and the
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

    // x8 is the one complete exchange the six answers left over, so its learner line is the one said again.
    expect(s1spShape($drafts))->toBe([
        'speak_answer@x1', 'speak_answer@x2', 'speak_answer@x3', 'speak_answer@x4', 'speak_answer@x5', 'speak_answer@x7',
        'speak_echo@x5', 'speak_retell@x8',
    ]);
    foreach ($drafts as $draft) {
        expect($draft->unitKind)->toBe(UnitKind::Exchange)
            ->and(array_key_first($draft->payload))->toBe('scene_id')
            ->and($draft->payload['scene_id'])->toBe(s1spSceneId()->value)
            ->and($draft->payload['exchange']['ref'])->toBe($draft->unitRef);
    }
});

// Canon: speak_echo — the longest partner line of ≤ 18 words the pace card did not take.
it('echoes the longest partner line of at most eighteen words that is not the pace line', function () {
    $scene = s1spScene();
    $drafts = (new SpeakStage)->build($scene);
    $echo = $drafts[6]->payload;
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
    [$first] = array_keys($lengths);

    expect($pace)->toBe(3)
        ->and($echo['exchange']['step'])->toBe($first)->toBe(5)
        ->and($echo['partner_line']['ref'])->not->toBe(SpokenLines::partnerRef($pace));

    // Every partner line ≤ 10 words: the longest of ≤ 18 IS the pace line (x3) — the echo must pass it by.
    $short = s1spScene(1, static fn (Lesson $l): Lesson => $l->withExchanges(array_values(array_filter(
        $l->exchanges,
        static fn (Exchange $e): bool => Words::count($e->partner()->textTarget) <= 10,
    ))));
    // Fewer exchanges here, so all of them fit the six answers — x8 among them, and no learner line is left to say
    // again: the echo is the last card of the stage.
    expect(PartnerLines::pace($short)['step'])->toBe(3)
        ->and(s1spShape(array_slice((new SpeakStage)->build($short), -2)))->toBe(['speak_answer@x8', 'speak_echo@x7']);

    // Eighteen words is still a line to echo; nineteen is not.
    $eighteen = 'It looks like a muscle strain, so he should rest at home and use a heating pad today.';
    $nineteen = 'It looks like a muscle strain, so he should rest at home and use a warm heating pad today.';
    expect(Words::count($eighteen))->toBe(18)->and(Words::count($nineteen))->toBe(19)
        ->and(s1spShape(array_slice((new SpeakStage)->build(s1spScene(1, static fn (Lesson $l): Lesson => s1spPartnerSays($l, 5, $eighteen))), -2)))
        ->toBe(['speak_echo@x5', 'speak_retell@x8'])
        ->and(s1spShape(array_slice((new SpeakStage)->build(s1spScene(1, static fn (Lesson $l): Lesson => s1spPartnerSays($l, 5, $nineteen))), -2)))
        ->toBe(['speak_echo@x1', 'speak_retell@x8']);

    // No partner line fits: the echo is not dealt — the retell is the learner's own line and does not depend on it.
    $long = s1spScene(1, static function (Lesson $l) use ($nineteen): Lesson {
        foreach ($l->exchanges as $e) {
            $l = s1spPartnerSays($l, $e->step, $nineteen);
        }

        return $l;
    });
    expect(PartnerLines::pace($long))->toBeNull()
        ->and(s1spShape((new SpeakStage)->build($long)))->toBe([
            'speak_answer@x1', 'speak_answer@x2', 'speak_answer@x3', 'speak_answer@x4', 'speak_answer@x5', 'speak_answer@x7',
            'speak_retell@x8',
        ]);
});

// Canon (наряд BACK-TAILS-1 §1.1, кадр 35-4): the longest learner line of a complete answer/ask exchange that no
// `speak_answer` of the day took — between two of one length the lower step; a rescue line is not one, and nothing left
// free means no card. Catches the stage asking twice for one line, and a rescue's «Простите, можно помедленнее?» dealt
// as the learner's own line to say again.
it('retells the learner\'s own longest line that no answer of the day took, and none when every line is taken', function () {
    $stage = new SpeakStage;

    // x1..x5 and x7 are answered; x8 is free, x6 is the rescue and is never picked.
    $retell = $stage->build(s1spScene())[7];
    expect($retell->kind)->toBe(CardKind::SpeakRetell)->and($retell->unitRef)->toBe('x8');

    // Only the rescue left free: no card at all.
    $noneFree = s1spScene(1, static fn (Lesson $l): Lesson => $l->withExchanges(array_values(array_filter(
        $l->exchanges,
        static fn (Exchange $e): bool => in_array($e->step, [1, 2, 3, 4, 5, 6, 7], true),
    ))));
    expect(array_values(array_filter($stage->build($noneFree), static fn (CardDraft $d): bool => $d->kind === CardKind::SpeakRetell)))->toBe([]);

    // Two free exchanges: the longer learner line wins, the lower step between equals.
    $longer = s1spScene(1, static fn (Lesson $l): Lesson => $l->withExchanges(array_map(
        static fn (Exchange $e): Exchange => $e->step !== 8 ? $e : $e->withMessages(array_map(
            static fn (Message $m): Message => ! $m->isLearner() ? $m : $m->withText('Do we need an appointment?'),
            $e->messages,
        )),
        $l->exchanges,
    )), static fn (PlanTerm $t): bool => $t->ref() !== 'p1');
    // p1 is gone, so x1 has no `speak_answer` and x8's line is now the shorter of the two free ones.
    expect(array_values(array_filter($stage->build($longer), static fn (CardDraft $d): bool => $d->kind === CardKind::SpeakRetell))[0]->unitRef)->toBe('x1');
});

it('writes speak_answer with exactly its keys: the exchange, the line answered, the own line, the frame with its fillers', function () {
    $scene = s1spScene();
    $exchange = $scene->exchange(1);
    $payload = (new SpeakStage)->speakAnswer($scene, $exchange)->payload;

    expect(array_keys($payload))->toBe(['scene_id', 'exchange', 'partner_line', 'own_line', 'task_native', 'frame', 'key', 'speech_mode', 'hint', 'judge'])
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
        // The learner says their own sentence and only the frame's own words are the key.
        ->and($payload['speech_mode'])->toBe('free');

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

// Canon (наряд FIX-2, п. 3): «вопрос, ожидаемая реплика, подсказка-каркас и обмен для судьи — из одного обмена, ссылка
// одна (payload.exchange)». Catches the card built from two exchanges at once — D-22 put «What is your dog's name?» over
// «Do you have anything ___?» on the owner's live day and showed one question on two cards — and an `ask` given a
// question it has none of: its partner line is the ANSWER, and showing it would hand over what the learner is to say.
it('builds every speak_answer out of ONE exchange, and gives an ask no question at all', function () {
    $scene = s1spScene();
    $stage = new SpeakStage;
    $x7 = $stage->speakAnswer($scene, $scene->exchange(7))->payload;
    $x8 = $stage->speakAnswer($scene, $scene->exchange(8))->payload;

    expect($x7['exchange'])->toBe(['ref' => 'x7', 'step' => 7, 'kind' => 'ask'])
        ->and($x7['partner_line'])->toBeNull()
        ->and($x7['own_line']['ref'])->toBe('x7b')
        ->and($x7['own_line']['text_target'])->toBe('Do we need an X-ray?')
        ->and($x7['own_line']['filler_index'])->toBe(0)
        ->and($x7['task_native'])->toBe($x7['own_line']['text_native'])
        ->and($x7['frame']['kind'])->toBe('ask')
        ->and($x8['partner_line'])->toBeNull()
        ->and($x8['own_line']['filler_index'])->toBe(1);

    // Every card of the day: the question, the expected line and the frame are the exchange `payload.exchange` names.
    foreach ($stage->build($scene) as $draft) {
        if ($draft->kind !== CardKind::SpeakAnswer) {
            continue;
        }
        $step = $draft->payload['exchange']['step'];
        $partner = $scene->exchange($step)?->partner();
        expect($draft->unitRef)->toBe($draft->payload['exchange']['ref'])
            ->and($draft->payload['own_line']['ref'])->toBe(SpokenLines::learnerRef($step))
            ->and($draft->payload['partner_line']['ref'] ?? null)
            ->toBe($draft->payload['exchange']['kind'] === 'ask' ? null : SpokenLines::partnerRef($step))
            ->and($draft->payload['partner_line']['text_target'] ?? null)
            ->toBe($draft->payload['exchange']['kind'] === 'ask' ? null : $partner->textTarget);
    }
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
            'speech_mode' => 'repeat',
            'pause_ms' => 3000,
        ])
        // «Повтори свою реплику»: the learner's own line, its coverage counted by the client — no judge, no reveal of
        // somebody else's line (наряд BACK-TAILS-1 §1.1).
        ->and($drafts[7]->kind)->toBe(CardKind::SpeakRetell)
        ->and($drafts[7]->payload)->toBe([
            'scene_id' => s1spSceneId()->value,
            'exchange' => ['ref' => 'x8', 'step' => 8, 'kind' => 'ask'],
            'own_line' => [
                'ref' => 'x8b',
                'text_target' => 'Do we need a follow-up appointment?',
                'text_native' => 'Нам нужно прийти на повторный приём?',
                'frame_ref' => 'p6',
                'filler_index' => 1,
                'key' => $drafts[7]->payload['own_line']['key'],
                'audio' => Audio::of('x8b'),
            ],
            'expected_text' => 'Do we need a follow-up appointment?',
            'speech_mode' => 'repeat',
        ])
        ->and($drafts[7]->payload['own_line']['key'])->not->toBeNull();
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

/**
 * SNESENO (наряд CONV-1): «Репетиция: speak_answer по всем сценам плана, ≤ 12» is gone — the day before the event is
 * «Вспомнить» ({@see RecallStage}) and the talk with the agent. The two tests that held the old selection went with
 * the method they tested; what replaced them lives in `RecallStageTest` and in the rehearsal case of
 * `SessionDayAssemblyTest`.
 */
