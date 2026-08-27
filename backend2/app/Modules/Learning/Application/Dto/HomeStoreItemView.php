<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * One deck in the home screen's SHOP WINDOW (кадры 19-2, 19-3): a cover, a name, a size, a level.
 *
 * Mirrored out of {@see \App\Modules\Collections\Application\Dto\StoreCatalogueItem} rather than
 * passed through, for the same reason {@see HomeStoreView} mirrors the count: this read model is
 * Learning's answer to «что мне делать», and its Presentation layer must not be serialising another
 * module's DTO — the day Collections widens that row, the home payload would widen with it silently.
 *
 * [imageUrl] and [level] are null when the deck genuinely has neither. The strip draws the paper
 * placeholder for a missing cover and prints nothing where the level would go; it never invents «—».
 *
 * The description, the pair and the two flags ride along for the PREVIEW a tapped cover opens: the
 * client builds the store's own sheet out of this row, and a sheet whose premium flag or pair was
 * guessed on the device is a sheet that lies until the subscribe button 403s.
 */
final readonly class HomeStoreItemView
{
    public function __construct(
        public string $id,
        public string $title,
        public int $itemsCount,
        public ?string $imageUrl,
        public ?string $level,
        public ?string $description,
        public string $sourceLang,
        public string $targetLang,
        public bool $isPremium,
        public bool $isReference,
    ) {}
}
