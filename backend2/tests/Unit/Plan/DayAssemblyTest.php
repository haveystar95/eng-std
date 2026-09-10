<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Assembly\DayAssembler;
use App\Modules\Plan\Domain\Assembly\ReturnedUnit;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\PlanTerm;
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
 * THE DAY, DEALT (docs/plan-v2.md §6): the composition per level, the order, «heard → assemble»
 * only on Intermediate and only for statements of at most ten words, and tolerance of every
 * broken lesson the checks may have let through in observe.
 */
function asmPayload(): array
{
    return FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Beginner, 6, 8, 8));
}

function asmMaterial(array $payload, ?PlanSceneId $sceneId = null): SceneMaterial
{
    $sceneId ??= PlanSceneId::generate();
    $lesson = (new LessonParser)->parse($payload);

    return new SceneMaterial($sceneId, $lesson, PlanTerm::fromLesson($sceneId, $lesson, static fn (): PlanTermId => PlanTermId::generate()));
}

/** @return list<DayCard> */
function asmDeal(array $payload, PlanLevel $level, array $returned = [], array $extra = []): array
{
    $scene = asmMaterial($payload);

    return (new DayAssembler)->sceneDay(PlanDayId::generate(), $scene, [$scene->sceneId->value => $scene], $level, $returned, $extra, static fn (): DayCardId => DayCardId::generate());
}

/** @return list<string> */
function asmKindsIn(array $cards, Stage $stage): array
{
    return array_values(array_map(
        static fn (DayCard $c): string => $c->kind()->value,
        array_filter($cards, static fn (DayCard $c): bool => $c->stage() === $stage),
    ));
}

it('deals a Beginner day: four word cards spaced, three phrase cards, one dialogue, two listen cards and one speak per exchange', function () {
    $cards = asmDeal(asmPayload(), PlanLevel::Beginner);

    $words = asmKindsIn($cards, Stage::Words);
    // Spacing: word 1 met, word 2 met, word 1 said, word 3 met, word 2 said, word 1 chosen…
    expect(array_slice($words, 0, 6))->toBe(['word_intro', 'word_intro', 'word_say', 'word_intro', 'word_say', 'word_choose'])
        ->and(count(array_filter($words, static fn (string $k): bool => $k === 'word_intro')))->toBe(8)
        ->and(count(array_filter($words, static fn (string $k): bool => $k === 'word_choose')))->toBe(8);

    $phrases = asmKindsIn($cards, Stage::Phrases);
    expect(array_slice($phrases, 0, 3))->toBe(['phrase_intro', 'phrase_intro', 'phrase_repeat'])
        ->and(count($phrases))->toBe(18);

    expect(asmKindsIn($cards, Stage::Dialogue))->toBe(['dialogue_read'])
        ->and(count(asmKindsIn($cards, Stage::Listen)))->toBe(16)
        ->and(count(asmKindsIn($cards, Stage::Speak)))->toBe(8);

    // Positions run 1..n within every stage, in walking order.
    foreach (Stage::ordered() as $stage) {
        $positions = array_map(static fn (DayCard $c): int => $c->position(), array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === $stage)));
        expect($positions)->toBe(range(1, count($positions)));
    }
});

it('asks a Beginner to choose the translation with decoys from the day, an Intermediate to choose the word by its definition', function () {
    $beginner = asmDeal(asmPayload(), PlanLevel::Beginner);
    $choose = array_values(array_filter($beginner, static fn (DayCard $c): bool => $c->kind() === CardKind::WordChoose))[0];
    expect($choose->payload()['mode'])->toBe('translation')
        ->and($choose->payload()['prompt'])->toBe('lower back')
        ->and(count($choose->payload()['options']))->toBe(4)
        ->and(count(array_filter($choose->payload()['options'], static fn (array $o): bool => $o['correct'])))->toBe(1);

    $intermediate = asmDeal(asmPayload(), PlanLevel::Intermediate);
    $choose = array_values(array_filter($intermediate, static fn (DayCard $c): bool => $c->kind() === CardKind::WordChoose))[0];
    expect($choose->payload()['mode'])->toBe('definition')
        ->and($choose->payload()['prompt'])->toBe('the part of the back above the hips');
});

