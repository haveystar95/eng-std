<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto\Inspection;

use DateTimeImmutable;

/** A call the learner's client made to the plan's routes, from the request log — metadata only. */
final readonly class JournalClientCall
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
