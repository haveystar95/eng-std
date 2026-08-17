<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Queues the echo-example repair for a freshly generated collection, out of band — the same shape,
 * and for the same reason, as {@see DispatchesImageAttachment}: it is one model call per bad example
 * and must never be able to slow down or fail the generation the user is waiting on.
 */
interface DispatchesExampleRepair
{
    public function repairCollection(CollectionId $collectionId, UserId $ownerId): void;
}
