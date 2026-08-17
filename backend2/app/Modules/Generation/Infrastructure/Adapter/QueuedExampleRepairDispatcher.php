<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\DispatchesExampleRepair;
use App\Modules\Generation\Infrastructure\Job\RepairEchoExamplesJob;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class QueuedExampleRepairDispatcher implements DispatchesExampleRepair
{
    /**
     * Governed by the same switch as the enrichment chain: both add model calls to every generation,
     * and both must be stoppable without a deploy. Off means a term whose example was refused simply
     * has none — which is still better than the echo it replaced.
     */
    public function repairCollection(CollectionId $collectionId, UserId $ownerId): void
    {
        if (config('services.generation.auto_repair_examples') !== true) {
            return;
        }

        RepairEchoExamplesJob::dispatch($collectionId->value, $ownerId->value);
    }
}
