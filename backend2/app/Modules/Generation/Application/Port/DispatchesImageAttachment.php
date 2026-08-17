<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Shared\Domain\ValueObject\CollectionId;

/**
 * Kicks off image attachment for a freshly generated collection, out of band. Lets the Application
 * enqueue the work without depending on the queue (mirrors {@see DispatchesGeneration}). Attachment
 * is best-effort and must never block or fail the generation that produced the collection.
 */
interface DispatchesImageAttachment
{
    public function dispatch(CollectionId $collectionId): void;
}
