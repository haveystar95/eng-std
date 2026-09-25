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
 *
 * THE SIXTH STAGE SKIPPED (наряд ACC-1 §3) is a passage of the talk with NO talk: the day was dealt with nothing to talk
 * about — its scenes have no written lesson — or it was dealt on five stages before the talk existed (the days the
 * migration that dropped `plan_days.has_conversation` found open or closed). Every day has six stages now; a skipped
 * sixth is behind the day — it does not hold the day shut — but it is not passed: no talk may be started on it
 * (422 `plan_conversation_not_in_day`), it adds no minutes, it sends nothing back tomorrow, and no row of it is drawn.
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

    /** The sixth stage of a day skipped at `$at` — the day has nothing to talk about. */
    public static function skippedTalk(PlanId $planId, PlanDayId $dayId, DateTimeImmutable $at): self
    {
        return new self($planId, $dayId, Stage::Conversation, null, $at);
    }

    /** Is this the sixth stage skipped — its passage with no talk that walked it? */
    public function skipsTalk(): bool
    {
        return $this->stage === Stage::Conversation && $this->conversationId === null;
    }
}
