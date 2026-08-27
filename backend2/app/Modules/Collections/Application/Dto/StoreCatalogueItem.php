<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

/**
 * One deck of the store as a SHOP WINDOW shows it: a photograph, a name, a size and a level.
 *
 * The home screen used to offer the store as three topic words in outline chips, and three words are
 * a table of contents, not an invitation. Кадр 19-2 replaces them with a strip of real covers,
 * because a photograph of an airport sells «Аэропорт» and the word «Аэропорт» does not.
 *
 * Smaller than {@see StoreCollectionView} on purpose. That one is the store SCREEN's row and carries
 * the description, the premium flag, the subscription flag, the photo's author and the reference
 * verdict; a strip of four covers needs none of them, and a home screen that fetched them would be
 * paying for a page it throws away — which is the reason {@see StoreCatalogueSummary} exists at all.
 *
 * `level` and `imageUrl` are nullable and mean it: a deck whose terms carry no CEFR has no level to
 * print, and a deck with no cover gets the paper placeholder rather than a broken image. Neither is
 * a zero and neither is a placeholder string.
 */
final readonly class StoreCatalogueItem
{
    public function __construct(
        public string $id,
        public string $title,
        public int $itemsCount,
        public ?string $imageUrl,
        public ?string $level,
    ) {}
}
