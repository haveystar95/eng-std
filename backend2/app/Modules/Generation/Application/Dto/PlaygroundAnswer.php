<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * One sandbox round-trip as the screen shows it: the text, the JSON if it was JSON, the reason it
 * was not, and what the call cost.
 *
 * `error` and `parseError` are different failures and are kept apart. `error` means the vendor never
 * gave an answer (auth, credits, timeout, a refusal) — there is nothing to read. `parseError` means
 * the model answered fine and the answer simply is not JSON, which is an ordinary and interesting
 * result in a prompt sandbox, not a fault.
 */
final readonly class PlaygroundAnswer
{
    /** @param  array<mixed>|null  $parsedJson */
    public function __construct(
        public string $provider,
        public string $model,
        public string $rawText,
        public ?array $parsedJson,
        public ?string $parseError,
        public ?int $tokensIn,
        public ?int $tokensOut,
        /** Priced by the app's ONE pricing table; null = the model is not in it, never "free". */
        public ?string $costUsd,
        public int $latencyMs,
        public ?string $error = null,
    ) {}
}
