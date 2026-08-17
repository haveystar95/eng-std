<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Generation\Application\Port\DispatchesEnrichment;

/**
 * Records what the enrichment станок WOULD have been asked to do, instead of queuing it — so a test
 * can assert the post-generation chain fired without a queue, a worker or a model.
 */
final class RecordingEnrichmentDispatcher implements DispatchesEnrichment
{
    /** @var list<array{collection_id: string, version: string}> */
    public array $collections = [];

    /** @var list<array{term_ids: list<string>, version: string, lang: string}> */
    public array $terms = [];

    public function enrichTerms(array $termIds, string $generatorVersion, string $translationLang = 'ru'): void
    {
        $this->terms[] = ['term_ids' => $termIds, 'version' => $generatorVersion, 'lang' => $translationLang];
    }

    public function enrichCollection(string $collectionId, string $generatorVersion): void
    {
        $this->collections[] = ['collection_id' => $collectionId, 'version' => $generatorVersion];
    }
}
