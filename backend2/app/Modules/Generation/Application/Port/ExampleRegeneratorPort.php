<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\ExampleRegenBrief;
use App\Modules\Generation\Application\Dto\ExampleRegenResult;

/**
 * Produces one fresh example sentence for a term, avoiding the current one. OpenAI in Infrastructure,
 * a fake in tests; versioned prompt file. A failed call throws (surfaced to the caller as a 502-ish).
 */
interface ExampleRegeneratorPort
{
    public function regenerate(ExampleRegenBrief $brief): ExampleRegenResult;
}
