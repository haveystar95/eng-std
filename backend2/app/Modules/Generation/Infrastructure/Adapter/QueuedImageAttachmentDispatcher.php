<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\DispatchesImageAttachment;
use App\Modules\Generation\Infrastructure\Job\AttachImagesJob;
use App\Modules\Shared\Domain\ValueObject\CollectionId;

final class QueuedImageAttachmentDispatcher implements DispatchesImageAttachment
{
    public function dispatch(CollectionId $collectionId): void
    {
        AttachImagesJob::dispatch($collectionId->value);
    }
}
