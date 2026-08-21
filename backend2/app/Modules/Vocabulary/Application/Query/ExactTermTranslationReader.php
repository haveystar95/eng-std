<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

/**
 * «Do we already know what this word means?» — one exact word, one answer, no ranking.
 *
 * Separate from {@see TermSearchReader} on purpose, even though that one could be squeezed into
 * doing this. Search RANKS: it answers «what might you have meant», returns prefix matches and
 * translation hits, and is allowed to be generous. This answers a yes/no about spending money, and
 * a generous answer to that question buys the wrong thing — a prefix match on «sign» would suppress
 * the translation of «significant» and show the learner a hint about a different word.
 *
 * Exact, normalized, one row. Free, and it runs before anything that is not.
 */
interface ExactTermTranslationReader
{
    /**
     * @param  string  $normalizedText  the query, already casefolded and whitespace-collapsed
     * @param  string  $lang            the language being learned (the term's own)
     * @param  string  $nativeLang      the learner's language — which translation to return
     * @return string|null              the translation, or null if we do not have this exact word
     */
    public function translationFor(string $normalizedText, string $lang, string $nativeLang): ?string;
}