it('folds the translations of the dialogue for an Intermediate only', function () {
    $b = array_values(array_filter(asmDeal(asmPayload(), PlanLevel::Beginner), static fn (DayCard $c): bool => $c->kind() === CardKind::DialogueRead))[0];
    $i = array_values(array_filter(asmDeal(asmPayload(), PlanLevel::Intermediate), static fn (DayCard $c): bool => $c->kind() === CardKind::DialogueRead))[0];

    expect($b->payload()['translations_collapsed'])->toBeFalse()
        ->and($i->payload()['translations_collapsed'])->toBeTrue()
        ->and(count($b->payload()['exchanges']))->toBe(8);
});

it('deals «heard → assemble» only to an Intermediate, and only for a partner statement of at most ten words', function () {
    $p = asmPayload();
    // Exchange 1: the partner asks (11 words). Exchange 5: a statement of 10 words. Exchange 6: an 8-word statement, made 12.
    $p['dialogue'][5]['messages'][0]['text_target'] = 'Give him a painkiller twice a day after meals for two weeks.';

    $beginner = asmDeal($p, PlanLevel::Beginner);
    $listenB = array_values(array_filter($beginner, static fn (DayCard $c): bool => $c->stage() === Stage::Listen));
    expect(array_unique(array_map(static fn (DayCard $c): string => $c->kind()->value, array_filter($listenB, static fn (DayCard $c): bool => $c->position() % 2 === 1))))
        ->toBe(['listen_question']);
    // A Beginner's question is asked in the learner's own language.
    expect($listenB[0]->payload()['language'])->toBe('native');

    $intermediate = asmDeal($p, PlanLevel::Intermediate);
    $listenI = array_values(array_filter($intermediate, static fn (DayCard $c): bool => $c->stage() === Stage::Listen));
    $byStep = [];
    foreach ($listenI as $card) {
        if ($card->position() % 2 === 1) {
            $byStep[$card->payload()['exchange_step']] = $card->kind()->value;
        }
    }
    expect($byStep[1])->toBe('listen_question')   // A asked
        ->and($byStep[5])->toBe('listen_assemble') // A stated, ten words
        ->and($byStep[6])->toBe('listen_question') // A stated, twelve words
        ->and($listenI[0]->payload()['language'])->toBe('target');
});

it('deals the reply card by who started: choose among three when the partner did, assemble when the learner did', function () {
    $cards = asmDeal(asmPayload(), PlanLevel::Beginner);
    $replies = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === Stage::Listen && $c->position() % 2 === 0));
    $byStep = [];
    foreach ($replies as $card) {
        $byStep[$card->payload()['exchange_step']] = $card;
    }

    expect($byStep[1]->kind())->toBe(CardKind::AnswerChoose)
        ->and(count($byStep[1]->payload()['options']))->toBe(3)
        ->and($byStep[7]->kind())->toBe(CardKind::AnswerAssemble)
        ->and($byStep[7]->payload()['tiles'])->toHaveCount(count(explode(' ', 'Should he avoid sports for now?')) + 1);
});

it('gives every speak card the learner line as the task, the key and the text as hints, and the pass rule', function () {
    $cards = asmDeal(asmPayload(), PlanLevel::Beginner);
    $speak = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->kind() === CardKind::Speak))[0];

    expect($speak->payload()['task_native'])->toBe('Болит в пояснице.')
        ->and($speak->payload()['expected'])->toBe('It hurts in his lower back.')
        ->and($speak->payload()['speaking_key'])->toBe('lower back')
        ->and($speak->payload()['coverage'])->toBe(0.7)
        ->and($speak->payload()['hints'])->toBe(['key' => 'lower back', 'text' => 'It hurts in his lower back.']);
});

