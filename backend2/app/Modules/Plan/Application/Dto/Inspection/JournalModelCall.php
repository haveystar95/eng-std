<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto\Inspection;

use DateTimeImmutable;

/**
 * A model call from the journal (`model_calls`), as the plan's page reads it (наряд ADM-1). `logId` — the outbound
 * request-log row of the same call (its body and the model's answer), found by the vendor's usage; null — none answers it
 * exactly.
 */
final readonly class JournalModelCall
{
    public function __construct(
        public string $id,
        public string $status,
        public string $provider,
        public string $model,
        public ?string $answeredModel,
        public ?string $purpose,
        public int $estimatedTokensIn,
        public ?int $tokensIn,
        public ?int $cachedTokens,
        public ?int $tokensOut,
        public ?string $costUsd,
        public ?int $httpStatus,
        public ?string $error,
        public ?int $latencyMs,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt,
        public ?string $logId,
    ) {}
}
