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
 * A query detected as neither half of the pair — Romanian, German, a proper noun the detector
 * shrugged at — is treated as the language being LEARNED, so the answer comes back in the learner's
 * own language. That is the useful failure: somebody who typed something we cannot place still gets
 * told what it means, in the language they read.
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
     * What the provider detected, turned into a direction. `$detected` null = it would not say.
     *
     * Only the learner's OWN language flips the direction; everything else — the target language,
     * a third language, silence — means «answer in the language they read».
     */
    public function resolve(?string $detected, string $native, string $target): SearchDirection
    {
        $seen = strtolower(trim((string) $detected));

        return $seen === strtolower($native)
            ? new SearchDirection($native, $target)
            : new SearchDirection($target, $native);
    }
}
