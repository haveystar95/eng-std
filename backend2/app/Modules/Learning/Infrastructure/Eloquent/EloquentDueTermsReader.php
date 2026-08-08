<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\DueTermsReader;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

final class EloquentDueTermsReader implements DueTermsReader
{
    private const TABLE = 'user_term_progress';

    /** @var list<string> */
    private const COLUMNS = ['term_id', 'state', 'interval_days', 'due_at', 'reps'];

    public function dueAmong(UserId $userId, DateTimeImmutable $now, array $termIds, int $limit): array
    {
        if ($termIds === []) {
            return [];
        }

        $rows = DB::table(self::TABLE)
            ->where('user_id', $userId->value)
            ->where('state', '<>', LearningState::New->value)
            ->where('due_at', '<=', $now)
            ->whereIn('term_id', $termIds)
            ->orderBy('due_at')
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
        );
    }
}
