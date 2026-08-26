<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Dto\PooledTermRef;
use App\Modules\Learning\Application\Dto\ProgressSyncRow;
use App\Modules\Learning\Application\Port\ProgressSyncReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentProgressSyncReader implements ProgressSyncReader
{
    public function changedProgress(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array
    {
        // `since` is whatever the client echoed back; if it carries a device offset, binding it raw
        // would shift the whole window by that offset and silently drop or re-send rows (UtcInstant).
        $q = DB::table('user_term_progress')
            ->where('user_id', $userId->value)
            ->where('updated_at', '<=', UtcInstant::bind($upper));
        if ($since !== null) {
            $q->where('updated_at', '>=', UtcInstant::bind($since));
        }

        return array_values($q->orderBy('updated_at')->orderBy('term_id')
            ->get(['term_id', 'state', 'ease_factor', 'interval_days', 'due_at', 'reps', 'lapses', 'last_reviewed_at', 'updated_at', 'acquisition', 'learning_step', 'successful_reviews', 'enrolled_at'])
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
                enrolledAt: $r->enrolled_at !== null ? new DateTimeImmutable((string) $r->enrolled_at) : null,
            ))->all());
    }

    public function pooledTermIds(UserId $userId): array
    {
        $rows = DB::table('user_term_progress')
            ->where('user_id', $userId->value)
            ->whereNotNull('enrolled_at')
            ->pluck('term_id');

        return array_values(array_map(static fn ($id): string => (string) $id, $rows->all()));
    }

    public function newlyEnrolledTermRefs(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array
    {
        // A snapshot already ships every scoped term, and the pool is now part of that scope.
        if ($since === null) {
            return [];
        }

        $rows = DB::table('user_term_progress as p')
            ->join('terms as t', 't.id', '=', 'p.term_id')
            ->where('p.user_id', $userId->value)
            ->whereNotNull('p.enrolled_at')
            ->where('p.enrolled_at', '>=', UtcInstant::bind($since))
            ->where('p.enrolled_at', '<=', UtcInstant::bind($upper))
            ->orderBy('t.updated_at')
            ->orderBy('t.id')
            ->get(['t.id', 't.updated_at']);

        return array_values($rows->map(static fn (object $r): PooledTermRef => new PooledTermRef(
            (string) $r->id,
            new DateTimeImmutable((string) $r->updated_at),
        ))->all());
    }
}
