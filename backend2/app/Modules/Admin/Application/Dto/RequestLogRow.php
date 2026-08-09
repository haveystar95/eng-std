<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** One row of the API request/response log (inbound client calls + outbound external calls). */
final readonly class RequestLogRow
{
    public function __construct(
        public string $id,
        public string $direction,   // inbound | outbound
        public string $method,
        public ?string $host,
        public string $path,
        public ?string $service,
        public ?int $status,
        public ?int $durationMs,
        public ?string $userId,
        public ?string $occurredAt,
    ) {}
}
