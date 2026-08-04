<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * The running spend total after a model call, handed to the pipeline's onAttempt callback so a
 * caller can persist it *before* validation runs — a rejected draft still cost tokens. On a top-up
 * the totals are already summed across both calls.
 */
final readonly class AttemptUsage
{
    public function __construct(
        public string $model,
        public ?int $tokensIn,
        public ?int $tokensOut,
        public ?string $costUsd,
        public ?string $rawResponse, // truncated output of THIS call, for failure diagnosis
    ) {}
}
