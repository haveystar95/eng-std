<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Query;

use App\Modules\Collections\Application\Dto\StoreCollectionPage;
use App\Modules\Collections\Application\Port\StoreCollectionsReader;

final readonly class GetStoreCollectionsHandler
{
    public function __construct(private StoreCollectionsReader $store) {}

    public function __invoke(GetStoreCollections $query): StoreCollectionPage
    {
        return $this->store->forLanguagePair(
            $query->viewer,
            $query->sourceLang,
            $query->targetLang,
            $query->cursor,
            max(1, min($query->limit, 100)),
        );
    }
}
