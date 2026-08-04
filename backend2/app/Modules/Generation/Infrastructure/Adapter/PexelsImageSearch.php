<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\ImageResult;
use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Generation\Application\Port\TransientImageSearchError;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pexels image search. One landscape photo per query, chosen for a card/cover crop. The API key
 * goes in the Authorization header (Pexels' scheme — no "Bearer"). Failures are classified so the
 * caller knows whether to retry: 429 and 5xx and network errors are transient (throw), a genuine
 * empty result is null (no retry), and anything else (e.g. a bad key → 401) is a hard config error.
 *
 * @see https://www.pexels.com/api/documentation/
 */
final class PexelsImageSearch implements ImageSearchPort
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.pexels.com/v1',
        private readonly int $timeoutSeconds = 15,
    ) {}

    public function search(string $query): ?ImageResult
    {
        $q = trim($query);
        if ($q === '') {
            return null; // nothing to search — a valid, no-image outcome
        }

        try {
            $response = Http::withHeaders(['Authorization' => $this->apiKey])
                ->timeout($this->timeoutSeconds)
                ->get(rtrim($this->baseUrl, '/') . '/search', [
                    'query' => $q,
                    'per_page' => 1,
                    'orientation' => 'landscape',
                ]);
        } catch (ConnectionException $e) {
            throw TransientImageSearchError::network($e->getMessage());
        }

        if ($response->status() === 429) {
            throw TransientImageSearchError::rateLimited($this->retryAfter($response));
        }
        if ($response->serverError()) {
            throw TransientImageSearchError::upstream($response->status());
        }
        if (! $response->successful()) {
            // 401/403/400 etc. — not transient; a retry won't fix a bad key. Fail loudly.
            throw new RuntimeException('Pexels API error: ' . $response->status() . ' ' . $response->body());
        }

        $photo = $response->json('photos.0');
        if (! is_array($photo)) {
            return null; // no photo matched — normal, do not retry
        }

        $url = $this->pickUrl($photo);
        if ($url === null) {
            return null;
        }

        return new ImageResult(
            url: $url,
            author: is_string($photo['photographer'] ?? null) ? $photo['photographer'] : null,
            authorUrl: is_string($photo['photographer_url'] ?? null) ? $photo['photographer_url'] : null,
        );
    }

    /** @param array<string, mixed> $photo */
    private function pickUrl(array $photo): ?string
    {
        $src = $photo['src'] ?? null;
        if (! is_array($src)) {
            return null;
        }

        // Prefer a ready landscape crop, then progressively larger stills, then the original.
        foreach (['landscape', 'large2x', 'large', 'medium', 'original'] as $size) {
            if (is_string($src[$size] ?? null) && $src[$size] !== '') {
                return $src[$size];
            }
        }

        return null;
    }

    private function retryAfter(\Illuminate\Http\Client\Response $response): ?int
    {
        $header = $response->header('Retry-After');

        return is_numeric($header) ? (int) $header : null;
    }
}
