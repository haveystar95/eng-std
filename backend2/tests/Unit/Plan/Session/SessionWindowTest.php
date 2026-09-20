<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Service\DayMetricsCalculator;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\Service\DayWindowStages;
use App\Modules\Plan\Domain\Service\ReturnDay;
use App\Modules\Plan\Domain\Service\UnitStates;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\ProgramSummary;
use App\Modules\Plan\Domain\ValueObject\StageState;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Domain\ValueObject\UnitState;
use App\Modules\Plan\Domain\ValueObject\WindowStage;
use App\Modules\Plan\Domain\ValueObject\WindowStatus;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * THE DAY WINDOW OVER THE REGISTRY (наряд SESSION-1a, разд. 2–3; D-05): the day's listening is no unit of the programme,
 * the summaries count what is left, the minutes are the kinds' seconds, the day's numbers are folded over any kind, and
 * a unit failed twice names the day it comes back on.
 */

/** A card of the fixed scene; `answered` passes it, `failedTwice` fails it and its copy. */
function s1wCard(CardKind $kind, UnitKind $unit, string $ref, bool $answered = false, bool $failedTwice = false, ?DateTimeImmutable $at = null): DayCard
{
    $card = DayCard::dealt(
        DayCardId::generate(), PlanDayId::generate(), $kind->stage(), 1, $kind, ['scene_id' => 'S1'], CardSource::Today, null, $unit, $ref,
    );
    $at ??= new DateTimeImmutable('2026-09-16T10:00:00Z');
    if ($failedTwice) {
        $card->answer(CardResult::Failed, 1, null, $at);
        $copy = $card->retry(DayCardId::generate(), 2, $card->payload());
        $copy->answer(CardResult::Failed, 1, null, $at);

        return $copy;
    }
    if ($answered) {
        $card->answer(CardResult::Passed, 1, null, $at);
    }

    return $card;
}

/** @param list<string> $keys */
function s1wKeysOf(array $keys, string $unit): array
{
    return array_values(array_filter($keys, static fn (string $key): bool => str_contains($key, ':'.$unit.':')));
}

// Catches a programme that counts the day's listening as a unit: «слушаю» would sit among the words as «не пройдено».
it('leaves the day’s listening out of the units — not pending, not walked, not returning — and counts the rest', function () {
    $cards = [
        s1wCard(CardKind::WordIntro, UnitKind::Word, 'v1', answered: true),
        s1wCard(CardKind::WordChoose, UnitKind::Word, 'v1', answered: true),
        s1wCard(CardKind::WordChoose, UnitKind::Word, 'v2', failedTwice: true),
        s1wCard(CardKind::PhraseIntro, UnitKind::Phrase, 'p1'),
        s1wCard(CardKind::DialoguePartner, UnitKind::Exchange, 'x1', answered: true),
        s1wCard(CardKind::ListenDialogue, UnitKind::Day, 'day'),
        s1wCard(CardKind::ListenQuestion, UnitKind::Day, 'L1', failedTwice: true),
        s1wCard(CardKind::ListenPace, UnitKind::Day, 'day', answered: true),
    ];
    $states = UnitStates::of($cards);

    expect(s1wKeysOf(array_keys($states), 'day'))->toBe([])
        ->and($states)->toHaveCount(4)
        ->and($states[UnitStates::key('S1', UnitKind::Word, 'v1')])->toBe(UnitState::Done)
        ->and($states[UnitStates::key('S1', UnitKind::Word, 'v2')])->toBe(UnitState::ReturnsTomorrow)
        ->and($states[UnitStates::key('S1', UnitKind::Phrase, 'p1')])->toBe(UnitState::Pending)
        ->and($states[UnitStates::key('S1', UnitKind::Exchange, 'x1')])->toBe(UnitState::Done)
        ->and(ProgramSummary::of(array_values($states)))->toEqual(new ProgramSummary(4, 2, 1))
        // The listening failed twice returns nothing: its copy is failed, the unit is not marked.
        ->and($cards[6]->result())->toBe(CardResult::Failed)
        ->and($cards[6]->returns())->toBeFalse();
});

// Catches a brow that counts a returning unit as walked, or a pending one as returning.
it('summarises a tab by its states: total, walked, coming back', function () {
    expect(ProgramSummary::of([UnitState::Done, UnitState::Pending, UnitState::ReturnsTomorrow, UnitState::Done, UnitState::ReturnsTomorrow]))
        ->toEqual(new ProgramSummary(5, 2, 2))
        ->and(ProgramSummary::of([]))->toEqual(new ProgramSummary(0, 0, 0));
});

