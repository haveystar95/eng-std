<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\ImageResult;
use App\Modules\Generation\Application\Dto\ImageSearchQuery;
use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Generation\Application\Port\TransientImageSearchError;

/**
 * Deterministic image search — no network. Bound when IMAGE_DRIVER=fake, and instantiated directly
 * in tests to drive each branch of the attach job. The `mode` selects the outcome:
 *   found          → a stable ImageResult derived from the query
 *   not_found      → null (empty result — the caller must not retry)
 *   rate_limited   → throws TransientImageSearchError (retryable)
 *   transient_error→ throws TransientImageSearchError (retryable)
 */
final class FakePexelsImageSearch implements ImageSearchPort
{
    public const FOUND = 'found';
    public const NOT_FOUND = 'not_found';
    public const RATE_LIMITED = 'rate_limited';
    public const TRANSIENT_ERROR = 'transient_error';

    /** Number of times search() was called — lets tests assert throttling / skip behaviour. */
    public int $calls = 0;

    public function __construct(private readonly string $mode = self::FOUND) {}

    public function search(string $query): ?ImageResult
    {
        $this->calls++;
        $q = trim($query);

        return match ($this->mode) {
            self::NOT_FOUND => null,
            self::RATE_LIMITED => throw TransientImageSearchError::rateLimited(1),
            self::TRANSIENT_ERROR => throw TransientImageSearchError::upstream(503),
            default => $q === '' ? null : new ImageResult(
                url: 'https://images.pexels.test/' . md5($q) . '.jpg',
                author: 'Fake Photographer',
                authorUrl: 'https://pexels.test/@fake',
                avgColor: '#' . strtoupper(substr(md5($q), 0, 6)),
            ),
        };
    }

    /** @var list<ImageSearchQuery> every query asked in a batch, in order — what the ladder tests read */
    public array $asked = [];

    /** Batches asked — the parallel photo job asks one per rung of its ladder. */
    public int $batches = 0;

    public function searchMany(array $queries, int $concurrency = 6): array
    {
        $this->batches++;

        return array_map(function (ImageSearchQuery $q): ?ImageResult {
            $this->asked[] = $q;
            $found = $this->search($q->query);

            return $found === null || $q->page <= 1 ? $found : new ImageResult(
                url: str_replace('.jpg', "-p{$q->page}.jpg", $found->url),
                author: $found->author,
                authorUrl: $found->authorUrl,
                avgColor: $found->avgColor,
            );
        }, $queries);
    }

    public function photo(string $photoId): ?ImageResult
    {
        $this->calls++;

        return match ($this->mode) {
            self::NOT_FOUND => null,
            self::RATE_LIMITED => throw TransientImageSearchError::rateLimited(1),
            self::TRANSIENT_ERROR => throw TransientImageSearchError::upstream(503),
            default => preg_match('/^\d+$/', $photoId) !== 1 ? null : new ImageResult(
                url: "https://images.pexels.com/photos/{$photoId}/pexels-photo-{$photoId}.jpeg",
                author: 'Fake Photographer',
                authorUrl: 'https://pexels.test/@fake',
                avgColor: '#978E82',
            ),
        };
    }
}