dataset('tolerated lessons', [
    'speaking key not in the line → speak card without underline' => static function (array $p): array {
        $p['dialogue'][0]['messages'][1]['speaking_key'] = 'describe main pain';

        return $p;
    },
    'reading in a foreign script → shown as is' => static function (array $p): array {
        $p['vocabulary'][0]['pronunciation_native'] = 'lower back';

        return $p;
    },
    'second message asks → an ordinary exchange' => static function (array $p): array {
        $p['dialogue'][0]['messages'][1]['text_target'] = 'Does it hurt a lot?';

        return $p;
    },
    'phrase in no line → only in the phrases stage' => static function (array $p): array {
        $p['phrases'][0]['text_target'] = 'Could you write it down, please?';

        return $p;
    },
    'word id on a line without the word → no highlight' => static function (array $p): array {
        $p['dialogue'][1]['messages'][0]['vocabulary_ids'] = ['v4'];

        return $p;
    },
    'three messages in an exchange' => static function (array $p): array {
        $p['dialogue'][0]['messages'][] = $p['dialogue'][0]['messages'][1];

        return $p;
    },
    'a step number twice' => static function (array $p): array {
        $p['dialogue'][3]['step'] = 3;

        return $p;
    },
    'one message in an exchange' => static function (array $p): array {
        $p['dialogue'][2]['messages'] = [$p['dialogue'][2]['messages'][0]];

        return $p;
    },
    'variant longer than the line' => static function (array $p): array {
        $p['dialogue'][0]['messages'][1]['simplified_variants'] = ['It hurts in his lower back a lot now.'];

        return $p;
    },
    'word contained in another' => static function (array $p): array {
        $p['vocabulary'][1]['term_target'] = 'back';

        return $p;
    },
    'one phrase short' => static function (array $p): array {
        array_pop($p['phrases']);

        return $p;
    },
    'no vocabulary at all' => static function (array $p): array {
        $p['vocabulary'] = [];

        return $p;
    },
    'no dialogue at all' => static function (array $p): array {
        $p['dialogue'] = [];

        return $p;
    },
]);

it('deals a day from every broken lesson the checks may keep', function (Closure $break) {
    foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
        $cards = asmDeal($break(asmPayload()), $level);

        expect($cards)->not->toBeEmpty();
        foreach ($cards as $card) {
            expect($card->payload())->toHaveKey('scene_id');
        }
    }
})->with('tolerated lessons');

it('leaves an exchange with one message out of the listening and speaking stages', function () {
    $p = asmPayload();
    $p['dialogue'][2]['messages'] = [$p['dialogue'][2]['messages'][0]];

    $cards = asmDeal($p, PlanLevel::Beginner);

    expect(count(asmKindsIn($cards, Stage::Listen)))->toBe(14)
        ->and(count(asmKindsIn($cards, Stage::Speak)))->toBe(7)
        // The dialogue card still shows the whole visit as written.
        ->and(count(array_values(array_filter($cards, static fn (DayCard $c): bool => $c->kind() === CardKind::DialogueRead))[0]->payload()['exchanges']))->toBe(8);
});

it('deals a returned unit as one card at the end of its stage, marked returned', function () {
    $sceneId = PlanSceneId::generate();
    $scene = asmMaterial(asmPayload(), $sceneId);
    $yesterday = PlanDayId::generate();
    $returned = [
        new ReturnedUnit($sceneId, UnitKind::Word, 'v3', $yesterday),
        new ReturnedUnit($sceneId, UnitKind::Phrase, 'p2', $yesterday),
        new ReturnedUnit($sceneId, UnitKind::Exchange, 'x4', $yesterday),
        new ReturnedUnit($sceneId, UnitKind::Word, 'v3', $yesterday), // the same unit twice comes once
        new ReturnedUnit($sceneId, UnitKind::Word, 'v99', $yesterday), // a unit that is no longer there is skipped
    ];

    $cards = (new DayAssembler)->sceneDay(PlanDayId::generate(), $scene, [$sceneId->value => $scene], PlanLevel::Beginner, $returned, [], static fn (): DayCardId => DayCardId::generate());
    $back = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->source() === CardSource::Returned));

    expect(array_map(static fn (DayCard $c): string => $c->kind()->value, $back))->toBe(['word_choose', 'phrase_assemble', 'speak'])
        ->and($back[0]->sourceDayId()?->equals($yesterday))->toBeTrue()
        ->and($back[0]->position())->toBe(count(asmKindsIn($cards, Stage::Words)));
});

