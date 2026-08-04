<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\ImageResult;

/**
 * Finds one stock photo for a short search query. The implementation (Pexels) lives in
 * Infrastructure; nothing outside Infrastructure/Adapter knows the vendor. Tests bind a fake.
 *
 * Contract:
 * - a genuine "no photo matches this query" is a null return — a normal, terminal outcome the
 *   caller must NOT retry (it would just return null again);
 * - a transient failure (rate limit, 5xx, network) throws {@see TransientImageSearchError}, which
 *   the caller is expected to retry with backoff.
 */
interface ImageSearchPort
{
    /**
     * @throws TransientImageSearchError on a retryable failure (rate limit / upstream 5xx / network)
     */
    public function search(string $query): ?ImageResult;
}
