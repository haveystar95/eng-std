<?php

declare(strict_types=1);

namespace App\Modules\Observability\Application\Dto;

/**
 * What a vendor's answer says it spent (наряд GEN-3): the model that answered, the input tokens, how many of them the vendor
 * served from its prompt cache (OpenAI: `usage.prompt_tokens_details.cached_tokens`), the output tokens, and the price —
 * the app's one pricing table with the cached tokens at their cached rate. A count the vendor did not give is null.
 */
final readonly class ModelCallUsage
{
    public function __construct(
        public string $answeredModel,
        public ?int $tokensIn,
        public ?int $cachedTokensIn,
        public ?int $tokensOut,
        public ?string $costUsd,
    ) {}
}
