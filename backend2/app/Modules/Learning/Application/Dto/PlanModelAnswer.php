<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * One answered model call, with what it cost beside it.
 *
 * The spend travels WITH the payload rather than being logged somewhere the caller cannot see,
 * because the caller is the one who has to decide whether to pay for a second attempt.
 */
final readonly class PlanModelAnswer
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public array $payload,
        public string $model,
        public ?int $tokensIn,
        public ?int $tokensOut,
        public ?string $costUsd,
        public int $latencyMs,
        public string $promptVersion,
    ) {}
}
