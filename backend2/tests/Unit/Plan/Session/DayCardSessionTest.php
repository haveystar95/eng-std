<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Exception\CardAlreadyAnswered;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * A CARD'S ANSWER, ITS JUDGE AND ITS COPY (наряд SESSION-1a, разд. 3; D-06): only a choice has consequences — the first
 * failure comes back at the end of the stage, the copy's failure marks the unit to return, unless the unit is the day's
 * listening; a judged card is answered by an accepted verdict; the copy carries a new payload.
 */

function s1Card(CardKind $kind, UnitKind $unit = UnitKind::Word, string $ref = 'v1'): DayCard
{
    return DayCard::dealt(
        DayCardId::generate(), PlanDayId::generate(), $kind->stage(), 3, $kind,
        ['scene_id' => 'scene', 'options' => [['id' => 'o1', 'text' => 'a'], ['id' => 'o2', 'text' => 'b']], 'correct' => 'o2'],
        CardSource::Today, null, $unit, $ref,
    );
}

function s1Now(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-09-16T10:00:00+00:00');
}

it('deals a failed choice again once, and marks its unit to return when the copy fails too', function () {
    $card = s1Card(CardKind::WordChoose);

    expect($card->answer(CardResult::Failed, 1, ['chosen' => 'o1'], s1Now()))->toBeTrue()
        ->and($card->returns())->toBeFalse()
        ->and($card->response())->toBe(['chosen' => 'o1']);

    $copy = $card->retry(DayCardId::generate(), 9, $card->payload());

    expect($copy->answer(CardResult::Failed, 1, null, s1Now()))->toBeFalse()
        ->and($copy->returns())->toBeTrue();
});

it('returns a word, a frame and an exchange failed twice, never the day\'s listening', function () {
    foreach ([
        [CardKind::WordChoose, UnitKind::Word, 'v2'],
        [CardKind::PhraseSlot, UnitKind::Phrase, 'p1'],
        [CardKind::DialoguePartner, UnitKind::Exchange, 'x3'],
    ] as [$kind, $unit, $ref]) {
        $first = s1Card($kind, $unit, $ref);
        expect($first->answer(CardResult::Failed, 1, null, s1Now()))->toBeTrue($kind->value);
        $copy = $first->retry(DayCardId::generate(), 10, $first->payload());

        expect($copy->answer(CardResult::Failed, 2, null, s1Now()))->toBeFalse($kind->value)
            ->and($copy->returns())->toBeTrue($kind->value);
    }
});

// Canon (SESSION-1a, хвост): «Слушаю и отвечаю» deals no copies — its review shows every answer, so the first failure is
// the only one, and the day's listening has no next day to come back on.
it('deals no copy of a failed listening choice and never returns the day\'s listening', function () {
    foreach ([
        [CardKind::ListenQuestion, 'L2'],
        [CardKind::ListenPredict, 'day'],
        [CardKind::ListenNumber, 'day'],
    ] as [$kind, $ref]) {
        $card = s1Card($kind, UnitKind::Day, $ref);

        expect($kind->requeues())->toBeFalse($kind->value)
            ->and($card->answer(CardResult::Failed, 1, null, s1Now()))->toBeFalse($kind->value)
            ->and($card->result())->toBe(CardResult::Failed)
            ->and($card->returns())->toBeFalse($kind->value);
    }
    expect(CardKind::WordChoose->requeues())->toBeTrue()
        ->and(CardKind::DialoguePartner->requeues())->toBeTrue()
        ->and(CardKind::SpeakAnswer->requeues())->toBeFalse();
});

// Canon (SESSION-1a, хвост): a unit comes back ONCE — a card that is itself a return never marks its unit again.
it('never marks a unit to return again from a card that is itself a return', function () {
    $returned = s1Card(CardKind::WordChoose, UnitKind::Word, 'v2')->retry(DayCardId::generate(), 1, []);
    $return = DayCard::dealt(DayCardId::generate(), PlanDayId::generate(), Stage::Words, 30, CardKind::WordChoose, ['scene_id' => 's'],
        CardSource::Returned, PlanDayId::generate(), UnitKind::Word, 'v2');

    expect($return->answer(CardResult::Failed, 1, null, s1Now()))->toBeTrue();
    $copy = $return->retry(DayCardId::generate(), 31, $return->payload());

    expect($copy->answer(CardResult::Failed, 2, null, s1Now()))->toBeFalse()
        ->and($copy->source())->toBe(CardSource::Returned)
        ->and($copy->returns())->toBeFalse()
        ->and($returned->source())->toBe(CardSource::Today);
});

it('gives a failure of a card that is not a choice no consequence', function () {
    foreach ([CardKind::WordRepeat, CardKind::DialogueAnswer, CardKind::ListenPace, CardKind::SpeakAnswer] as $kind) {
        $card = s1Card($kind, UnitKind::Exchange, 'x1');
        expect($card->answer(CardResult::Failed, 2, null, s1Now()))->toBeFalse($kind->value)
            ->and($card->returns())->toBeFalse($kind->value)
            ->and($card->result())->toBe(CardResult::Failed);
    }

    $passed = s1Card(CardKind::WordChoose);
    expect($passed->answer(CardResult::Passed, 0, null, s1Now()))->toBeFalse()
        ->and($passed->attempts())->toBe(1)
        ->and($passed->answeredAt())->toEqual(s1Now());
});

