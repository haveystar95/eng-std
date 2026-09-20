<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Repository;

use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * THE TALKS OF A PLAN. The journal of lines is APPEND-ONLY and the implementation is what keeps it
 * so: {@see save()} inserts the lines the aggregate has grown and never updates or deletes one.
 * A line is written complete — its words, its sound and its bill are known before it is stored.
 */
interface ConversationRepository
{
    /** The talk by id, for this learner only — someone else's id reads as «not found». */
    public function find(ConversationId $id, UserId $userId): ?Conversation;

    /** The one talk of the day that is not over, if there is one (the partial unique index). */
    public function openForDay(PlanDayId $dayId): ?Conversation;

    /** The day's latest talk, over or not — what the day window and the day's summary read. */
    public function latestForDay(PlanDayId $dayId): ?Conversation;

    /**
     * The latest talk of each of these days, without their lines — one query for a route or a plan.
     *
     * @param  list<PlanDayId>  $dayIds
     * @return array<string, Conversation> by day id
     */
    public function latestForDays(array $dayIds): array;

    /**
     * Locks the talk's row and reports where it stands — the re-check every write does after the
     * model has answered (the pattern `JudgeCardHandler` uses): the talk was read WITHOUT a lock so
     * that nothing was held while the learner waited, and two moves sent at once must not both land.
     *
     * @return array{state: \App\Modules\Plan\Domain\ValueObject\ConversationState, turns: int}|null
     */
    public function lockState(ConversationId $id): ?array;

    /** Writes the talk's own row and appends the lines it has grown since it was read. */
    public function save(Conversation $conversation): void;
}
