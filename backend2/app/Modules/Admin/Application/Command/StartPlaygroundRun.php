<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Command;

/**
 * Send one prompt to one model, verbatim — as a run the screen polls (наряд GEN-3). A Command: it creates the run and
 * returns its id; the call itself is made by a queued job, never inside the admin's request.
 */
final readonly class StartPlaygroundRun
{
    public function __construct(
        public string $provider,
        public string $model,
        public string $prompt,
        public ?float $temperature = null,
    ) {}
}
