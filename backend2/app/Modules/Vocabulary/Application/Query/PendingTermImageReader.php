<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Vocabulary\Application\Dto\PendingTermImage;

/**
 * Of the given terms, which still need a photo — i.e. have no image_url yet but do carry a
 * non-empty image_api_prompt to search on. Terms already imaged (including globally-shared ones
 * reused across collections) and terms the model marked un-illustratable are simply not returned,
 * so the attach job never re-searches them.
 */
interface PendingTermImageReader
{
    /**
     * @param  list<string>  $termIds
     * @return list<PendingTermImage>
     */
    public function pendingFor(array $termIds): array;
}
