<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use App\Modules\Learning\Domain\ValueObject\Grade;
use App\Modules\Learning\Domain\ValueObject\ReviewId;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use DateTimeImmutable;

/** One review as it crosses into the application from the client (already validated into VOs). */
final readonly class ReviewInput
{
    public function __construct(
        public ReviewId $reviewId,
        public TermId $termId,
        public Grade $grade,
        public DateTimeImmutable $answeredAt,
        public ?StudySessionId $sessionId = null,
        public ?int $latencyMs = null,
    ) {}
}
