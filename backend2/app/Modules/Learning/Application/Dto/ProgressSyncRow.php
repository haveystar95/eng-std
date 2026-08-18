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
        /**
         * The acquisition ladder, carried alongside the scheduler's own fields because the device
         * mirrors `LearningLadder::stepFor` to decide what a card should be while offline. Without
         * these two the phone would know when a word is due and not what to ask for it.
         */
        public string $acquisition = 'graduated',
        public int $learningStep = 0,
        /**
         * Correct non-practice reviews since graduation — what the ladder's rungs 3–5 are counted
         * in. Sent alongside `reps` and NOT derivable from it: `reps` counts scheduler calls of
         * every grade, so a device deriving the rung from it would deal dictation to a word its
         * owner has only ever got wrong.
         */
        public int $successfulReviews = 0,
    ) {}
}
