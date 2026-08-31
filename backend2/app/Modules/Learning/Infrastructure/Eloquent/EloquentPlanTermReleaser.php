<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\PlanTermReleaser;
use App\Modules\Learning\Domain\ValueObject\EnrollmentSources;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

/**
 * One plan's claim, taken off every pair it holds, in one statement.
 *
 * `enrollment_sources - 'plan:…'` is Postgres' jsonb array-minus-element: it removes the string if
 * it is there and returns the array unchanged if it is not, which makes this idempotent for free —
 * abandoning a plan twice releases the same rows to the same state.
 *
 * `enrolled_at` is deliberately not in the SET list. Releasing is not unenrolling: the learner
 * spent days on these words, and what changes is that they may now remove them.
 */
final readonly class EloquentPlanTermReleaser implements PlanTermReleaser
{
    public function releasePlan(UserId $userId, string $planId): int
    {
        $source = EnrollmentSources::forPlan($planId);

        // One statement, with the source BOUND rather than quoted into the SQL. `enrollment_sources
        // - ?` is jsonb array-minus-element: it removes the string if it is there and returns the
        // array unchanged if it is not, which makes this idempotent for free.
        return DB::update(
            'UPDATE user_term_progress SET enrollment_sources = enrollment_sources - ?, updated_at = now() '
            . 'WHERE user_id = ? AND enrollment_sources @> ?::jsonb',
            [$source, $userId->value, json_encode([$source], JSON_THROW_ON_ERROR)],
        );
    }

    public function unenrolUntouched(UserId $userId, string $planId): int
    {
        $source = EnrollmentSources::forPlan($planId);

        // `=` and not `@>`: the pair must have had NO other reason to be in the pool. A word the
        // learner also saved by hand carries two reasons, and only one of them is ending.
        //
        // The review check is a NOT EXISTS over the append-only log rather than a join on sessions:
        // a review is written for every answer of every kind, practice included, so «no row» is the
        // whole of «never touched» and needs no second table to say it.
        return DB::update(
            'UPDATE user_term_progress SET enrolled_at = NULL, enrollment_sources = ?::jsonb, updated_at = now() '
            . 'WHERE user_id = ? AND enrollment_sources = ?::jsonb '
            . 'AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.user_id = user_term_progress.user_id '
            . 'AND r.term_id = user_term_progress.term_id)',
            [
                json_encode([], JSON_THROW_ON_ERROR),
                $userId->value,
                json_encode([$source], JSON_THROW_ON_ERROR),
            ],
        );
    }
}
