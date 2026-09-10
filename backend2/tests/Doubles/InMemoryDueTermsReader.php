<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\DueTermsReader;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * The pool projection, in memory. Everything handed to `$dueTerms` / `$allTerms` is by definition
 * ENROLLED — those methods only ever speak about pool pairs, so a test that wants a term left out
 * of the pool simply does not list it there.
 *
 * `$outOfPool` is the other population, and it exists for one caller: {@see allInScope()}, the read
 * behind a collection's free practice, which drills the topic and not the queue. Rows listed there
 * come back tagged `inPool: false` — a PAUSED word, or one that has a progress row for some other
 * reason but is not being studied.
 */
final class InMemoryDueTermsReader implements DueTermsReader
{
    /** @var list<int> the limit passed to each selectableInPool() call, for asserting the session cap */
    public array $dueLimits = [];

    /** @var list<int> the limit passed to each introductionsInPool() call — the daily new-term quota */
    public array $introLimits = [];

    /**
     * @param  list<DueTermView>  $dueTerms  the selectable projection (ladder rows + due rows)
     * @param  list<DueTermView>|null  $allTerms  every pool row (any state, ignoring due_at)
     *                                            for {@see allInPool}; defaults to [$dueTerms]
     * @param  list<DueTermView>  $outOfPool  rows in scope that are NOT enrolled — {@see allInScope}
     *                                        only. Tagged `inPool: false` on the way out.
     */
    public function __construct(
        private readonly array $dueTerms = [],
        private readonly ?array $allTerms = null,
        private readonly array $outOfPool = [],
    ) {}

    public function selectableInPool(UserId $userId, DateTimeImmutable $now, ?array $termIds, int $limit): array
    {
        $this->dueLimits[] = $limit;

        // Rung-0 pairs are the OTHER method's population — they cost the daily quota and are read
        // under it, exactly as the real reader splits them.
        $rows = array_values(array_filter(
            $this->scoped($this->dueTerms, $termIds),
            static fn (DueTermView $v): bool => $v->acquisition !== Acquisition::New,
        ));

        return array_slice($rows, 0, $limit);
    }

    public function introductionsInPool(UserId $userId, DateTimeImmutable $now, ?array $termIds, int $limit): array
    {
        $this->introLimits[] = $limit;
        if ($limit <= 0) {
            return [];
        }

        $rows = array_values(array_filter(
            $this->scoped($this->dueTerms, $termIds),
            static fn (DueTermView $v): bool => $v->acquisition === Acquisition::New,
        ));

        return array_slice($rows, 0, $limit);
    }

    public function allInPool(UserId $userId, ?array $termIds, int $limit): array
    {
        return array_slice($this->scoped($this->allTerms ?? $this->dueTerms, $termIds), 0, $limit);
    }

    public function allInScope(UserId $userId, array $termIds, int $limit): array
    {
        $pool = array_map(
            static fn (DueTermView $v): DueTermView => new DueTermView(
                $v->termId, $v->state, $v->intervalDays, $v->dueAt, $v->reps,
                $v->acquisition, $v->learningStep, $v->successfulReviews, inPool: true,
            ),
            $this->scoped($this->allTerms ?? $this->dueTerms, $termIds),
        );
        $outside = array_map(
            static fn (DueTermView $v): DueTermView => new DueTermView(
                $v->termId, $v->state, $v->intervalDays, $v->dueAt, $v->reps,
                $v->acquisition, $v->learningStep, $v->successfulReviews, inPool: false,
            ),
            $this->scoped($this->outOfPool, $termIds),
        );

        return array_slice([...$pool, ...$outside], 0, $limit);
    }

    /**
     * @param  list<DueTermView>  $rows
     * @param  list<string>|null  $termIds
     * @return list<DueTermView>
     */
    private function scoped(array $rows, ?array $termIds): array
    {
        if ($termIds === null) {
            return array_values($rows);
        }
        $set = array_flip($termIds);

        return array_values(array_filter($rows, static fn (DueTermView $v): bool => isset($set[$v->termId->value])));
    }
}
