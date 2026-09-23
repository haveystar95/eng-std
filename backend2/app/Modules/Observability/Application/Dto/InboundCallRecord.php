<?php

declare(strict_types=1);

namespace App\Modules\Observability\Application\Dto;

use DateTimeImmutable;

/**
 * One call a client made to our API, as the request log holds it — the metadata only; the redacted bodies and headers are
 * one more read by id (`GET /admin/api/logs/{id}`). `userAgent` is the header as the client sent it; nothing else about
 * the device or the build reaches the log (наряд ADM-1: the app sends no build or device header).
 */
final readonly class InboundCallRecord
{
    public function __construct(
        public string $id,
        public string $method,
        public string $path,
        public ?int $status,
        public ?int $durationMs,
        public ?int $requestBytes,
        public ?int $responseBytes,
        public ?string $userAgent,
        public ?string $error,
        public DateTimeImmutable $occurredAt,
    ) {}
}
