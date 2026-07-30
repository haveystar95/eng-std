<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ReviewId;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use DateTimeImmutable;

/**
 * One answer as the client uploads it — the RAW answer, not a grade. The server grades it
 * (leniency + per-mode latency), so the grading rule lives in exactly one runtime. A practice
 * answer is recorded but never schedules.
 */
final readonly class ReviewInput
{
    public function __construct(
        public ReviewId $reviewId,
        public TermId $termId,
        public ExerciseMode $exerciseMode,
        public string $response,
        public DateTimeImmutable $answeredAt,
        public bool $usedHint = false,
        public bool $isPractice = false,
        public ?int $latencyMs = null,
        public ?StudySessionId $sessionId = null,
    ) {}
}
