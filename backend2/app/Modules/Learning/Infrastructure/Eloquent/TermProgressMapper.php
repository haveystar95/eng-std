<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

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
            enrolledAt: $this->toDate($row['enrolled_at'] ?? null),
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
            // Pool membership: the third, independent fact on the row. Written only by enroll() /
            // unenroll(), and by nothing else — see TermProgress.
            'enrolled_at' => $this->toUtc($progress->enrolledAt()),
            'ease_factor' => $progress->easeFactor(),
            'interval_days' => $progress->intervalDays(),
            'due_at' => $this->toUtc($progress->dueAt()),
            'reps' => $progress->reps(),
            'lapses' => $progress->lapses(),
            'last_reviewed_at' => $this->toUtc($progress->lastReviewedAt()),
        ];
    }

    /**
     * Bind an instant as an instant. The query builder turns a DateTimeInterface into a bare
     * `Y-m-d H:i:s` string IN THE OBJECT'S OWN ZONE (Connection::prepareBindings) — no offset — and
     * Postgres then reads that string in the session zone, UTC. So a due date correctly computed as
     * midnight in Europe/Bucharest arrived in the column as midnight UTC and came back to the phone
     * as 03:00 (QA-BUG-1): the offset was lost here, on the write, not in the scheduler.
     *
     * Converting first makes the string say what the instant means. Every datetime column on this
     * row goes through it — `due_at` is the one that was noticed, but `last_reviewed_at` carries the
     * device's own offset and was landing hours off for the same reason.
     */
    private function toUtc(?DateTimeImmutable $value): ?DateTimeImmutable
    {
        return $value?->setTimezone(new DateTimeZone('UTC'));
    }

    private function toDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        return new DateTimeImmutable((string) $value);
    }
}
