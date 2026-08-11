<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

/**
 * Splits an example sentence into the chips a `scramble` card is assembled from — and, because the
 * gate counts them, decides how long a sentence "is".
 *
 * The rules were chosen against the real corpus (822 examples in the live database), not in the
 * abstract:
 *
 *  - split on whitespace only. Punctuation is never a chip of its own.
 *  - an in-word apostrophe STAYS in its token (`don't`, `teacher's`). 10.8% of examples have one;
 *    splitting there would produce `don` + `'t`, which is not a word anyone assembles.
 *  - the FINAL `.`/`!`/`?` is stripped and never becomes a chip. 99.3% of examples end with one, so
 *    a punctuation chip would be a constant present in every single card — no information, one more
 *    tile to place.
 *  - inner punctuation stays GLUED to its token (`morning,`). 14.8% of examples have a comma; as a
 *    separate chip it carries no meaning but adds a position to get wrong.
 *  - case is NOT folded — the leading capital stays. Grading normalises case anyway
 *    ({@see \App\Modules\Shared\Domain\Service\LexicalNormalizer}), so this cannot make the task
 *    stricter; what it buys is that the chips are literally the pinned example's own words, so the
 *    example stays the single source of truth for the expected order.
 *
 * Pure and deterministic — the Dart port in the mobile client mirrors it, pinned by the practice
 * contract fixture.
 */
final class SentenceTokenizer
{
    /**
     * The chips of a sentence, in the sentence's own order.
     *
     * @return list<string>
     */
    public function tokenize(string $sentence): array
    {
        $split = preg_split('/\s+/u', trim($sentence)) ?: [];
        /** @var list<string> $tokens */
        $tokens = array_values(array_filter($split, static fn (string $t): bool => $t !== ''));
        if ($tokens === []) {
            return [];
        }

        $lastIndex = count($tokens) - 1;
        $last = (string) preg_replace('/[.!?…]+$/u', '', $tokens[$lastIndex]);
        if ($last === '') {
            array_pop($tokens); // the sentence ended on a standalone "!" — drop the empty chip

            return $tokens;
        }
        $tokens[$lastIndex] = $last;

        return $tokens;
    }

    /** How many chips a sentence yields — what the scramble gate measures. */
    public function count(string $sentence): int
    {
        return count($this->tokenize($sentence));
    }

    /**
     * Do two strings tokenize to the same words (ignoring case)? A term whose example IS the term
     * ("Nice to meet you." for the phrase «Nice to meet you») would make scramble a copy of
     * word_bank — same tiles, same target — so that term is not scrambled.
     */
    public function sameTokens(string $a, string $b): bool
    {
        $fold = fn (string $s): array => array_map(
            static fn (string $t): string => mb_strtolower($t),
            $this->tokenize($s),
        );

        return $fold($a) === $fold($b);
    }
}
