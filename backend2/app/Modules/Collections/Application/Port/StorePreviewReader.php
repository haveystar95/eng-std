<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Port;

use App\Modules\Collections\Application\Dto\StoreCollectionPreview;
use App\Modules\Shared\Domain\ValueObject\CollectionId;

/**
 * A taster of a store collection: its first `limit` terms (learned form + native translation) plus
 * the total item count. Returns null when the id is not a store collection (public/system) — the
 * caller turns that into a 404, so private/custom collections stay hidden. No tier gate: previewing
 * is free even for premium collections.
 */
interface StorePreviewReader
{
    public function preview(CollectionId $collectionId, int $limit): ?StoreCollectionPreview;
}
