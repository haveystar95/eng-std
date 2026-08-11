<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * What slice of the request log to show. Every field is optional and they AND together; an empty
 * filter set is "everything, newest first".
 *
 * `search` is a substring match against the stored request/response bodies — the question it
 * answers ("which call contained this word?") is the one a body-less log can never answer.
 */
final readonly class LogFilters
{
    public function __construct(
        public ?string $direction = null,     // inbound | outbound
        public ?string $provider = null,      // the `service` tag: openai | pexels | gemini
        public ?int $status = null,
        public ?string $statusClass = null,   // '2xx' | '4xx' | '5xx' | 'error' — coarser than $status
        public ?string $purpose = null,
        public ?string $userId = null,
        public ?string $collectionId = null,
        public ?string $from = null,          // ISO datetime, inclusive lower bound on occurred_at
        public ?string $to = null,            // ISO datetime, inclusive upper bound
        public ?string $path = null,
        public ?string $search = null,        // substring in request/response body
    ) {}
}
