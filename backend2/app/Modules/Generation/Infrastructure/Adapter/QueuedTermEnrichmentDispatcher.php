<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Infrastructure\Job\EnrichTermJob;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Port\DispatchesTermEnrichment;

/**
 * Implements Vocabulary's enrichment-dispatch port with the Generation queue job — so Vocabulary
 * (and its callers, e.g. the add-word flow) can ask for enrichment without knowing about the LLM
 * or the queue. Bound in the Generation service provider.
 */
final class QueuedTermEnrichmentDispatcher implements DispatchesTermEnrichment
{
    public function enrich(TermId $termId, LanguageCode $translationLang): void
    {
        EnrichTermJob::dispatch($termId->value, $translationLang->value);
    }
}
