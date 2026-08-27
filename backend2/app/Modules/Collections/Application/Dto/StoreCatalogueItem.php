<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

use App\Modules\Shared\Domain\Service\LanguageRoles;

/**
 * One deck of the store as a SHOP WINDOW shows it: a photograph, a name, a size and a level.
 *
 * The home screen used to offer the store as three topic words in outline chips, and three words are
 * a table of contents, not an invitation. Кадр 19-2 replaces them with a strip of real covers,
 * because a photograph of an airport sells «Аэропорт» and the word «Аэропорт» does not.
 *
 * Smaller than {@see StoreCollectionView} on purpose: no subscription flag (this list is BY
 * DEFINITION the decks the learner does not have) and no photo credit (the strip prints none). What
 * it does carry beyond the cover is what a tapped cover NEEDS — a tap opens the store's own preview
 * sheet, and a sheet built from values guessed on the device is worse than no sheet: a guessed
 * `is_premium: false` hides a paywall until the subscribe button 403s, and a guessed pair draws two
 * flags on a phrasebook.
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
        public ?string $description,
        public string $sourceLang,
        public string $targetLang,
        public bool $isPremium,
    ) {}

    /**
     * A REFERENCE collection: a phrasebook, not a course.
     *
     * DERIVED and never stored, the same computation {@see StoreCollectionView::isReference()} runs
     * — one product, one answer to «есть ли у этого языка тренажёры». A second expression over the
     * same language code is how the store screen and the home strip would come to disagree about
     * the same deck.
     */
    public function isReference(): bool
    {
        return LanguageRoles::isReference($this->targetLang);
    }
}
