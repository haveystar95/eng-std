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
     * @param  string  $translationLang  the language whose translation the станок reads for each term.
     * @param  bool  $ignoreVersionMark  these ids come from a TOP-UP (chosen by coverage), so the
     *        worker must not re-filter them by the journal — see BuildTermEnrichments.
     */
    public function enrichTerms(
        array $termIds,
        string $generatorVersion,
        string $translationLang = 'ru',
        bool $ignoreVersionMark = false,
    ): void;

    /** Queue a whole collection: resolve its pending terms on the worker, then chunk them. */
    public function enrichCollection(string $collectionId, string $generatorVersion): void;
}
