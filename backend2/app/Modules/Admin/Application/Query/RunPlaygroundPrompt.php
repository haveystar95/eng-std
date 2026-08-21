<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

/**
 * Send one prompt to one model, verbatim.
 *
 * A Query rather than a Command because it changes nothing anywhere — it does SPEND, which the
 * request log records like every other outbound call, but the app's state before and after is
 * identical. The naming follows the module's own rule (Commands mutate and return ids).
 */
final readonly class RunPlaygroundPrompt
{
    public function __construct(
        public string $provider,
        public string $model,
        public string $prompt,
        public ?float $temperature = null,
    ) {}
}
