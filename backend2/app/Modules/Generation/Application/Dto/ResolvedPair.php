<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Generation\Domain\Service\SearchDirection;

/**
 * The pair a search runs in, with BOTH readings of it side by side.
 *
 * They are not the same question and conflating them is a real bug rather than a tidiness issue:
 *
 *  * {@see $direction} is which way the LEARNER is asking — «EN → RU» or «RU → EN». It is what the
 *    translator is told, and it is half of the instant cache key, because a word translated one way
 *    is not the answer to the same word asked the other way.
 *  * {@see $termLang} / {@see $translationLang} are where the ROWS live. `case` is an English term
 *    with a Russian translation whichever way it was reached, so a catalogue reader keyed on the
 *    direction would go looking for Russian terms and find none.
 *
 * Carried together so no caller has to derive one from the other and get it wrong for a learner
 * whose taught language is not English — the live database already holds Polish terms.
 */
final readonly class ResolvedPair
{
    public function __construct(
        public SearchDirection $direction,
        /** What a term is written in — the language being taught. */
        public string $termLang,
        /** What its translation is written in — the language the learner reads. */
        public string $translationLang,
    ) {}

    /** True when the query was typed in the learner's own language. */
    public function reversed(): bool
    {
        return $this->direction->source === $this->translationLang;
    }
}
