<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * One log row with its full payloads. Bodies were redacted on WRITE (the log table never holds a
 * credential), so this hands back exactly what is stored — the panel pretty-prints it.
 */
final readonly class RequestLogDetail
{
    /**
     * @param  array<mixed>|null  $requestHeaders
     * @param  array<mixed>|null  $requestBody
     * @param  array<mixed>|null  $responseBody
     */
    public function __construct(
        public RequestLogRow $row,
        public ?int $requestBytes,
        public ?int $responseBytes,
        public ?array $requestHeaders,
        public ?array $requestBody,
        public ?array $responseBody,
    ) {}
}
