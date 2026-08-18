<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\ProgressSnapshotReader;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentProgressSnapshotReader implements ProgressSnapshotReader
{
    public function forTerms(UserId $userId, array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $rows = DB::table('user_term_progress')
            ->where('user_id', $userId->value)
            ->whereIn('term_id', $termIds)
            ->get(['term_id', 'state', 'interval_days', 'due_at', 'reps', 'acquisition', 'learning_step', 'successful_reviews']);

        $out = [];
        foreach ($rows as $row) {
            $id = (string) $row->term_id;
            $out[$id] = new DueTermView(
                TermId::fromString($id),
                LearningState::from((string) $row->state),
                (int) $row->interval_days,
                $row->due_at !== null ? new DateTimeImmutable((string) $row->due_at) : null,
                reps: (int) $row->reps,
                // Carried because the pre-batch rung is a fact the fold destroys: by the time an
                // answer is folded the pair may already have moved a rung, and the grader has to
                // know what the card it is grading actually asked.
                acquisition: Acquisition::from((string) $row->acquisition),
                learningStep: (int) $row->learning_step,
                successfulReviews: (int) $row->successful_reviews,
            );
        }

        return $out;
    }
}
