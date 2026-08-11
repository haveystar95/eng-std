<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * One row of the API request/response log (inbound client calls + outbound external calls).
 *
 * `model` / `tokens*` / `costUsd` are DERIVED at read time from the stored bodies, not extra
 * columns: the usage block is already in the response we logged, so pulling it out costs nothing
 * and — unlike new columns — works for every row ever written, including the ones logged before
 * this screen existed.
 */
final readonly class RequestLogRow
{
    public function __construct(
        public string $id,
        public string $direction,   // inbound | outbound
        public string $method,
        public ?string $host,
        public string $path,
        public ?string $service,
        public ?string $purpose,    // generation | images | enrichment | realtime | recap | example_regen
        public ?string $collectionId,
        public ?int $status,
        public ?int $durationMs,
        public ?string $userId,
        public ?string $occurredAt,
        public ?string $model = null,
        public ?int $tokensIn = null,
        public ?int $tokensOut = null,
        public ?float $costUsd = null,
        public ?string $error = null,
    ) {}
}
