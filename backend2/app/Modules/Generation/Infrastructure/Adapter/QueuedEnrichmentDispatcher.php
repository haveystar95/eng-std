<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\DispatchesEnrichment;
use App\Modules\Generation\Infrastructure\Job\EnrichCollectionJob;
use App\Modules\Generation\Infrastructure\Job\EnrichTermsChunkJob;

final class QueuedEnrichmentDispatcher implements DispatchesEnrichment
{
    /**
     * An explicit list of terms — always queued. This is the console backfill's path: a human asked
     * for exactly these terms, so the automatic-chain switch has no say in it.
     */
    public function enrichTerms(
        array $termIds,
        string $generatorVersion,
        string $translationLang = 'ru',
        bool $ignoreVersionMark = false,
    ): void {
        foreach (array_chunk($termIds, EnrichTermsChunkJob::CHUNK_SIZE) as $chunk) {
            EnrichTermsChunkJob::dispatch($chunk, $generatorVersion, null, $translationLang, $ignoreVersionMark);
        }
    }

    /**
     * The post-generation chain, and the one thing `services.generation.auto_enrich` governs: it adds
     * about one model call per term to EVERY generation, so it has to be switchable without a deploy.
     * Off = generation behaves exactly as it did before the станок existed.
     */
    public function enrichCollection(string $collectionId, string $generatorVersion): void
    {
        if (config('services.generation.auto_enrich') !== true) {
            return;
        }

        EnrichCollectionJob::dispatch($collectionId, $generatorVersion);
    }
}
