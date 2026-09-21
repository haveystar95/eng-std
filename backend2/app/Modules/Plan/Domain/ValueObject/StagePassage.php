<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

use DateTimeImmutable;

/**
 * A STAGE OF A DAY WAS WALKED — a fact, written once and never taken back (наряд CONV-2, п. 2; дух решения 298:
 * «прохождение этапа — событие журнала, а не пересчёт»).
 *
 * The sixth stage has no cards to count, and «the day's latest talk is over» was the reading CONV-1 used — which «Ещё
 * раз» broke: a new talk started after the first one ended made the stage «идёт» again, and the day could not be
 * closed until the second talk was walked to its end (CLIENT-CONV-1a, §5 п. 15). The FIRST talk that came to an end of
 * its own is what walked the stage; every talk after it is a replay, an exercise on top of a day already walked.
 *
 * One row per day and stage, in `plan_stage_passages`, append-only: a replay cannot un-walk it, a second natural end
 * cannot move it.
 */
final readonly class StagePassage
{
    public function __construct(
        public PlanId $planId,
        public PlanDayId $dayId,
        public Stage $stage,
        public ?ConversationId $conversationId,
        public DateTimeImmutable $passedAt,
    ) {}
}
