<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\PlanTermSweepStore;
use App\Modules\Learning\Domain\ValueObject\EnrollmentSources;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Support\Facades\DB;

final readonly class EloquentPlanTermSweepStore implements PlanTermSweepStore
{
    public function plansByStatus(array $statuses): array
    {
        $rows = DB::table('learning_plans')
            ->whereIn('status', $statuses)
            ->orderBy('user_id')
            ->orderBy('created_at')
            ->get(['id', 'user_id', 'title', 'status']);

        return array_values($rows->map(static fn (object $r): array => [
            'user_id' => (string) $r->user_id,
            'plan_id' => (string) $r->id,
            'title' => (string) $r->title,
            'status' => (string) $r->status,
        ])->all());
    }

    public function dayCollectionIds(string $planId): array
    {
        return array_values(array_map('strval', DB::table('learning_plan_days')
            ->where('plan_id', $planId)
            ->whereNotNull('collection_id')
            ->pluck('collection_id')
            ->all()));
    }

    public function archivableTerms(UserId $userId, string $enrollmentSource, array $termIds): array
    {
        $rows = DB::table('user_term_progress')
            ->where('user_id', $userId->value)
            ->whereNotNull('enrolled_at')
            // A reason of the learner's own — «Учить это слово», or a «не знаю» swipe — did not end
            // with anybody's plan, so the pair stays whatever happened to the plan.
            ->whereRaw(
                'NOT jsonb_exists_any(enrollment_sources, ARRAY[?, ?])',
                [EnrollmentSources::MANUAL, EnrollmentSources::TRIAGE],
            )
            ->where(static function (BuilderContract $q) use ($enrollmentSource, $termIds): void {
                // `jsonb_exists` is the function spelling of the jsonb `?` operator, which cannot
                // travel through a query builder at all — `?` is the parameter placeholder.
                $q->whereRaw('jsonb_exists(enrollment_sources, ?)', [$enrollmentSource]);
                if ($termIds !== []) {
                    $q->orWhereIn('term_id', $termIds);
                }
            })
            ->pluck('term_id');

        return array_values(array_map('strval', $rows->all()));
    }

    public function unenroll(UserId $userId, array $termIds): int
    {
        if ($termIds === []) {
            return 0;
        }

        return DB::table('user_term_progress')
            ->where('user_id', $userId->value)
            ->whereIn('term_id', $termIds)
            // The reasons go with the enrolment: «no reasons whenever the pair is out of the pool»
            // is the entity's invariant ({@see \App\Modules\Learning\Domain\Entity\TermProgress}).
            ->update(['enrolled_at' => null, 'enrollment_sources' => '[]', 'updated_at' => now()]);
    }
}
