<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\PlanTermSweepStore;
use App\Modules\Learning\Domain\ValueObject\EnrollmentSources;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

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

    public function learnersWithPlanMarker(): array
    {
        return array_values(array_map('strval', DB::table('user_term_progress')
            ->whereNotNull('enrolled_at')
            ->whereRaw(<<<'SQL'
                EXISTS (SELECT 1 FROM jsonb_array_elements_text(enrollment_sources) AS s(v) WHERE s.v LIKE 'plan:%')
                SQL)
            ->distinct()
            ->pluck('user_id')
            ->all()));
    }

    public function pooledPairsWithPlanMarker(UserId $userId): array
    {
        return $this->hydrate(DB::table('user_term_progress')
            ->where('user_id', $userId->value)
            ->whereNotNull('enrolled_at')
            // A `plan:` marker, whichever plan it names — the caller decides which of them are over.
            //
            // An EXISTS over the expanded array, and NOT `jsonb_path_exists(…, '$[*] ? (@ starts
            // with "plan:")')`: jsonpath's filter operator is `?`, which PDO reads as a parameter
            // placeholder and eats. It is the same trap the jsonb `?` operator sets one level up,
            // and it fails silently — the first draft of this method matched nothing and threw
            // nothing. A LIKE over the whole column's text would have its own bug: the column is an
            // ARRAY, so it would match a hand-saved word whose own text contained «plan:».
            ->whereRaw(<<<'SQL'
                EXISTS (SELECT 1 FROM jsonb_array_elements_text(enrollment_sources) AS s(v) WHERE s.v LIKE 'plan:%')
                SQL)
            ->get(['term_id', 'enrollment_sources']));
    }

    public function pooledPairsWithoutMarker(UserId $userId, array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        return $this->hydrate(DB::table('user_term_progress')
            ->where('user_id', $userId->value)
            ->whereNotNull('enrolled_at')
            ->whereIn('term_id', $termIds)
            ->whereRaw(<<<'SQL'
                NOT EXISTS (SELECT 1 FROM jsonb_array_elements_text(enrollment_sources) AS s(v) WHERE s.v LIKE 'plan:%')
                SQL)
            ->get(['term_id', 'enrollment_sources']));
    }

    public function stripSources(UserId $userId, array $termIds, array $sources): int
    {
        if ($termIds === [] || $sources === []) {
            return 0;
        }

        // `enrollment_sources - ?` is jsonb array-minus-element, nested once per marker: it removes
        // the string if it is there and returns the array unchanged if it is not, so this is
        // idempotent for free and cannot touch a reason it was not asked about.
        //
        // Hand-written rather than `->update(DB::raw(…))`, because the builder's update does not
        // carry bindings into a raw column expression — the markers have to be BOUND, and a marker
        // interpolated into SQL is a ULID today and something else the first time it is not.
        $expression = 'enrollment_sources';
        foreach ($sources as $_) {
            $expression = "({$expression} - ?)";
        }
        $terms = implode(', ', array_fill(0, count($termIds), '?'));

        return DB::update(
            "UPDATE user_term_progress SET enrollment_sources = {$expression}, updated_at = now() "
            . "WHERE user_id = ? AND term_id IN ({$terms})",
            [...$sources, $userId->value, ...$termIds],
        );
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

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return list<array{term_id: string, sources: list<string>}>
     */
    private function hydrate(Collection $rows): array
    {
        return array_values($rows->map(static function (stdClass $row): array {
            /** @var array<string, mixed> $r */
            $r = (array) $row;
            $decoded = json_decode((string) $r['enrollment_sources'], true);

            return [
                'term_id' => (string) $r['term_id'],
                'sources' => EnrollmentSources::fromArray(is_array($decoded) ? $decoded : [])->sources,
            ];
        })->all());
    }
}
