<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

/**
 * A store-listing row: a public or system collection a user can browse and subscribe to.
 * `topic` drives the client's sectioning; `isPremium` drives the badge and the subscribe gate;
 * `isSubscribed` tells the client whether it's already in the user's library.
 */
final readonly class StoreCollectionView
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public ?string $topic,
        public string $sourceLang,
        public string $targetLang,
        public bool $isPremium,
        public bool $isSubscribed,
        public int $itemsCount,
        public ?string $imageUrl,
        public ?string $imageAuthor,
        public ?string $imageAuthorUrl,
    ) {}
}
