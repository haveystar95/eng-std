<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Entity;

use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\Grade;
use App\Modules\Learning\Domain\ValueObject\ReviewId;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * One immutable answer in the append-only reviews log. Its id is client-generated so a
 * re-uploaded batch is an ignored insert, never a merge. The grade is server-computed; the
 * mode, raw response and practice flag are recorded alongside so the log stays enough to
 * recompute medians, retention and progress. Never updated once recorded.
 */
final class Review
{
    public function __construct(
        public readonly ReviewId $id,
        public readonly UserId $userId,
        public readonly TermId $termId,
        public readonly Grade $grade,
        public readonly DateTimeImmutable $answeredAt,
        public readonly int $clientSeq,
        public readonly ?ExerciseMode $exerciseMode = null,
        public readonly bool $isPractice = false,
        public readonly bool $isVerification = false,
        public readonly ?string $response = null,
        public readonly ?StudySessionId $sessionId = null,
        public readonly ?int $latencyMs = null,
        /**
         * The rung of the acquisition ladder the card was dealt at, or null outside it. Recorded
         * because the pair's rung MOVES as this very answer is folded, so the log is the only place
         * that can still say what the card asked — and rungs 1 and 2 ask different questions, not
         * merely differently-shaped versions of one.
         */
        public readonly ?int $ladderStep = null,
    ) {}

    /** Correct means anything the user got right, including "right but slow/hinted" (hard). */
    public function isCorrect(): bool
    {
        return $this->grade !== Grade::Again;
    }
}
