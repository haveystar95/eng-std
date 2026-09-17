<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** What a sandbox run's job is given: the run to write to, and the call exactly as the operator asked for it. */
final readonly class PlaygroundRunRequest
{
    public function __construct(
        public string $runId,
        public string $provider,
        public string $model,
        public string $prompt,
        public ?float $temperature,
    ) {}
}
