<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use App\Modules\Generation\Application\Port\DispatchesEnrichment;
use App\Modules\Generation\Infrastructure\Adapter\QueuedEnrichmentDispatcher;
use App\Modules\Generation\Infrastructure\Job\EnrichCollectionJob;
use App\Modules\Generation\Infrastructure\Job\EnrichTermsChunkJob;
use Illuminate\Support\Facades\Queue;

/**
 * The auto-chain switch. It governs the AUTOMATIC path only — a human running the console backfill
 * asked for exactly those terms and must not be silently overridden by a flag meant to stop the
 * per-generation cost.
 */
it('queues the collection job when the auto-chain is on', function () {
    config(['services.generation.auto_enrich' => true]);
    Queue::fake();

    app(DispatchesEnrichment::class)->enrichCollection('01COLLECTION0000000000000X', BuildTermEnrichmentsHandler::VERSION);

    Queue::assertPushed(EnrichCollectionJob::class, 1);
});

it('queues nothing when the auto-chain is off — generation behaves as it did before the станок', function () {
    config(['services.generation.auto_enrich' => false]);
    Queue::fake();

    app(DispatchesEnrichment::class)->enrichCollection('01COLLECTION0000000000000X', BuildTermEnrichmentsHandler::VERSION);

    Queue::assertNothingPushed();
});

it('still queues an explicit term list with the auto-chain off (the console backfill)', function () {
    config(['services.generation.auto_enrich' => false]);
    Queue::fake();

    app(DispatchesEnrichment::class)->enrichTerms(['01TERM000000000000000000A'], BuildTermEnrichmentsHandler::VERSION);

    Queue::assertPushed(EnrichTermsChunkJob::class, 1);
});

it('splits a term list into chunks of CHUNK_SIZE', function () {
    Queue::fake();
    $termIds = array_map(
        static fn (int $i): string => str_pad((string) $i, 26, '0', STR_PAD_LEFT),
        range(1, EnrichTermsChunkJob::CHUNK_SIZE * 2 + 1),
    );

    (new QueuedEnrichmentDispatcher())->enrichTerms($termIds, BuildTermEnrichmentsHandler::VERSION);

    // 41 terms at 20 per chunk = 3 jobs, the last one short.
    Queue::assertPushed(EnrichTermsChunkJob::class, 3);
});