it('deals a review day from the returns and every exchange of the two scenes, and a rehearsal from every scene', function () {
    $a = asmMaterial(asmPayload());
    $b = asmMaterial(asmPayload());
    $material = [$a->sceneId->value => $a, $b->sceneId->value => $b];
    $returned = [new ReturnedUnit($a->sceneId, UnitKind::Word, 'v1', PlanDayId::generate())];

    $review = (new DayAssembler)->reviewDay(PlanDayId::generate(), [$a, $b], $material, PlanLevel::Beginner, $returned, [], static fn (): DayCardId => DayCardId::generate());
    expect(asmKindsIn($review, Stage::Words))->toBe(['word_choose'])
        ->and(count(asmKindsIn($review, Stage::Speak)))->toBe(16)
        ->and(asmKindsIn($review, Stage::Listen))->toBe([]);

    $rehearsal = (new DayAssembler)->rehearsalDay(PlanDayId::generate(), [$a, $b], static fn (): DayCardId => DayCardId::generate());
    expect(count($rehearsal))->toBe(16)
        ->and(array_unique(array_map(static fn (DayCard $c): string => $c->stage()->value, $rehearsal)))->toBe(['speak']);
});

it('deals the same day twice: tiles and options are shuffled by the card address, not by chance', function () {
    $sceneId = PlanSceneId::generate();
    $one = asmMaterial(asmPayload(), $sceneId);
    $two = asmMaterial(asmPayload(), $sceneId);
    $strip = static fn (array $cards): array => array_map(static function (DayCard $c): array {
        $p = $c->payload();
        unset($p['plan_term_id']);

        return [$c->kind()->value, $c->position(), $p];
    }, $cards);

    $first = (new DayAssembler)->sceneDay(PlanDayId::generate(), $one, [$sceneId->value => $one], PlanLevel::Beginner, [], [], static fn (): DayCardId => DayCardId::generate());
    $second = (new DayAssembler)->sceneDay(PlanDayId::generate(), $two, [$sceneId->value => $two], PlanLevel::Beginner, [], [], static fn (): DayCardId => DayCardId::generate());

    expect($strip($first))->toBe($strip($second));
});

it('computes the day metrics from the cards: done, minutes, first-try share and the hardest unit', function () {
    $cards = asmDeal(asmPayload(), PlanLevel::Beginner);
    $t = new DateTimeImmutable('2026-09-10T10:00:00Z');
    $graded = 0;
    foreach ($cards as $i => $card) {
        if ($i >= 20) {
            break;
        }
        $graded += $card->isGraded() ? 1 : 0;
        $attempts = $card->unitRef() === 'v2' && $card->isGraded() ? 3 : 1;
        $card->answer($attempts > 1 ? CardResult::Failed : CardResult::Passed, $attempts, $t->modify('+'.($i * 30).' seconds'));
    }

    $metrics = (new DayMetricsCalculator)->calculate($cards, static fn (DayCard $c): ?string => $c->unitRef());

    expect($metrics->cardsTotal)->toBe(count($cards))
        ->and($metrics->cardsDone)->toBe(20)
        ->and($metrics->minutesSpent)->toBe(10)
        ->and($metrics->hardestUnitRef)->toBe('v2')
        ->and($metrics->firstTryShare)->toBeLessThan(1.0)
        ->and($metrics->firstTryShare)->toBeGreaterThan(0.0);
});
