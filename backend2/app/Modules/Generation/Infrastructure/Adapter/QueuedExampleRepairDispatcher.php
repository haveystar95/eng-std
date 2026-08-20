<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\DispatchesExampleRepair;
use App\Modules\Generation\Infrastructure\Job\EnrichCollectionJob;
use App\Modules\Generation\Infrastructure\Job\RepairEchoExamplesJob;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\Bus;

final class QueuedExampleRepairDispatcher implements DispatchesExampleRepair
{
    /**
     * A CHAIN, not two dispatches: the second job starts only when the first has finished, which is
     * the whole of audit A2. Two independent dispatches gave Horizon two jobs it was free to run in
     * either order (and, with more than one worker, at the same time), and the order it happened to
     * pick built distractors against examples that did not exist yet.
     *
     * Both switches still apply, and they compose: `auto_repair_examples` off leaves the enrichment
     * alone in the chain (there is then nothing to wait for), `auto_enrich` off leaves the repair
     * alone, and both off queues nothing at all. Off means a term whose example was refused simply
     * has none — which is still better than the echo it replaced.
     */
    public function repairThenEnrich(CollectionId $collectionId, UserId $ownerId, string $generatorVersion): void
    {
        $chain = [];

        if (config('services.generation.auto_repair_examples') === true) {
            $chain[] = new RepairEchoExamplesJob($collectionId->value, $ownerId->value);
        }
        if (config('services.generation.auto_enrich') === true) {
            $chain[] = new EnrichCollectionJob($collectionId->value, $generatorVersion);
        }

        if ($chain === []) {
            return;
        }

        Bus::chain($chain)->dispatch();
    }
}
