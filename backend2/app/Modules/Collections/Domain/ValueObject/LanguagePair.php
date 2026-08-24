<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\ValueObject;

use App\Modules\Collections\Domain\Exception\TermLanguageMismatch;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;

/**
 * A collection's pair — «изучаемый → язык поддержки» — and THE ONE RULE that hangs off it: a
 * collection accepts only terms of its own studied language.
 *
 * «Одна папка — одна пара» (DECISIONS п. 81). A word of another pair does not go into this folder;
 * it goes into another folder. That is not a limitation of the storage — the pair is what makes a
 * card answerable at all: it decides which translation is the question, which gloss sits under the
 * example, and which trainers the language can even carry. A folder holding a Polish word beside an
 * English one has no honest answer to any of the three.
 *
 * The rule lives HERE, in one value object, rather than in each writer, because there are five ways
 * a term reaches a folder (search, generation, import, a move between folders, back-office
 * curation) and a rule copied five times is a rule that holds four times. The aggregate carries
 * this VO ({@see \App\Modules\Collections\Domain\Entity\Collection::pair()}); the one writer that
 * does not build the aggregate — the back-office curator, which writes `collection_items` directly
 * — constructs the pair from its own row and asks the SAME object.
 */
final readonly class LanguagePair
{
    public function __construct(
        /** The language BEING LEARNED — and therefore the language of every term in the collection. */
        public LanguageCode $targetLang,
        /** The language of SUPPORT — which translation is shown, and what the example's gloss is in. */
        public LanguageCode $sourceLang,
    ) {}

    public function accepts(LanguageCode $termLang): bool
    {
        return $this->targetLang->equals($termLang);
    }

    /** @throws TermLanguageMismatch when the term is not of this collection's studied language */
    public function assertAccepts(CollectionId $collectionId, LanguageCode $termLang): void
    {
        if (! $this->accepts($termLang)) {
            throw TermLanguageMismatch::make($collectionId, $this->targetLang, $termLang);
        }
    }
}
