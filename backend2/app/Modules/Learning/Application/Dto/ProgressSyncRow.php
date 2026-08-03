<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use DateTimeImmutable;

/** One (user, term) progress change for delta sync. Progress rows are never deleted → upsert only. */
final readonly class ProgressSyncRow
{
    public function __construct(
        public string $termId,
        public string $state,
        public float $easeFactor,
        public int $intervalDays,
        public ?DateTimeImmutable $dueAt,
        public int $reps,
        public int $lapses,
        public ?DateTimeImmutable $lastReviewedAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
