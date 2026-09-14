<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\ImageResult;
use App\Modules\Generation\Application\Dto\ImageSearchQuery;

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

    /**
     * Many searches at once — at most `$concurrency` on the wire (DAY-UI-3: a day's photos are asked
     * together, not one after another). One answer per query, in order; a query that stays transient
     * after the adapter's own retries throws {@see TransientImageSearchError} for the whole batch, so
     * the caller's job retries it with what was already written kept.
     *
     * @param  list<ImageSearchQuery>  $queries
     * @return list<ImageResult|null>
     *
     * @throws TransientImageSearchError
     */
    public function searchMany(array $queries, int $concurrency = 6): array;

    /**
     * One photo by the vendor's own id — for a photo found earlier whose details (the average
     * colour) were not kept. Null when the vendor has no such photo; the same transient contract
     * as {@see search()}.
     *
     * @throws TransientImageSearchError on a retryable failure (rate limit / upstream 5xx / network)
     */
    public function photo(string $photoId): ?ImageResult;
}
