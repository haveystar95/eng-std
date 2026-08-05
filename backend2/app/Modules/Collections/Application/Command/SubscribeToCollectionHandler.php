<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Collections\Domain\Entity\Collection;
use App\Modules\Collections\Domain\Exception\CollectionNotFound;
use App\Modules\Collections\Domain\Exception\SubscriptionRequired;
use App\Modules\Collections\Domain\Repository\CollectionRepository;
use App\Modules\Collections\Domain\ValueObject\CollectionType;
use App\Modules\Collections\Domain\ValueObject\Visibility;
use App\Modules\Collections\Application\Port\CollectionSubscriptions;
use App\Modules\Identity\Application\Port\UserTierReader;
use App\Modules\Shared\Domain\Service\Clock;

/**
 * Subscribe a user to a store collection. Only public/system collections are subscribable (a
 * private one 404s, same as if it didn't exist); a premium one requires a premium tier, else
 * {@see SubscriptionRequired} (403 subscription_required). Idempotent — a repeat is a no-op.
 */
final readonly class SubscribeToCollectionHandler
{
    public function __construct(
        private CollectionRepository $collections,
        private CollectionSubscriptions $subscriptions,
        private UserTierReader $tiers,
        private Clock $clock,
    ) {}

    public function __invoke(SubscribeToCollection $command): void
    {
        $collection = $this->collections->findById($command->collectionId)
            ?? throw CollectionNotFound::withId($command->collectionId);

        if (! $this->isStoreCollection($collection)) {
            throw CollectionNotFound::withId($command->collectionId);
        }

        if ($collection->isPremium() && ! $this->tiers->tierOf($command->userId)->isPremium()) {
            throw SubscriptionRequired::forPremiumCollection();
        }

        $this->subscriptions->subscribe($command->userId, $command->collectionId, $this->clock->now());
    }

    private function isStoreCollection(Collection $collection): bool
    {
        return $collection->visibility() === Visibility::Public
            || $collection->type() === CollectionType::System;
    }
}
