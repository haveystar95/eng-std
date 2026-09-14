<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Assembly\DayAssembler;
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
 * THE DAY, DEALT FROM A SERVED `lesson_day.v4.4` LESSON (docs/plan-v2.md §6): the five stages and their cards
 * per level, the learner's own line (the frame said with its filler) as what is chosen, assembled and said,
 * the check as the comprehension card, returns, review and rehearsal, and the same deal twice.
 */

function daMaterial(PlanLevel $level = PlanLevel::Beginner, ?PlanSceneId $sceneId = null, ?Closure $edit = null): SceneMaterial
{
    $sceneId ??= PlanSceneId::generate();
    $payload = FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', $level, null, 8, 8));
    if ($edit !== null) {
        $payload = $edit($payload);
    }
    $lesson = LessonAssembly::serve((new LessonParser)->parse($payload), $sceneId->value);

    return new SceneMaterial($sceneId, $lesson, PlanTerm::fromLesson($sceneId, $lesson, static fn (): PlanTermId => PlanTermId::generate()));
}

/** @return list<DayCard> */
function daDeal(SceneMaterial $scene, PlanLevel $level, array $returned = []): array
{
    return (new DayAssembler)->sceneDay(PlanDayId::generate(), $scene, [$scene->sceneId->value => $scene], $level, $returned, [], static fn (): DayCardId => DayCardId::generate());
}

/** @return list<DayCard> */
function daIn(array $cards, Stage $stage): array
{
    return array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === $stage));
}

it('deals a day in five stages: four cards a word spaced out, three a phrase, one dialogue, two listening and one speaking card an exchange', function () {
    $cards = daDeal(daMaterial(), PlanLevel::Beginner);

    expect(array_map(static fn (DayCard $c): string => $c->kind()->value, array_slice(daIn($cards, Stage::Words), 0, 6)))
        ->toBe(['word_intro', 'word_intro', 'word_say', 'word_intro', 'word_say', 'word_choose'])
        // 8 words × 4, less the cloze of «sick note»: a filler the dialogue never says has no line to blank out.
        ->and(count(daIn($cards, Stage::Words)))->toBe(31)
        ->and(array_filter(daIn($cards, Stage::Words), static fn (DayCard $c): bool => $c->kind() === CardKind::WordCloze && $c->unitRef() === 'v8'))->toBe([])
        ->and(count(daIn($cards, Stage::Phrases)))->toBe(18)
        ->and(array_map(static fn (DayCard $c): string => $c->kind()->value, daIn($cards, Stage::Dialogue)))->toBe(['dialogue_read'])
        ->and(count(daIn($cards, Stage::Listen)))->toBe(16)
        ->and(count(daIn($cards, Stage::Speak)))->toBe(8);
    foreach (Stage::ordered() as $stage) {
        expect(array_map(static fn (DayCard $c): int => $c->position(), daIn($cards, $stage)))->toBe(range(1, count(daIn($cards, $stage))));
    }
});

// Canon: the phrase cards teach the frame said with the dialogue's filler — the words the learner will say in it.
it('teaches a phrase as its frame said with the dialogue filler, and assembles it from those words', function () {
    $phrases = daIn(daDeal(daMaterial(), PlanLevel::Beginner), Stage::Phrases);
    $assemble = array_values(array_filter($phrases, static fn (DayCard $c): bool => $c->kind() === CardKind::PhraseAssemble && $c->unitRef() === 'p6'))[0];

    expect($assemble->payload()['answer'])->toBe('Do we need an X-ray?')
        ->and($assemble->payload()['prompt_native'])->toBe('Нам нужно сделать рентген?')
        ->and(count($assemble->payload()['tiles']))->toBe(count(explode(' ', 'Do we need an X-ray')) + 1);
});

// Canon: «Говорю сам» — the task is the learner's native line, the expected text is the served line, the hints its key and text.
it('asks the learner to say their own served line, with the key and the text as hints', function () {
    $speak = daIn(daDeal(daMaterial(), PlanLevel::Beginner), Stage::Speak);

    expect($speak[4]->payload()['expected'])->toBe('Okay, he will rest at home.')
        ->and($speak[4]->payload()['task_native'])->toBe('Хорошо, он будет отдыхать дома.')
        ->and($speak[4]->payload()['hints'])->toBe(['key' => 'will rest', 'text' => 'Okay, he will rest at home.'])
        ->and($speak[4]->payload()['coverage'])->toBe(0.7);
});

it('asks the check of an exchange in the learner\'s language to a Beginner and in the target language to an Intermediate, the right option marked', function () {
    $beginner = daIn(daDeal(daMaterial(), PlanLevel::Beginner), Stage::Listen)[0];
    $intermediate = array_values(array_filter(
        daIn(daDeal(daMaterial(PlanLevel::Intermediate), PlanLevel::Intermediate), Stage::Listen),
        static fn (DayCard $c): bool => $c->kind() === CardKind::ListenQuestion,
    ))[0];

    expect($beginner->kind())->toBe(CardKind::ListenQuestion)
        ->and($beginner->payload()['language'])->toBe('native')
        ->and($beginner->payload()['question'])->toBe('О каких двух местах спрашивает врач?')
        ->and(array_column(array_filter($beginner->payload()['options'], static fn (array $o): bool => $o['correct']), 'text'))->toBe(['Верх или низ спины'])
        ->and($intermediate->payload()['language'])->toBe('target');
});

