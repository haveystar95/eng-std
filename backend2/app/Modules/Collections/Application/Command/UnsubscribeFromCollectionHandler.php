<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Collections\Application\Port\CollectionSubscriptions;
use App\Modules\Shared\Domain\Service\Clock;

final readonly class UnsubscribeFromCollectionHandler
{
    public function __construct(
        private CollectionSubscriptions $subscriptions,
        private Clock $clock,
    ) {}

    public function __invoke(UnsubscribeFromCollection $command): void
    {
        // Idempotent: removing a collection the user isn't subscribed to is a no-op.
        $this->subscriptions->unsubscribe($command->userId, $command->collectionId, $this->clock->now());
    }
}
