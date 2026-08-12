<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Job;

use App\Modules\Generation\Application\Query\ListPendingEnrichmentTargets;
use App\Modules\Generation\Application\Query\ListPendingEnrichmentTargetsHandler;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fans one collection out into enrichment chunks. Separate from the generation job for the same
 * reason AttachImagesJob is: the станок is a best-effort follow-up, and it must never be able to fail
 * or delay the collection the user is waiting for. If this never runs, the collection is simply a
 * collection without variants or distractors — every mode that exists today still works.
 *
 * Cheap and idempotent: it only lists and dispatches. Re-running it after a partial run dispatches
 * only what is still unmarked.
 */
final class EnrichCollectionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [10, 60, 180];

    public function __construct(
        private readonly string $collectionId,
        private readonly string $generatorVersion,
    ) {}

    public function handle(ListPendingEnrichmentTargetsHandler $pending): void
    {
        $termIds = $pending(new ListPendingEnrichmentTargets(
            [CollectionId::fromString($this->collectionId)],
            $this->generatorVersion,
        ));

        foreach (array_chunk($termIds, EnrichTermsChunkJob::CHUNK_SIZE) as $chunk) {
            // Carry the collection through so each chunk's model calls land in the log labelled
            // with the deck that caused them.
            EnrichTermsChunkJob::dispatch($chunk, $this->generatorVersion, $this->collectionId);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::warning('EnrichCollectionJob failed; collection keeps no distractors/variants', [
            'collection_id' => $this->collectionId,
            'generator_version' => $this->generatorVersion,
            'error' => $e->getMessage(),
        ]);
    }
}
