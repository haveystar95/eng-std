<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Shared\Domain\Service\LexicalNormalizer;

/**
 * Decides whether a back-translation missed the answer key by so little that flagging the card as
 * ambiguous would be noise. The first run's numbers are why this exists: 9 of 12 terms were flagged,
 * and a third of those differed from the target by one function word or one inflection.
 *
 * The rule is NOT plain "at most one token differs". That version was tried against the two cases
 * that define the boundary and fails one of them:
 *
 *   target «break room»       ↔ back «rest room»       — one substitution, MUST stay flagged
 *   target «company policies» ↔ back «company policy»  — one substitution, MUST be suppressed
 *
 * Both are a single token apart, so token count alone cannot separate them. What separates them is
 * WHAT the differing token is: the same word in another form («policy»/«policies») versus a
 * different word altogether («rest»/«break»). So:
 *
 *   - one token INSERTED or DELETED, and that token is a FUNCTION word → near miss. «meet with the
 *     team» for «meet the team», «to settle in» for «settle in». A learner typing the target is never
 *     wrong because the model padded its own paraphrase with a preposition. An inserted CONTENT word
 *     is a different matter and stays flagged — «open a bank account» against «open an account» adds
 *     meaning, it does not restate it.
 *   - one token SUBSTITUTED → near miss only when the two tokens are the same word inflected: they
 *     share a long common prefix. A different lexeme means the prompt genuinely admits a different
 *     answer — «workstation» → «workplace» is real ambiguity of a one-word term, not noise — and that
 *     is exactly what deserves a human's attention.
 *   - anything else → not a near miss.
 *
 * The prefix test is a deliberately crude stemmer, and crude is the point: it needs no dictionary and
 * no per-language rules, and both languages in play (and every other suffixing language) inflect at
 * the end. It errs toward KEEPING the flag, because a false flag costs a glance and a missed one
 * costs an unanswerable card.
 */
final class BackTranslationTolerance
{
    /**
     * Words whose presence or absence does not change which answer is meant. Closed list, and
     * English-only because this is compared against the LEARNED language, which is English for every
     * collection that exists — the back-translation is written in {{term_lang}}.
     *
     * A word outside the list is treated as a content word, so the flag SURVIVES. That is what makes
     * the list safe to be incomplete and safe for a future language: not recognising a function word
     * keeps a flag a human then dismisses, while wrongly treating a content word as noise would hide
     * a genuinely unanswerable card.
     */
    private const FUNCTION_WORDS = [
        'a', 'an', 'the',
        'to', 'of', 'in', 'on', 'at', 'by', 'for', 'with', 'from', 'into', 'about', 'up', 'out',
        'and', 'or', 'that', 'as',
        'is', 'are', 'be', 'been', 'am', 'was', 'were', 'do', 'does', 'did',
        'my', 'your', 'his', 'her', 'its', 'our', 'their',
        'some', 'any',
    ];

    /** Below this the "same stem" idea is meaningless — «is»/«in» share nothing but length. */
    private const MIN_STEM_LENGTH = 4;

    /** How much of the shorter token the shared prefix must cover to read as the same word. */
    private const MIN_PREFIX_RATIO = 0.6;

    public function __construct(private readonly LexicalNormalizer $normalizer = new LexicalNormalizer()) {}

    /**
     * Is $back within tolerance of ANY accepted form?
     *
     * @param  list<string>  $acceptedForms
     */
    public function isNearMiss(string $back, array $acceptedForms): bool
    {
        $backTokens = $this->tokens($back);
        if ($backTokens === []) {
            return false;
        }

        foreach ($acceptedForms as $form) {
            $formTokens = $this->tokens($form);
            if ($formTokens !== [] && $this->withinOneToken($backTokens, $formTokens)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function withinOneToken(array $a, array $b): bool
    {
        $lenA = count($a);
        $lenB = count($b);

        if (abs($lenA - $lenB) > 1) {
            return false;
        }

        if ($lenA === $lenB) {
            return $this->differsByOneInflection($a, $b);
        }

        // One list is longer by exactly one token: is it the shorter one with a token inserted?
        return $lenA > $lenB
            ? $this->isOneInsertion($a, $b)
            : $this->isOneInsertion($b, $a);
    }

    /**
     * Equal length: exactly one position differs, and the two tokens there are the same word in
     * another form.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function differsByOneInflection(array $a, array $b): bool
    {
        $diffIndex = null;
        foreach ($a as $i => $token) {
            if ($token !== $b[$i]) {
                if ($diffIndex !== null) {
                    return false;   // a second difference
                }
                $diffIndex = $i;
            }
        }

        // No difference at all is not this class's business — the caller already matched exactly.
        return $diffIndex !== null && $this->sameStem($a[$diffIndex], $b[$diffIndex]);
    }

    /**
     * Is $longer $shorter with exactly one extra token anywhere, and is that token a function word?
     * Walks both once; on the first mismatch it skips one token of $longer and requires the rest to
     * line up. The skipped token is the inserted one, and it decides the verdict.
     *
     * @param  list<string>  $longer
     * @param  list<string>  $shorter
     */
    private function isOneInsertion(array $longer, array $shorter): bool
    {
        $i = 0;
        $inserted = null;
        foreach ($shorter as $token) {
            if (($longer[$i] ?? null) !== $token) {
                if ($inserted !== null) {
                    return false;
                }
                $inserted = $longer[$i] ?? null;
                $i++;               // drop the extra token of $longer
                if (($longer[$i] ?? null) !== $token) {
                    return false;
                }
            }
            $i++;
        }

        // The extra token may also be the LAST one, in which case the loop never mismatched.
        $inserted ??= $longer[count($longer) - 1] ?? null;

        return $inserted !== null && $this->isFunctionWord($inserted);
    }

    private function isFunctionWord(string $token): bool
    {
        return in_array($token, self::FUNCTION_WORDS, true);
    }

    private function sameStem(string $a, string $b): bool
    {
        $shorter = min(mb_strlen($a), mb_strlen($b));
        if ($shorter < self::MIN_STEM_LENGTH) {
            return false;
        }

        $prefix = 0;
        while ($prefix < $shorter && mb_substr($a, $prefix, 1) === mb_substr($b, $prefix, 1)) {
            $prefix++;
        }

        return $prefix / $shorter >= self::MIN_PREFIX_RATIO;
    }

    /** @return list<string> */
    private function tokens(string $value): array
    {
        $normalized = $this->normalizer->normalize($value);

        return $normalized === '' ? [] : array_values(array_filter(preg_split('/\s+/u', $normalized) ?: []));
    }
}
