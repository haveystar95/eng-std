<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\DispatchesGeneration;
use App\Modules\Generation\Infrastructure\Job\GenerateCollectionJob;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;

final class QueuedGenerationDispatcher implements DispatchesGeneration
{
    public function dispatch(GenerationRequestId $id): void
    {
        GenerateCollectionJob::dispatch($id->value);
    }
}
