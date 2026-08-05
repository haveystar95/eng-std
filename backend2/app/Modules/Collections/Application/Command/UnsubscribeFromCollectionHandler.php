<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Collections\Application\Port\CollectionSubscriptions;

final readonly class UnsubscribeFromCollectionHandler
{
    public function __construct(private CollectionSubscriptions $subscriptions) {}

    public function __invoke(UnsubscribeFromCollection $command): void
    {
        // Idempotent: removing a collection the user isn't subscribed to is a no-op.
        $this->subscriptions->unsubscribe($command->userId, $command->collectionId);
    }
}
