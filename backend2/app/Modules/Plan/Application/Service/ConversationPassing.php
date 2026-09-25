<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\StagePassageRepository;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\StagePassage;
use DateTimeImmutable;

/**
 * THE SIXTH STAGE IS WALKED WHEN A TALK COMES TO AN END OF ITS OWN (наряд CONV-2, п. 2) — written here, inside the
 * transaction that writes the talk, by both doors a talk can end through (the opening line and a move).
 *
 * The first such talk of the day is the one that walked it: the journal keeps one row per day and stage and never
 * moves it, so «Ещё раз» after it is a replay — a talk on top of a walked day, not a new attempt at the stage. A talk
 * closed by «Ещё раз» itself ended nothing and walks nothing ({@see Conversation::passesStage()}).
 *
 * The talk that walks the stage is part of the day's minutes from that moment (наряд BACK-TAILS-2 §8), so the day's
 * metrics are counted again here, in the same transaction — a replay that ends of its own walks nothing and leaves them
 * as they are.
 */
final readonly class ConversationPassing
{
    public function __construct(
        private StagePassageRepository $passages,
        private DayCardRepository $cards,
        private PlanRepository $plans,
        private DayMetricsOf $metrics,
    ) {}

    /** Call inside the transaction that saves the talk: the talk and the fact it walked the stage land together. */
    public function mark(Conversation $talk): void
    {
        $endedAt = $talk->endedAt();
        if (! $talk->passesStage() || $endedAt === null) {
            return;
        }
        $this->passages->record(new StagePassage($talk->planId(), $talk->dayId(), Stage::Conversation, $talk->id(), $endedAt));
        if ($this->passages->of($talk->dayId(), Stage::Conversation)?->conversationId?->equals($talk->id()) === true) {
            $this->plans->saveDayMetrics($talk->dayId(), $this->metrics->withTalk($this->cards->forDay($talk->dayId()), $talk));
        }
    }

    /**
     * THE SIXTH STAGE SKIPPED (наряд ACC-1 §3): the day is dealt with nothing to talk about — no scene of it has a written
     * lesson — so the stage is behind it from the moment it opens, walked by no talk. Call inside the transaction that
     * deals the day; the same one writer as {@see mark()}, the same one row per day and stage.
     */
    public function skip(PlanDay $day, DateTimeImmutable $at): void
    {
        $this->passages->record(StagePassage::skippedTalk($day->planId(), $day->id(), $at));
    }
}
