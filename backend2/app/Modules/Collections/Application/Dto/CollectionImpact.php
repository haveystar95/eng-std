<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

/**
 * The blast radius of touching one collection: its size, and how many people would lose it.
 * `subscribers` counts active subscriptions; `owner` is the one learner who created it, if any
 * (store collections have none).
 */
final readonly class CollectionImpact
{
    public function __construct(
        public string $collectionId,
        public string $title,
        public string $type,
        public ?string $ownerId,
        public int $termsCount,
        public int $subscribers,
        public int $learnersWithProgress,
    ) {}
}
