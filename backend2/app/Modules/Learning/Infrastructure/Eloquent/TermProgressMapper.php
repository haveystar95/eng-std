<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * Row ↔ {@see TermProgress}. The `user_term_progress` table has a composite key
 * (user_id, term_id), so its repository uses the query builder rather than an Eloquent
 * model; this mapper keeps the column mapping in one place.
 */
final class TermProgressMapper
{
    /** @param array<string, mixed> $row */
    public function toEntity(array $row): TermProgress
    {
        return TermProgress::reconstitute(
            userId: UserId::fromString((string) $row['user_id']),
            termId: TermId::fromString((string) $row['term_id']),
            state: LearningState::from((string) $row['state']),
            easeFactor: (float) $row['ease_factor'],
            intervalDays: (int) $row['interval_days'],
            dueAt: $this->toDate($row['due_at']),
            reps: (int) $row['reps'],
            lapses: (int) $row['lapses'],
            lastReviewedAt: $this->toDate($row['last_reviewed_at']),
            acquisition: Acquisition::from((string) $row['acquisition']),
            learningStep: (int) $row['learning_step'],
            successfulReviews: (int) $row['successful_reviews'],
        );
    }

    /**
     * Domain-owned columns (timestamps are added by the repository).
     *
     * @return array<string, mixed>
     */
    public function toColumns(TermProgress $progress): array
    {
        return [
            'state' => $progress->state()->value,
            // The ladder dimension travels in the same row but is never written by the scheduler:
            // every transition that moves one of these two leaves the SM-2 columns untouched, and
            // vice versa (see TermProgress).
            'acquisition' => $progress->acquisition()->value,
            'learning_step' => $progress->learningStep(),
            'successful_reviews' => $progress->successfulReviews(),
            'ease_factor' => $progress->easeFactor(),
            'interval_days' => $progress->intervalDays(),
            'due_at' => $progress->dueAt(),
            'reps' => $progress->reps(),
            'lapses' => $progress->lapses(),
            'last_reviewed_at' => $progress->lastReviewedAt(),
        ];
    }

    private function toDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        return new DateTimeImmutable((string) $value);
    }
}
