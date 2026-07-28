<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class InMemoryUserCollectionTermsReader implements UserCollectionTermsReader
{
    /** @param list<string> $termIds */
    public function __construct(private readonly array $termIds = []) {}

    public function termIdsForUser(UserId $userId, int $limit): array
    {
        return array_slice($this->termIds, 0, $limit);
    }
}
