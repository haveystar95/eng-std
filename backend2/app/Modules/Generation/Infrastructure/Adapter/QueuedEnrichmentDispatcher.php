<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\DispatchesEnrichment;
use App\Modules\Generation\Infrastructure\Job\EnrichCollectionJob;
use App\Modules\Generation\Infrastructure\Job\EnrichTermsChunkJob;

final class QueuedEnrichmentDispatcher implements DispatchesEnrichment
{
    public function enrichTerms(array $termIds, string $generatorVersion): void
    {
        foreach (array_chunk($termIds, EnrichTermsChunkJob::CHUNK_SIZE) as $chunk) {
            EnrichTermsChunkJob::dispatch($chunk, $generatorVersion);
        }
    }

    public function enrichCollection(string $collectionId, string $generatorVersion): void
    {
        EnrichCollectionJob::dispatch($collectionId, $generatorVersion);
    }
}
