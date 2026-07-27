<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Entity;

use App\Modules\Learning\Domain\ValueObject\Grade;
use App\Modules\Learning\Domain\ValueObject\ReviewId;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * One immutable answer in the append-only reviews log. Its id is client-generated so a
 * re-uploaded batch is an ignored insert, never a merge. Never updated once recorded.
 */
final class Review
{
    public function __construct(
        public readonly ReviewId $id,
        public readonly UserId $userId,
        public readonly TermId $termId,
        public readonly Grade $grade,
        public readonly DateTimeImmutable $answeredAt,
        public readonly ?StudySessionId $sessionId = null,
        public readonly ?int $latencyMs = null,
    ) {}
}
