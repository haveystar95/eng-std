<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use DateTimeImmutable;

/**
 * The user's generation allowance, for greying the create button before they hit submit.
 * `resetsAt` is an absolute instant (next UTC-day boundary — the quota's own window); the client
 * renders it in device-local time. No per-user timezone is stored, so this stays timezone-free.
 */
final readonly class GenerationQuotaView
{
    public function __construct(
        public int $limit,
        public int $used,
        public int $remaining,
        public DateTimeImmutable $resetsAt,
    ) {}
}
