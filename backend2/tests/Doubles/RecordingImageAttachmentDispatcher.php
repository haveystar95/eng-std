<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Generation\Application\Port\DispatchesImageAttachment;
use App\Modules\Shared\Domain\ValueObject\CollectionId;

/** Records dispatched collection ids instead of queuing, so tests can assert attachment was kicked off. */
final class RecordingImageAttachmentDispatcher implements DispatchesImageAttachment
{
    /** @var list<string> */
    public array $dispatched = [];

    public function dispatch(CollectionId $collectionId): void
    {
        $this->dispatched[] = $collectionId->value;
    }
}
