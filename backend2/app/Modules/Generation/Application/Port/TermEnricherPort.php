<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\TermEnrichmentBrief;
use App\Modules\Generation\Application\Dto\TermEnrichmentResult;

/**
 * Fills in a bare user term with a translation, transcription, example and image query via the LLM.
 * The implementation (OpenAI) lives in Infrastructure; tests bind a fake. A transient/API failure
 * throws (the job retries); the versioned prompt file mirrors the collection generator's approach.
 */
interface TermEnricherPort
{
    public function enrich(TermEnrichmentBrief $brief): TermEnrichmentResult;
}
