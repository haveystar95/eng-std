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
        public int $clientSeq,
        public bool $usedHint = false,
        public bool $isPractice = false,
        public ?int $latencyMs = null,
        public ?StudySessionId $sessionId = null,
        /**
         * Which rung of the acquisition ladder the card was dealt at, echoed from the card. The
         * pair's rung moves the instant this answer is folded, so without it the server could not
         * tell afterwards what the card had asked — and rung 1 asks a different QUESTION (term →
         * translation, tapped, graded by identity) rather than merely a different-looking one.
         *
         * It is a claim by the client, so it is verified: the server honours the identity branch
         * only for a pair that was actually on the ladder before this batch. A false claim on a
         * graduated pair is simply graded as text, which the typed answer then fails — self-
         * limiting, and never a way to score a word correct.
         */
        public ?int $ladderStep = null,
    ) {}
}
