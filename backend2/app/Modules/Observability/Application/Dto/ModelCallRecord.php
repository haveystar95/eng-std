<?php

declare(strict_types=1);

namespace App\Modules\Observability\Application\Dto;

use DateTimeImmutable;

/**
 * One row of the model call journal as it was written (`model_calls`, наряд GEN-3) — read back for the admin's plan page
 * (наряд ADM-1). `logId` is the outbound request-log row of the same call, found by its usage (see
 * {@see \App\Modules\Observability\Application\Port\CallLogReader::withLogIds()}); null when no row answers it exactly.
 */
final readonly class ModelCallRecord
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
        public int $timeoutSeconds,
        public ?int $latencyMs,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $finishedAt,
        public ?string $logId = null,
    ) {}

    public function withLogId(?string $logId): self
    {
        return new self(
            $this->id, $this->status, $this->provider, $this->model, $this->answeredModel, $this->purpose,
            $this->estimatedTokensIn, $this->tokensIn, $this->cachedTokens, $this->tokensOut, $this->costUsd,
            $this->httpStatus, $this->error, $this->timeoutSeconds, $this->latencyMs, $this->startedAt, $this->finishedAt,
            $logId,
        );
    }
}