// Canon (разд. 2): «DayPace — секунды на карточку по ВИДУ». Catches a pace by stage (one rate for a tap and a line said
// aloud), minutes rounded down, and answered cards still counted as left.
it('prices what is left by kind: two word intros and an assembly are 8 + 8 + 20 seconds, answered cards cost nothing', function () {
    $pace = new DayPace;
    $words = [
        s1wCard(CardKind::WordIntro, UnitKind::Word, 'v1'),
        s1wCard(CardKind::WordIntro, UnitKind::Word, 'v2'),
        s1wCard(CardKind::WordAssemble, UnitKind::Word, 'v3'),
        s1wCard(CardKind::WordChoose, UnitKind::Word, 'v4', answered: true),
    ];
    $listen = [
        s1wCard(CardKind::ListenDialogue, UnitKind::Day, 'day'),
        s1wCard(CardKind::ListenQuestion, UnitKind::Day, 'L1'),
        s1wCard(CardKind::ListenQuestion, UnitKind::Day, 'L2'),
        s1wCard(CardKind::ListenReview, UnitKind::Day, 'day'),
    ];
    $rows = DayWindowStages::of([...$words, ...$listen], [], WindowStatus::InProgress, $pace);
    $unanswered = array_filter($words, static fn (DayCard $c): bool => ! $c->isAnswered());

    expect($pace->secondsOf($unanswered))->toBe(8 + 8 + 20)
        ->and(array_map(static fn (WindowStage $s): string => $s->state->value, $rows))->toBe([StageState::Current->value, StageState::Locked->value])
        ->and($rows[0]->minutesLeft)->toBe(DayPace::minutes(8 + 8 + 20))
        ->and($rows[0]->doneCount)->toBe(1)
        ->and($rows[0]->total)->toBe(4)
        // The whole visit played once is 110 seconds on its own: 110 + 12 + 12 + 30 = 164 s → 3 minutes, not a stage rate.
        ->and(DayWindowStages::minutesEstimate([...$words, ...$listen], WindowStatus::NotStarted, $pace))->toBe(DayPace::minutes(8 + 8 + 20 + 10 + 110 + 12 + 12 + 30))
        ->and(DayWindowStages::minutesEstimate($listen, WindowStatus::InProgress, $pace))->toBe(3)
        ->and(DayPace::minutes(36))->toBe(1)
        ->and(DayPace::minutes(61))->toBe(2)
        ->and(DayPace::minutes(0))->toBe(0);
});

// Canon (разд. 2): «в config/plan.php (начальные значения, подкрутим после телефона)». Catches a table fixed in code.
it('takes its seconds from the table it is given, and a kind the table does not name costs nothing', function () {
    $pace = new DayPace(['word_intro' => 60, 'speak_answer' => 45]);

    expect($pace->seconds(CardKind::WordIntro))->toBe(60)
        ->and($pace->seconds(CardKind::SpeakAnswer))->toBe(45)
        ->and($pace->seconds(CardKind::ListenDialogue))->toBe(0)
        ->and((new DayPace)->seconds(CardKind::ListenDialogue))->toBe(110)
        ->and((new DayPace)->seconds(CardKind::ListenPairs))->toBe(0);
});

// Moved from the old assembly test (its day is gone): the day's numbers are the cards', whatever their kind.
it('computes the day metrics from cards of any kind: dealt, done, minutes without the long pauses', function () {
    $kinds = [
        [CardKind::WordIntro, UnitKind::Word, 'v1'], [CardKind::WordRepeat, UnitKind::Word, 'v1'], [CardKind::WordChoose, UnitKind::Word, 'v1'],
        [CardKind::PhraseOtherSlot, UnitKind::Phrase, 'p1'], [CardKind::DialogueRescue, UnitKind::Exchange, 'x2'],
        [CardKind::ListenDialogue, UnitKind::Day, 'day'], [CardKind::ListenQuestion, UnitKind::Day, 'L1'], [CardKind::SpeakRetell, UnitKind::Exchange, 'x3'],
    ];
    $t = new DateTimeImmutable('2026-09-15T10:00:00Z');
    $cards = [];
    for ($i = 0; $i < 24; $i++) {
        [$kind, $unit, $ref] = $kinds[$i % count($kinds)];
        $cards[] = s1wCard($kind, $unit, $ref);
    }
    foreach (array_slice($cards, 0, 20) as $i => $card) {
        $card->answer(CardResult::Skipped, 1, null, $t->modify('+'.($i * 30 + ($i >= 10 ? 3600 : 0)).' seconds'));
    }

    $metrics = (new DayMetricsCalculator)->calculate($cards);

    expect($metrics->cardsTotal)->toBe(24)
        ->and($metrics->cardsDone)->toBe(20)
        ->and($metrics->minutesSpent)->toBe(9);
});

// Canon (DAY-UI-3; разд. 3): «вернётся в день N». Catches a return named on the rehearsal (it deals no returns) and a
// day after the last one.
// Canon (SESSION-1a, хвост): a unit comes back once, on the nearest following day of whatever type — the rehearsal too.
it('names the next day a unit comes back on — any type, the rehearsal too, none after the last day', function () {
    $plan = Plan::create(
        PlanId::generate(), UserId::generate(), 'Иду к врачу', new LanguageCode('en'), new LanguageCode('ru'), PlanLevel::Beginner,
        5, null, new DateTimeImmutable('2026-09-16'), new DateTimeImmutable('2026-09-16T10:00:00Z'), static fn (): PlanDayId => PlanDayId::generate(),
    );

    expect(array_map(static fn ($d): DayType => $d->type(), $plan->days()))->toBe([DayType::Scene, DayType::Scene, DayType::Review, DayType::Scene, DayType::Rehearsal])
        ->and(ReturnDay::of($plan, $plan->day(1)))->toBe(2)
        ->and(ReturnDay::of($plan, $plan->day(2)))->toBe(3)
        ->and(ReturnDay::of($plan, $plan->day(3)))->toBe(4)
        ->and(ReturnDay::of($plan, $plan->day(4)))->toBe(5)
        ->and(ReturnDay::of($plan, $plan->day(5)))->toBeNull();
});
