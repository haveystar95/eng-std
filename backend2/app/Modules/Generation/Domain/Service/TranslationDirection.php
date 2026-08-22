<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Shared\Domain\Service\LanguagePurity;

/**
 * Which half of the learner's pair did they just type in?
 *
 * The search field takes both. «occasion» wants a Russian answer; «случай» wants an English one —
 * and the second is the more important of the two, because a word the learner cannot yet name in
 * English is exactly the word worth turning into a card.
 *
 * ## The guess is not the answer
 *
 * Two methods, and the split between them is the whole design.
 *
 * {@see guess()} is a LOCAL hint made from the alphabet, and it is worth exactly what an alphabet
 * is worth: it separates Cyrillic from Latin and nothing else. «ocazie» is Romanian, «okazja» is
 * Polish, «occasion» is English, and all three look identical to it. It exists only because the
 * vendor has to be told a target language BEFORE it can tell us the source one — somebody has to
 * go first, and a guess that is right for the overwhelming majority of queries is a cheaper way to
 * go first than a probe call.
 *
 * {@see resolve()} is the VERDICT, made from what the provider detected, and it always wins. When
 * the two disagree the guess is discarded and the translation is bought again in the right
 * direction; that costs a second call on a rare input, which is the correct price for never showing
 * a Russian word to somebody who asked what a Russian word is in English.
 *
 * ## The third language
 *
 * A query detected as neither half of the pair falls back to the alphabet's guess — the one case
 * where the guess is the best signal left, because the detector has just said it does not recognise
 * either language we care about.
 *
 * This is not the rule this class was first written with, and the reason it changed is worth
 * keeping. The original rule said «a third language is treated as the language being LEARNED», so
 * an unplaceable query would at least be explained in the language the learner reads. That is right
 * for a third language written in the TARGET's alphabet — Romanian, German, a proper noun — and it
 * is wrong in the way that matters most: DeepL detects «случай» as **Bulgarian**, because it is
 * also a Bulgarian word, and the fixed rule then answered a Russian speaker's Russian query in
 * Russian. The main use case of the whole feature, broken by a detector being right about a
 * language nobody asked about (found on the first live call against the real vendor).
 *
 * Falling back to the alphabet keeps the original outcome everywhere it was actually describing —
 * a Latin-script third language still lands on «answer in the learner's own language» — and fixes
 * the Cyrillic-sibling case, where the script says plainly what the label could not.
 */
final readonly class TranslationDirection
{
    public function __construct(private LanguagePurity $purity = new LanguagePurity()) {}

    /**
     * The alphabet's opinion, to be contradicted by the provider a moment later.
     *
     * Only ever a starting target. Nothing downstream may treat it as a fact about the query.
     */
    public function guess(string $query, string $native, string $target): SearchDirection
    {
        // `isWrongScript` has an opinion for exactly the scripts it knows and says «no» for the
        // rest — which lands on «assume they typed their own language» for any pair it cannot
        // read. That is the safer default: it is the direction the learner uses the field for.
        return $this->purity->isWrongScript($native, $query)
            ? new SearchDirection($target, $native)
            : new SearchDirection($native, $target);
    }

    /**
     * What the provider detected, turned into a direction.
     *
     * The detector decides whenever it named either half of the pair, and only then. A third
     * language or a silent detector falls back to `$fallback` — the alphabet's guess — for the
     * reason in the class docblock.
     */
    public function resolve(
        ?string $detected,
        string $native,
        string $target,
        SearchDirection $fallback,
    ): SearchDirection {
        return match (strtolower(trim((string) $detected))) {
            strtolower($native) => new SearchDirection($native, $target),
            strtolower($target) => new SearchDirection($target, $native),
            default => $fallback,
        };
    }
}
