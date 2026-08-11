<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

/**
 * Hands enrichment work to the queue. A port rather than a direct dispatch so the console command
 * and the post-generation chain both stay clear of Infrastructure (Presentation may not reach it at
 * all), and so tests can assert "it would have queued this" without a queue.
 */
interface DispatchesEnrichment
{
    /**
     * Chunk and queue these terms. Chunking is the adapter's business — the caller knows the work,
     * not the batch size.
     *
     * @param  list<string>  $termIds
     */
    public function enrichTerms(array $termIds, string $generatorVersion): void;

    /** Queue a whole collection: resolve its pending terms on the worker, then chunk them. */
    public function enrichCollection(string $collectionId, string $generatorVersion): void;
}
