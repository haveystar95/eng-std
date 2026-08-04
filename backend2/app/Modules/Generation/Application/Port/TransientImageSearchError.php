<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use RuntimeException;

/**
 * A retryable image-search failure — rate limit, upstream 5xx or a network error. Part of the
 * {@see ImageSearchPort} contract: the adapter throws it, the async attach job retries with
 * backoff. An empty search result is NOT this — that is a null return and must not be retried.
 */
final class TransientImageSearchError extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }

    public static function rateLimited(?int $retryAfterSeconds): self
    {
        return new self('Pexels rate limit hit', $retryAfterSeconds);
    }

    public static function upstream(int $status): self
    {
        return new self("Pexels upstream error: {$status}");
    }

    public static function network(string $detail): self
    {
        return new self("Pexels network error: {$detail}");
    }
}
