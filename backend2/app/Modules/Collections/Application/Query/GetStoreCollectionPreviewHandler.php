<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Query;

use App\Modules\Collections\Application\Dto\StoreCollectionPreview;
use App\Modules\Collections\Application\Port\StorePreviewReader;

final readonly class GetStoreCollectionPreviewHandler
{
    public function __construct(private StorePreviewReader $preview) {}

    public function __invoke(GetStoreCollectionPreview $query): ?StoreCollectionPreview
    {
        return $this->preview->preview($query->collectionId, max(1, min($query->limit, 20)));
    }
}
