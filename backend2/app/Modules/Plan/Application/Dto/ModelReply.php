<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** One structured answer from the model, with what it cost and which prompt file asked for it. */
final readonly class ModelReply
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public array $payload,
        public string $promptVersion,
        public string $model,
        public ?int $tokensIn,
        public ?int $tokensOut,
        /** USD, six decimals; `0.000000` when the model is not priced or the call is fake. */
        public string $costUsd,
        public int $latencyMs,
        public string $raw = '',
        /** Of `tokensIn`, what the vendor served from its prompt cache (already priced so in `costUsd`); null when unsaid. */
        public ?int $cachedTokensIn = null,
    ) {}
}
