<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Query;

use App\Modules\Shared\Domain\ValueObject\CollectionId;

/**
 * The cover-image search query for a collection that still needs one — non-null only when the
 * collection has no image_url yet but does carry a non-empty image_api_prompt. Null otherwise, so
 * the attach job neither re-searches an imaged collection nor searches one the model gave no query.
 */
interface PendingCollectionImageReader
{
    public function pendingFor(CollectionId $collectionId): ?string;
}
