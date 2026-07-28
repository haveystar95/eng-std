<?php

declare(strict_types=1);

namespace App\Modules\Observability\Application\Dto;

use DateTimeImmutable;

/**
 * One recorded HTTP call, ready to persist. Bodies/headers here are already redacted
 * by the caller — the writer stores them verbatim.
 */
final readonly class ApiLogEntry
{
    /**
     * @param  array<mixed>|null  $requestHeaders
     * @param  array<mixed>|null  $requestBody
     * @param  array<mixed>|null  $responseBody
     */
    public function __construct(
        public string $direction,           // inbound | outbound
        public string $method,
        public string $path,
        public ?string $host,
        public ?string $service,
        public ?int $status,
        public ?int $durationMs,
        public ?string $userId,
        public ?int $requestBytes,
        public ?int $responseBytes,
        public ?array $requestHeaders,
        public ?array $requestBody,
        public ?array $responseBody,
        public ?string $error,
        public DateTimeImmutable $occurredAt,
    ) {}
}
