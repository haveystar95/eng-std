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
     * @param list<DueTermView> $dueTerms
     * @param list<DueTermView> $newTerms
     */
    public function __construct(
        private readonly array $dueTerms = [],
        private readonly array $newTerms = [],
    ) {}

    public function due(UserId $userId, DateTimeImmutable $now, int $limit): array
    {
        $this->dueLimits[] = $limit;

        return array_slice($this->dueTerms, 0, $limit);
    }

    public function newTerms(UserId $userId, int $limit): array
    {
        return array_slice($this->newTerms, 0, $limit);
    }
}
