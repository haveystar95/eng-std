<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\StageSummary;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * THE SUMMARY OF A STAGE OF CARDS, READ OFF ITS CARDS (кадр 30-6, наряд FIX-3 §10). The phone counted it itself until now
 * (`SessionSummaries.stageTally`); the numbers are the server's, like every number the plan prints. «С первого раза» is a
 * card `passed` at its first attempt — never `hinted`, never skipped, never after a lapse (a lapse deals a copy, and the
 * copy's unit is not first-time any more). What is counted is the stage's UNIT:
 *
 * - «Слушаю и отвечаю» — its QUESTIONS (`listen_question`, `listen_predict`, `listen_number`): its unit is the whole
 *   visit, and a question answered wrong is final;
 * - «Повторение» — its CARDS as dealt (a lapse's copy is not a second card of the day);
 * - «Вспомнить» — its lines said aloud (the sheet of lines is not a line); nothing of it comes back;
 * - every other stage — its UNITS (a word, a phrase, an exchange of a scene): a unit is walked when all of its cards are
 *   answered, first-time when all of them are, and comes back when one of them says so.
 */
final class StageSummaries
{
    /** @var list<CardKind> */
    private const LISTEN_QUESTIONS = [CardKind::ListenQuestion, CardKind::ListenPredict, CardKind::ListenNumber];

    /** @param list<DayCard> $cards the stage's cards */
    public static function of(Stage $stage, array $cards): StageSummary
    {
        $counted = match ($stage) {
            Stage::Listen => array_values(array_filter($cards, static fn (DayCard $c): bool => in_array($c->kind(), self::LISTEN_QUESTIONS, true))),
            Stage::Repetition => array_values(array_filter($cards, static fn (DayCard $c): bool => $c->retryOf() === null)),
            Stage::Recall => array_values(array_filter($cards, static fn (DayCard $c): bool => $c->kind() !== CardKind::RecallScenes && $c->retryOf() === null)),
            default => null,
        };
        if ($counted !== null) {
            return new StageSummary(
                done: count(array_filter($counted, static fn (DayCard $c): bool => $c->isAnswered())),
                total: count($counted),
                firstTry: count(array_filter($counted, self::firstTime(...))),
                returns: $stage === Stage::Recall ? 0 : count(array_filter($counted, static fn (DayCard $c): bool => $c->returns() && $c->unitKind() !== UnitKind::Day)),
            );
        }

        $units = [];
        foreach ($cards as $card) {
            if ($card->unitKind() !== UnitKind::Day) {
                $units[UnitStates::key(UnitStates::sceneOf($card), $card->unitKind(), $card->unitRef())][] = $card;
            }
        }
        $done = 0;
        $first = 0;
        $returns = 0;
        foreach ($units as $unitCards) {
            $done += self::all($unitCards, static fn (DayCard $c): bool => $c->isAnswered()) ? 1 : 0;
            $first += self::all($unitCards, self::firstTime(...)) ? 1 : 0;
            $returns += array_filter($unitCards, static fn (DayCard $c): bool => $c->returns()) !== [] ? 1 : 0;
        }

        return new StageSummary(done: $done, total: count($units), firstTry: $first, returns: $returns);
    }

    private static function firstTime(DayCard $card): bool
    {
        return $card->result() === CardResult::Passed && $card->attempts() <= 1;
    }

    /**
     * @param  list<DayCard>  $cards
     * @param  callable(DayCard): bool  $test
     */
    private static function all(array $cards, callable $test): bool
    {
        foreach ($cards as $card) {
            if (! $test($card)) {
                return false;
            }
        }

        return $cards !== [];
    }
}
