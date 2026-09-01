<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\PlanTermArchiver;
use App\Modules\Learning\Domain\ValueObject\EnrollmentSources;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

/**
 * One plan's claim taken off every pair it holds, and out of the pool goes every pair that had no
 * other claim on it — in one statement.
 *
 * `enrollment_sources - 'plan:…'` is Postgres' jsonb array-minus-element: it removes the string if
 * it is there and returns the array unchanged if it is not, which makes this idempotent for free —
 * archiving a plan twice leaves the same rows in the same state.
 *
 * THE `CASE` IS THE WHOLE RULE, and it is written over the value AFTER the subtraction rather than
 * before it: «did this plan's reason turn out to be the only one». Two statements would have been
 * the obvious spelling and would have been wrong — the second one, looking for rows with no reasons
 * left, cannot tell a pair this plan just released from a legacy row that has carried an empty list
 * since before `enrollment_sources` existed (there are 294 of those on the owner's own base). One
 * statement, scoped by `@>` to the pairs this plan actually holds, cannot make that mistake.
 */
final readonly class EloquentPlanTermArchiver implements PlanTermArchiver
{
    public function archivePlan(UserId $userId, string $planId): int
    {
        $source = EnrollmentSources::forPlan($planId);

        return DB::update(
            'UPDATE user_term_progress SET '
            . 'enrolled_at = CASE WHEN (enrollment_sources - ?) = \'[]\'::jsonb THEN NULL ELSE enrolled_at END, '
            . 'enrollment_sources = enrollment_sources - ?, '
            . 'updated_at = now() '
            . 'WHERE user_id = ? AND enrollment_sources @> ?::jsonb',
            [$source, $source, $userId->value, json_encode([$source], JSON_THROW_ON_ERROR)],
        );
    }
}
