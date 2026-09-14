<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\ImageResult;
use App\Modules\Generation\Application\Dto\ImageSearchQuery;
use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Generation\Application\Port\TransientImageSearchError;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
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
    /** Re-asks of the transient queries of a batch, after the first round. */
    private const BATCH_RETRIES = 2;

    private const BATCH_BACKOFF_MS = 700;

    public function __construct(
        private readonly OutboundCallContext $context,
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.pexels.com/v1',
        private readonly int $timeoutSeconds = 15,
        private readonly int $throttleMs = 0,
    ) {}

    public function search(string $query): ?ImageResult
    {
        $q = trim($query);
        if ($q === '') {
            return null; // nothing to search — a valid, no-image outcome
        }

        // Space out real network calls to stay clear of Pexels' burst limits. Sits in the adapter
        // (not the caller) so throttling travels with the vendor and the fake stays instant. On a
        // transient throw we skip the sleep — the queue's backoff already spaces the retry.
        if ($this->throttleMs > 0) {
            usleep($this->throttleMs * 1000);
        }

        try {
            $response = $this->context->run('images', null, fn () => Http::withHeaders(['Authorization' => $this->apiKey])
                ->timeout($this->timeoutSeconds)
                ->get(rtrim($this->baseUrl, '/') . '/search', [
                    'query' => $q,
                    'per_page' => 1,
                    'orientation' => 'landscape',
                ]));
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

        return $this->result($photo);
    }

    /**
     * The day's photos together (DAY-UI-3): one pool of requests, `$concurrency` on the wire at once.
     * A 429, a 5xx or a dropped connection is asked again — only those queries — twice, with backoff;
     * what is still transient after that fails the batch as {@see TransientImageSearchError}.
     */
    public function searchMany(array $queries, int $concurrency = 6): array
    {
        $results = array_fill(0, count($queries), null);
        /** @var array<int, ImageSearchQuery> $pending */
        $pending = array_filter($queries, static fn (ImageSearchQuery $q): bool => trim($q->query) !== '');

        for ($attempt = 0; $pending !== []; $attempt++) {
            $responses = $this->context->run('images', null, fn (): array => Http::pool(function (Pool $pool) use ($pending): array {
                $requests = [];
                foreach ($pending as $i => $q) {
                    $requests[] = $pool->as('q'.$i)
                        ->withHeaders(['Authorization' => $this->apiKey])
                        ->timeout($this->timeoutSeconds)
                        ->get(rtrim($this->baseUrl, '/').'/search', [
                            'query' => trim($q->query),
                            'per_page' => 1,
                            'page' => max(1, $q->page),
                            'orientation' => 'landscape',
                        ]);
                }

                return $requests;
            }, max(1, $concurrency)));

            $again = [];
            $transient = null;
            foreach ($pending as $i => $q) {
                $response = $responses['q'.$i] ?? null;
                if (! $response instanceof Response) {
                    $again[$i] = $q;
                    $transient = TransientImageSearchError::network($response instanceof \Throwable ? $response->getMessage() : 'no response');

                    continue;
                }
                if ($response->status() === 429 || $response->serverError()) {
                    $again[$i] = $q;
                    $transient = $response->status() === 429
                        ? TransientImageSearchError::rateLimited($this->retryAfter($response))
                        : TransientImageSearchError::upstream($response->status());

                    continue;
                }
                if (! $response->successful()) {
                    throw new RuntimeException('Pexels API error: '.$response->status().' '.$response->body());
                }
                $photo = $response->json('photos.0');
                $results[$i] = is_array($photo) ? $this->result($photo) : null;
            }

            if ($again === []) {
                break;
            }
            if ($attempt >= self::BATCH_RETRIES) {
                throw $transient ?? TransientImageSearchError::network('no response');
            }
            usleep(self::BATCH_BACKOFF_MS * (2 ** $attempt) * 1000);
            $pending = $again;
        }

        return array_values($results);
    }

    public function photo(string $photoId): ?ImageResult
    {
        if (preg_match('/^\d+$/', $photoId) !== 1) {
            return null; // not a Pexels id — nothing to ask for
        }

        try {
            $response = $this->context->run('images', null, fn () => Http::withHeaders(['Authorization' => $this->apiKey])
                ->timeout($this->timeoutSeconds)
                ->get(rtrim($this->baseUrl, '/') . '/photos/' . $photoId));
        } catch (ConnectionException $e) {
            throw TransientImageSearchError::network($e->getMessage());
        }

        if ($response->status() === 404) {
            return null; // the photo is gone — terminal, like an empty search
        }
        if ($response->status() === 429) {
            throw TransientImageSearchError::rateLimited($this->retryAfter($response));
        }
        if ($response->serverError()) {
            throw TransientImageSearchError::upstream($response->status());
        }
        if (! $response->successful()) {
            throw new RuntimeException('Pexels API error: ' . $response->status() . ' ' . $response->body());
        }

        $photo = $response->json();

        return is_array($photo) ? $this->result($photo) : null;
    }

    /** @param array<string, mixed> $photo */
    private function result(array $photo): ?ImageResult
    {
        $url = $this->pickUrl($photo);
        if ($url === null) {
            return null;
        }

        return new ImageResult(
            url: $url,
            author: is_string($photo['photographer'] ?? null) ? $photo['photographer'] : null,
            authorUrl: is_string($photo['photographer_url'] ?? null) ? $photo['photographer_url'] : null,
            avgColor: is_string($photo['avg_color'] ?? null) ? $photo['avg_color'] : null,
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

    private function retryAfter(Response $response): ?int
    {
        $header = $response->header('Retry-After');

        return is_numeric($header) ? (int) $header : null;
    }
}