it('deals the reply card by who opened the exchange: choose when the partner did, assemble when the learner did', function () {
    $listen = daIn(daDeal(daMaterial(), PlanLevel::Beginner), Stage::Listen);
    $replies = [];
    foreach ($listen as $card) {
        if (in_array($card->kind(), [CardKind::AnswerChoose, CardKind::AnswerAssemble], true)) {
            $replies[$card->payload()['exchange_step']] = $card;
        }
    }

    expect($replies[1]->kind())->toBe(CardKind::AnswerChoose)
        ->and(count(array_filter($replies[1]->payload()['options'], static fn (array $o): bool => $o['correct'])))->toBe(1)
        ->and($replies[7]->kind())->toBe(CardKind::AnswerAssemble)
        ->and($replies[7]->payload()['answer'])->toBe('Do we need an X-ray?');
});

it('leaves an exchange with one message out of listening and speaking, and keeps it in the dialogue', function () {
    $scene = daMaterial(edit: static function (array $p): array {
        $p['dialogue'][2]['messages'] = [$p['dialogue'][2]['messages'][0]];

        return $p;
    });
    $cards = daDeal($scene, PlanLevel::Beginner);

    expect(count(daIn($cards, Stage::Listen)))->toBe(14)
        ->and(count(daIn($cards, Stage::Speak)))->toBe(7)
        ->and(count(daIn($cards, Stage::Dialogue)[0]->payload()['exchanges']))->toBe(8);
});

it('deals a returned unit as one card at the end of its stage, marked returned', function () {
    $sceneId = PlanSceneId::generate();
    $scene = daMaterial(sceneId: $sceneId);
    $yesterday = PlanDayId::generate();
    $cards = daDeal($scene, PlanLevel::Beginner, [
        new ReturnedUnit($sceneId, UnitKind::Word, 'v3', $yesterday),
        new ReturnedUnit($sceneId, UnitKind::Phrase, 'p2', $yesterday),
        new ReturnedUnit($sceneId, UnitKind::Exchange, 'x4', $yesterday),
        new ReturnedUnit($sceneId, UnitKind::Word, 'v3', $yesterday),
        new ReturnedUnit($sceneId, UnitKind::Word, 'v99', $yesterday),
    ]);
    $back = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->source() === CardSource::Returned));

    expect(array_map(static fn (DayCard $c): string => $c->kind()->value, $back))->toBe(['word_choose', 'phrase_assemble', 'speak'])
        ->and($back[0]->sourceDayId()?->equals($yesterday))->toBeTrue()
        ->and($back[0]->position())->toBe(count(daIn($cards, Stage::Words)));
});

it('deals a review day from the returns and every exchange of two scenes, and a rehearsal from every scene said aloud', function () {
    $a = daMaterial();
    $b = daMaterial();
    $material = [$a->sceneId->value => $a, $b->sceneId->value => $b];

    $review = (new DayAssembler)->reviewDay(PlanDayId::generate(), [$a, $b], $material, PlanLevel::Beginner, [new ReturnedUnit($a->sceneId, UnitKind::Word, 'v1', PlanDayId::generate())], [], static fn (): DayCardId => DayCardId::generate());
    $rehearsal = (new DayAssembler)->rehearsalDay(PlanDayId::generate(), [$a, $b], static fn (): DayCardId => DayCardId::generate());

    expect(array_map(static fn (DayCard $c): string => $c->kind()->value, daIn($review, Stage::Words)))->toBe(['word_choose'])
        ->and(count(daIn($review, Stage::Speak)))->toBe(16)
        ->and(count($rehearsal))->toBe(16)
        ->and(array_values(array_unique(array_map(static fn (DayCard $c): string => $c->stage()->value, $rehearsal))))->toBe(['speak']);
});

it('deals the same day twice: tiles and options shuffled by the card\'s address, not by chance', function () {
    $sceneId = PlanSceneId::generate();
    $strip = static fn (array $cards): array => array_map(static function (DayCard $c): array {
        $p = $c->payload();
        unset($p['plan_term_id']);

        return [$c->kind()->value, $c->position(), $p];
    }, $cards);

    expect($strip(daDeal(daMaterial(sceneId: $sceneId), PlanLevel::Beginner)))->toBe($strip(daDeal(daMaterial(sceneId: $sceneId), PlanLevel::Beginner)));
});

it('computes the day metrics from the cards: dealt, done, minutes without the long pauses', function () {
    $cards = daDeal(daMaterial(), PlanLevel::Beginner);
    $t = new DateTimeImmutable('2026-09-15T10:00:00Z');
    foreach (array_slice($cards, 0, 20) as $i => $card) {
        $card->answer(CardResult::Passed, 1, $t->modify('+'.($i * 30 + ($i >= 10 ? 3600 : 0)).' seconds'));
    }

    $metrics = (new DayMetricsCalculator)->calculate($cards);

    expect($metrics->cardsTotal)->toBe(count($cards))
        ->and($metrics->cardsDone)->toBe(20)
        ->and($metrics->minutesSpent)->toBe(9);
});
