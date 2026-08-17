<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Generation\Application\Port\DispatchesExampleRepair;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Records what the echo-example repair WOULD have been asked to do, instead of queuing it — so a
 * test can assert the post-generation chain fired without a queue, a worker or a model.
 */
final class RecordingExampleRepairDispatcher implements DispatchesExampleRepair
{
    /** @var list<array{collection_id: string, owner_id: string}> */
    public array $collections = [];

    public function repairCollection(CollectionId $collectionId, UserId $ownerId): void
    {
        $this->collections[] = ['collection_id' => $collectionId->value, 'owner_id' => $ownerId->value];
    }
}
