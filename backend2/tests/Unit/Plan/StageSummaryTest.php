<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\Service\DayWindowStages;
use App\Modules\Plan\Domain\Service\StageSummaries;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\StageSummary;
use App\Modules\Plan\Domain\ValueObject\TalkStage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Domain\ValueObject\WindowStage;
use App\Modules\Plan\Domain\ValueObject\WindowStatus;

/**
 * «ЕЩЁ РАЗ» OF EVERY ROW AND THE NUMBERS OF 30-6 (наряд FIX-3 §8, §10): a stage of cards may always be walked again — on a
 * closed day too, by the phone itself; the talk once walked and while the day's replays last; and a stage of cards
 * carries its summary — volume, «с первого раза», what comes back — counted by the server, as the phone counted it.
 */

/** One card of a stage: `$result` null — not answered; `$returns` — a copy failed again, the unit comes back; `$retry` — a copy. */
function ssCard(Stage $stage, CardKind $kind, string $ref, ?CardResult $result = null, int $attempts = 1, bool $returns = false, bool $retry = false, UnitKind $unit = UnitKind::Word): DayCard
{
    $card = DayCard::dealt(DayCardId::generate(), PlanDayId::generate(), $stage, 1, $kind, ['scene_id' => 'S1'], CardSource::Today, null, $unit, $ref);
    if ($retry || $returns) {
        $card = $card->retry(DayCardId::generate(), 2, $card->payload());
    }
    if ($returns) {
        // A copy failed again — the unit comes back tomorrow (D-06).
        $card->answer(CardResult::Failed, 2, null, new DateTimeImmutable);
    } elseif ($result !== null) {
        $card->answer($result, $attempts, null, new DateTimeImmutable);
    }

    return $card;
}

// Canon (§10): «stages[].summary для этапов карточек: {done, total, first_try, returns} — объём, «с первого раза»,
// возвраты». A unit of words is walked when all of its cards are, first-time when all of them passed at the first
// attempt, and comes back when one of its cards says so. CATCHES a count of cards for units, `hinted` or a second
// attempt counted as the first, and a returning unit missed.
it('counts a stage of cards by its units: walked, first-time, coming back', function () {
    $cards = [
        ssCard(Stage::Words, CardKind::WordIntro, 'v1', CardResult::Passed),
        ssCard(Stage::Words, CardKind::WordChoose, 'v1', CardResult::Passed),
        ssCard(Stage::Words, CardKind::WordIntro, 'v2', CardResult::Passed),
        ssCard(Stage::Words, CardKind::WordChoose, 'v2', CardResult::Passed, attempts: 2),
        ssCard(Stage::Words, CardKind::WordIntro, 'v3', CardResult::Hinted),
        ssCard(Stage::Words, CardKind::WordChoose, 'v3', returns: true),
        ssCard(Stage::Words, CardKind::WordIntro, 'v4'),
    ];

    expect(StageSummaries::of(Stage::Words, $cards))->toEqual(new StageSummary(done: 3, total: 4, firstTry: 1, returns: 1));
});

// Canon (30-6 by stage): «Слушаю и отвечаю» counts its QUESTIONS, «Повторение» its cards as dealt, «Вспомнить» its lines
// said aloud without the sheet. CATCHES the whole visit counted as a unit, a lapse's copy counted twice, and the sheet
// counted as a line.
it('counts the questions of the listening, the dealt cards of a review and the lines of «Вспомнить»', function () {
    $listen = [
        ssCard(Stage::Listen, CardKind::ListenDialogue, 'L0', CardResult::Passed, unit: UnitKind::Day),
        ssCard(Stage::Listen, CardKind::ListenQuestion, 'L1', CardResult::Passed, unit: UnitKind::Day),
        ssCard(Stage::Listen, CardKind::ListenPredict, 'L2', CardResult::Failed, unit: UnitKind::Day),
        ssCard(Stage::Listen, CardKind::ListenNumber, 'L3', unit: UnitKind::Day),
    ];
    $review = [
        ssCard(Stage::Repetition, CardKind::SpeakAnswer, 'x1', CardResult::Passed, unit: UnitKind::Exchange),
        ssCard(Stage::Repetition, CardKind::SpeakAnswer, 'x2', CardResult::Skipped, attempts: 2, unit: UnitKind::Exchange),
        ssCard(Stage::Repetition, CardKind::SpeakAnswer, 'x2', CardResult::Passed, retry: true, unit: UnitKind::Exchange),
    ];
    $recall = [
        ssCard(Stage::Recall, CardKind::RecallScenes, 'day', CardResult::Passed, unit: UnitKind::Day),
        ssCard(Stage::Recall, CardKind::SpeakRetell, 'x1', CardResult::Passed, unit: UnitKind::Exchange),
        ssCard(Stage::Recall, CardKind::SpeakRetell, 'x2', unit: UnitKind::Exchange),
    ];

    expect(StageSummaries::of(Stage::Listen, $listen))->toEqual(new StageSummary(done: 2, total: 3, firstTry: 1, returns: 0))
        ->and(StageSummaries::of(Stage::Repetition, $review))->toEqual(new StageSummary(done: 2, total: 2, firstTry: 1, returns: 0))
        ->and(StageSummaries::of(Stage::Recall, $recall))->toEqual(new StageSummary(done: 1, total: 2, firstTry: 1, returns: 0));
});

// Canon (§8): «stages[].again: этапы карточек — всегда true, и у закрытых дней; разговор — again = true, пока не упёрся в
// replays_per_day». CATCHES «Ещё раз» of a card stage offered only on a walked day, and the talk's offered before it is
// walked or once the day's replays are spent.
it('lets every stage of cards be walked again in every state, and the talk once walked while its replays last', function () {
    $cards = [ssCard(Stage::Words, CardKind::WordIntro, 'v1', CardResult::Passed), ssCard(Stage::Speak, CardKind::SpeakAnswer, 'x1', unit: UnitKind::Exchange)];
    $rows = static fn (WindowStatus $status, ?TalkStage $talk, bool $replays): array => DayWindowStages::of(
        $cards, [], $status, new DayPace, true, $talk, 5, ['title' => 'Поговори с тренером', 'scenes' => 1], $replays,
    );
    $again = static fn (array $rows): array => array_map(static fn (WindowStage $r): array => [$r->stage->value, $r->again], $rows);

    expect($again($rows(WindowStatus::InProgress, null, true)))->toBe([['words', true], ['speak', true], ['conversation', false]])
        ->and($again($rows(WindowStatus::Passed, TalkStage::Passed, true)))->toBe([['words', true], ['speak', true], ['conversation', true]])
        ->and($again($rows(WindowStatus::Passed, TalkStage::Passed, false)))->toBe([['words', true], ['speak', true], ['conversation', false]])
        ->and($again($rows(WindowStatus::NotStarted, null, true)))->toBe([['words', true], ['speak', true], ['conversation', false]])
        // A stage of cards carries its summary in the row; the talk's row has none.
        ->and($rows(WindowStatus::InProgress, null, true)[0]->summary)->toEqual(new StageSummary(done: 1, total: 1, firstTry: 1, returns: 0))
        ->and($rows(WindowStatus::InProgress, null, true)[2]->summary)->toBeNull();
});
