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
