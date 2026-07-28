<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Query;

use App\Modules\Collections\Application\Dto\CollectionPage;
use App\Modules\Collections\Application\Port\UserCollectionsReader;

final readonly class ListUserCollectionsHandler
{
    private const MAX_LIMIT = 100;

    public function __construct(private UserCollectionsReader $reader) {}

    public function __invoke(ListUserCollections $query): CollectionPage
    {
        $limit = max(1, min(self::MAX_LIMIT, $query->limit));

        return $this->reader->forUser($query->userId, $query->cursor, $limit);
    }
}