// Canon (SESSION-1d, DECISIONS п. 327): a phrase said aloud and given up on after two attempts with a microphone is a lapse
// of its frame like a wrong choice — the first deals a copy, the copy's marks the frame to return; a skip before the second
// attempt, a skip for want of a microphone and the other voice cards' skips have no consequence.
it('deals a phrase said aloud and given up on after two attempts again, and returns its frame when the copy is given up on too', function () {
    foreach ([CardKind::PhraseRepeat, CardKind::PhraseOtherSlot] as $kind) {
        $card = s1Card($kind, UnitKind::Phrase, 'p2');
        expect($card->answer(CardResult::Skipped, 2, ['heard' => 'it started'], s1Now()))->toBeTrue($kind->value)
            ->and($card->returns())->toBeFalse($kind->value);

        $copy = $card->retry(DayCardId::generate(), 9, $card->payload());
        expect($copy->answer(CardResult::Skipped, 2, null, s1Now()))->toBeFalse($kind->value)
            ->and($copy->returns())->toBeTrue($kind->value);

        $early = s1Card($kind, UnitKind::Phrase, 'p2');
        $noMic = s1Card($kind, UnitKind::Phrase, 'p2');
        expect($early->answer(CardResult::Skipped, 1, null, s1Now()))->toBeFalse($kind->value)
            ->and($early->returns())->toBeFalse($kind->value)
            ->and($noMic->answer(CardResult::Skipped, 2, ['no_mic' => true], s1Now()))->toBeFalse($kind->value)
            ->and($noMic->returns())->toBeFalse($kind->value);
    }

    $word = s1Card(CardKind::WordRepeat);
    expect($word->answer(CardResult::Skipped, 2, null, s1Now()))->toBeFalse()
        ->and($word->returns())->toBeFalse();
});

it('answers a judged card with the accepted verdict — passed, or hinted when the frame was shown', function () {
    $card = s1Card(CardKind::SpeakAnswer, UnitKind::Exchange, 'x2');
    $response = ['heard' => 'I have a headache', 'slot_value' => 'a headache', 'hinted' => false, 'judge' => ['accepted' => true]];
    $card->judge(true, false, $response, s1Now());

    expect($card->result())->toBe(CardResult::Passed)
        ->and($card->attempts())->toBe(1)
        ->and($card->response())->toBe($response)
        ->and($card->answeredAt())->toEqual(s1Now());

    $hinted = s1Card(CardKind::PhraseOwnSlot, UnitKind::Phrase, 'p2');
    $hinted->judge(true, true, ['heard' => 'x'], s1Now());

    expect($hinted->result())->toBe(CardResult::Hinted);
});

it('counts a rejected attempt and leaves the card open', function () {
    $card = s1Card(CardKind::SpeakRetell, UnitKind::Exchange, 'x4');
    $card->judge(false, false, ['heard' => 'first'], s1Now());
    $card->judge(false, true, ['heard' => 'second'], s1Now());

    expect($card->result())->toBeNull()
        ->and($card->isAnswered())->toBeFalse()
        ->and($card->attempts())->toBe(2)
        ->and($card->response())->toBe(['heard' => 'second'])
        ->and($card->answeredAt())->toBeNull();

    $card->answer(CardResult::Skipped, 3, null, s1Now());
    expect($card->result())->toBe(CardResult::Skipped)
        ->and($card->attempts())->toBe(3);
});

it('refuses a verdict and a second answer on an answered card', function () {
    $answered = s1Card(CardKind::SpeakAnswer, UnitKind::Exchange, 'x1');
    $answered->judge(true, false, ['heard' => 'x'], s1Now());

    expect(fn () => $answered->judge(true, false, ['heard' => 'y'], s1Now()))->toThrow(CardAlreadyAnswered::class)
        ->and(fn () => $answered->answer(CardResult::Skipped, 1, null, s1Now()))->toThrow(CardAlreadyAnswered::class)
        ->and($answered->response())->toBe(['heard' => 'x']);
});

it('copies a card to the end of its stage with the payload it is given and none of the original\'s answer', function () {
    $card = s1Card(CardKind::PhraseSlot, UnitKind::Phrase, 'p3');
    $card->answer(CardResult::Failed, 2, ['chosen' => 'o1'], s1Now());
    $id = DayCardId::generate();
    $payload = ['scene_id' => 'scene', 'options' => [['id' => 'o2', 'text' => 'b'], ['id' => 'o1', 'text' => 'a']], 'correct' => 'o2'];
    $copy = $card->retry($id, 12, $payload);

    expect($copy->id()->equals($id))->toBeTrue()
        ->and($copy->position())->toBe(12)
        ->and($copy->payload())->toBe($payload)
        ->and($copy->retryOf()?->equals($card->id()))->toBeTrue()
        ->and($copy->kind())->toBe(CardKind::PhraseSlot)
        ->and($copy->stage())->toBe($card->stage())
        ->and($copy->unitKind())->toBe(UnitKind::Phrase)
        ->and($copy->unitRef())->toBe('p3')
        ->and($copy->dayId()->equals($card->dayId()))->toBeTrue()
        ->and($copy->result())->toBeNull()
        ->and($copy->attempts())->toBe(0)
        ->and($copy->response())->toBeNull()
        ->and($copy->answeredAt())->toBeNull()
        ->and($copy->returns())->toBeFalse();
});
