<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\DueTermsReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

final class InMemoryDueTermsReader implements DueTermsReader
{
    /** @var list<int> the limit passed to each due() call, for asserting the session cap */
    public array $dueLimits = [];

    /**
     * @param  list<DueTermView>  $dueTerms  the due projection
     * @param  list<DueTermView>|null  $allTerms  every progress row (any state, ignoring due_at)
     *                                            for {@see allAmong}; defaults to [$dueTerms]
     */
    public function __construct(
        private readonly array $dueTerms = [],
        private readonly ?array $allTerms = null,
    ) {}

    public function dueAmong(UserId $userId, DateTimeImmutable $now, array $termIds, int $limit): array
    {
        $this->dueLimits[] = $limit;
        $set = array_flip($termIds);
        $filtered = array_values(array_filter($this->dueTerms, static fn ($v): bool => isset($set[$v->termId->value])));

        return array_slice($filtered, 0, $limit);
    }

    public function allAmong(UserId $userId, array $termIds, int $limit): array
    {
        $set = array_flip($termIds);
        $pool = $this->allTerms ?? $this->dueTerms;
        $filtered = array_values(array_filter($pool, static fn ($v): bool => isset($set[$v->termId->value])));

        return array_slice($filtered, 0, $limit);
    }
}
