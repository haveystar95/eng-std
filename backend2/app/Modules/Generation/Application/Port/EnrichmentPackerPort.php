<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\EnrichmentBrief;
use App\Modules\Generation\Application\Dto\EnrichmentPack;

/**
 * Produces one term's enrichment pack via the LLM. The implementation (OpenAI, structured outputs)
 * lives in Infrastructure; tests bind a deterministic fake. A transient/API failure throws so the
 * job retries the chunk — and the per-term version mark means the retry skips whatever already
 * landed, so a retry never re-pays for a finished term.
 */
interface EnrichmentPackerPort
{
    public function pack(EnrichmentBrief $brief): EnrichmentPack;
}
