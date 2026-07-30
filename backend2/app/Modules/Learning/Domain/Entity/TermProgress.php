<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Entity;

use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * How well a user knows one term. Keyed by (user_id, term_id) — a term learned in one
 * collection is learned everywhere. A projection folded from the append-only reviews log
 * by the {@see \App\Modules\Learning\Domain\Service\Scheduler}; immutable, so every
 * scheduling step yields a fresh instance and the fold stays a pure function.
 */
final class TermProgress
{
    public const DEFAULT_EASE = 2.50;

    private function __construct(
        private readonly UserId $userId,
        private readonly TermId $termId,
        private readonly LearningState $state,
        private readonly float $easeFactor,
        private readonly int $intervalDays,
        private readonly ?DateTimeImmutable $dueAt,
        private readonly int $reps,
        private readonly int $lapses,
        private readonly ?DateTimeImmutable $lastReviewedAt,
    ) {}

    /** A term the user has never answered. */
    public static function start(UserId $userId, TermId $termId): self
    {
        return new self($userId, $termId, LearningState::New, self::DEFAULT_EASE, 0, null, 0, 0, null);
    }

    /**
     * A term triaged as "known": self-assessed, not proven. It has no SRS due date — a
     * verification check is scheduled separately (TriageVerificationPlanner). Not scheduled
     * by SM-2 while in this state.
     */
    public static function knownFromTriage(UserId $userId, TermId $termId): self
    {
        return new self($userId, $termId, LearningState::Known, self::DEFAULT_EASE, 0, null, 0, 0, null);
    }

    /**
     * A term triaged as "unsure": enters `learning` directly, due immediately, so it skips the
     * intro recognition step and shows up in the next session at the assembly level.
     */
    public static function learningFromTriage(UserId $userId, TermId $termId, DateTimeImmutable $now): self
    {
        return new self($userId, $termId, LearningState::Learning, self::DEFAULT_EASE, 0, $now, 0, 0, null);
    }

    /**
     * Return a term to `new` without erasing what we know about it: state and schedule reset,
     * but `reps`/`lapses` survive. Used when a `known` mark is undone — a term with a long
     * history that a user manually marked known must not be reduced to a blank slate. To
     * selection, a `new` row and a missing row mean the same thing, so nothing is lost by
     * keeping the row.
     */
    public function returnToNew(): self
    {
        return new self(
            $this->userId, $this->termId, LearningState::New, self::DEFAULT_EASE, 0, null,
            $this->reps, $this->lapses, $this->lastReviewedAt,
        );
    }

    /** Rebuild from persistence, or from the scheduler producing the next state. */
    public static function reconstitute(
        UserId $userId,
        TermId $termId,
        LearningState $state,
        float $easeFactor,
        int $intervalDays,
        ?DateTimeImmutable $dueAt,
        int $reps,
        int $lapses,
        ?DateTimeImmutable $lastReviewedAt,
    ): self {
        return new self(
            $userId, $termId, $state, $easeFactor, $intervalDays,
            $dueAt, $reps, $lapses, $lastReviewedAt,
        );
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function termId(): TermId
    {
        return $this->termId;
    }

    public function state(): LearningState
    {
        return $this->state;
    }

    public function easeFactor(): float
    {
        return $this->easeFactor;
    }

    public function intervalDays(): int
    {
        return $this->intervalDays;
    }

    public function dueAt(): ?DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function reps(): int
    {
        return $this->reps;
    }

    public function lapses(): int
    {
        return $this->lapses;
    }

    public function lastReviewedAt(): ?DateTimeImmutable
    {
        return $this->lastReviewedAt;
    }

    public function isNew(): bool
    {
        return $this->state === LearningState::New;
    }
}
