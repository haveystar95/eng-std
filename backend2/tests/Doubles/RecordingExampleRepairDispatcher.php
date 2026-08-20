<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Generation\Application\Port\DispatchesExampleRepair;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Records what the post-generation follow-up WOULD have been asked to do, instead of queuing it — so
 * a test can assert the chain fired without a queue, a worker or a model. The recorded call is
 * «repair, then enrich», one call, because that ORDER is the thing worth asserting (audit A2).
 */
final class RecordingExampleRepairDispatcher implements DispatchesExampleRepair
{
    /** @var list<array{collection_id: string, owner_id: string, generator_version: string}> */
    public array $collections = [];

    public function repairThenEnrich(CollectionId $collectionId, UserId $ownerId, string $generatorVersion): void
    {
        $this->collections[] = [
            'collection_id' => $collectionId->value,
            'owner_id' => $ownerId->value,
            'generator_version' => $generatorVersion,
        ];
    }
}
