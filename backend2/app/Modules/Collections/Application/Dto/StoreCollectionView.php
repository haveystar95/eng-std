<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

use App\Modules\Shared\Domain\Service\LanguageRoles;

/**
 * A store-listing row: a public or system collection a user can browse and subscribe to.
 * `topic` drives the client's sectioning; `isPremium` drives the badge and the subscribe gate;
 * `isSubscribed` tells the client whether it's already in the user's library. `level` is the
 * CEFR range shown on the card (e.g. "A2–B1" or "B2"); null when no term carries a level.
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
        public ?string $level = null,
    ) {}

    /**
     * A REFERENCE collection: a phrasebook, not a course.
     *
     * DERIVED from the studied language and never stored (DECISIONS п. 136) — the same computation
     * `/sync` runs for a collection already on the shelf ({@see \App\Modules\Learning\Application\Dto\CollectionChange::isReference}).
     * The store feed did not carry it until 24.08, and the omission was a lie waiting for its first
     * Chinese deck: the card would have drawn a pair of flags, promising training that does not
     * exist for zh or ja. There is no such deck in the catalogue yet — which is exactly why this
     * lands before one appears rather than after.
     */
    public function isReference(): bool
    {
        return LanguageRoles::isReference($this->targetLang);
    }
}
