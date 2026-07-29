<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class InMemoryUserCollectionTermsReader implements UserCollectionTermsReader
{
    /**
     * @param list<string> $termIds
     * @param array<string, list<string>> $byCollection
     */
    public function __construct(
        private readonly array $termIds = [],
        private readonly array $byCollection = [],
    ) {}

    public function termIdsForUser(UserId $userId, int $limit): array
    {
        return array_slice($this->termIds, 0, $limit);
    }

    public function termIdsForCollection(UserId $userId, string $collectionId, int $limit): array
    {
        return array_slice($this->byCollection[$collectionId] ?? [], 0, $limit);
    }

    public function termIdsByCollection(UserId $userId): array
    {
        return $this->byCollection;
    }
}
