<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * One sandbox call as the panel renders it.
 *
 * `error` (the vendor never answered) and `parseError` (it answered, just not in JSON) are separate
 * fields because they are separate facts and lead to different next moves — one is a key or a bill,
 * the other is a prompt.
 */
final readonly class PlaygroundResult
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
        public ?string $costUsd,
        public int $latencyMs,
        public ?string $error,
    ) {}
}
