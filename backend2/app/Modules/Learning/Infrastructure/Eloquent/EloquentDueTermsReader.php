<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\DueTermsReader;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final class EloquentDueTermsReader implements DueTermsReader
{
    private const TABLE = 'user_term_progress';

    /** @var list<string> */
    private const COLUMNS = ['term_id', 'state', 'interval_days', 'due_at', 'reps', 'acquisition', 'learning_step'];

    public function selectableAmong(UserId $userId, DateTimeImmutable $now, array $termIds, int $limit): array
    {
        if ($termIds === []) {
            return [];
        }

        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId->value)
            ->whereIn('term_id', $termIds)
            ->where(static function (Builder $q) use ($now): void {
                // Unfinished on the ladder — no due date, and none is wanted: the recognition rungs
                // never schedule, so "is it due" is not a question that applies to them.
                $q->where('acquisition', '<>', Acquisition::Graduated->value)
                    // …or graduated and owed a review: due now, or never scheduled at all (a fresh
                    // graduate, or a pair returned from `known`).
                    ->orWhere(static function (Builder $q) use ($now): void {
                        $q->where('acquisition', Acquisition::Graduated->value)
                            ->where(static fn (Builder $q) => $q->whereNull('due_at')->orWhere('due_at', '<=', $now));
                    });
            })
            // NULLS FIRST is the whole ordering: it puts the unfinished and the freshly graduated
            // ahead of anything merely due, then soonest first among the rest.
            ->orderByRaw('due_at ASC NULLS FIRST')
            ->orderBy('term_id')
            ->limit($limit)
            ->get(self::COLUMNS);

        return array_values($rows->map($this->toView(...))->all());
    }

    public function allAmong(UserId $userId, array $termIds, int $limit): array
    {
        if ($termIds === []) {
            return [];
        }

        // No state/due filter: practice drills whatever is in scope. Ordering is irrelevant (the
        // caller shuffles), so we just cap the read.
        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId->value)
            ->whereIn('term_id', $termIds)
            ->limit($limit)
            ->get(self::COLUMNS);

        return array_values($rows->map($this->toView(...))->all());
    }

    private function toView(stdClass $row): DueTermView
    {
        return new DueTermView(
            termId: TermId::fromString((string) $row->term_id),
            state: LearningState::from((string) $row->state),
            intervalDays: (int) $row->interval_days,
            dueAt: $row->due_at !== null ? new DateTimeImmutable((string) $row->due_at) : null,
            reps: (int) $row->reps,
            acquisition: Acquisition::from((string) $row->acquisition),
            learningStep: (int) $row->learning_step,
        );
    }
}
