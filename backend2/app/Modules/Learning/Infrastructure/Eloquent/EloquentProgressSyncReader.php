<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Dto\ProgressSyncRow;
use App\Modules\Learning\Application\Port\ProgressSyncReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentProgressSyncReader implements ProgressSyncReader
{
    public function changedProgress(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array
    {
        $q = DB::table('user_term_progress')
            ->where('user_id', $userId->value)
            ->where('updated_at', '<=', $upper);
        if ($since !== null) {
            $q->where('updated_at', '>=', $since);
        }

        return array_values($q->orderBy('updated_at')->orderBy('term_id')
            ->get(['term_id', 'state', 'ease_factor', 'interval_days', 'due_at', 'reps', 'lapses', 'last_reviewed_at', 'updated_at', 'acquisition', 'learning_step', 'successful_reviews'])
            ->map(fn ($r): ProgressSyncRow => new ProgressSyncRow(
                termId: (string) $r->term_id,
                state: (string) $r->state,
                easeFactor: (float) $r->ease_factor,
                intervalDays: (int) $r->interval_days,
                dueAt: $r->due_at !== null ? new DateTimeImmutable((string) $r->due_at) : null,
                reps: (int) $r->reps,
                lapses: (int) $r->lapses,
                lastReviewedAt: $r->last_reviewed_at !== null ? new DateTimeImmutable((string) $r->last_reviewed_at) : null,
                updatedAt: new DateTimeImmutable((string) $r->updated_at),
                acquisition: (string) $r->acquisition,
                learningStep: (int) $r->learning_step,
                successfulReviews: (int) $r->successful_reviews,
            ))->all());
    }
}
