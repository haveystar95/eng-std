<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;

/**
 * DID THE LEARNER SAY ENOUGH OF THIS LINE (наряд SESSION-1a, разд. 0; D-26) — the coverage every voice card of the day
 * is passed by, on the client without the network and on the server before the judge.
 *
 * The rule of «Говорю сам» (Learning's `SpokenCoverage`), read the way this module reads a language: an expected text
 * of at most two counted words needs all of them, a longer one 70 % of its words, counted as a MULTISET (a line that
 * says «no» twice needs it twice), order-free — a recogniser drops and swaps words, it does not reorder them. The
 * articles of the TARGET pack are forgiven — the recogniser eats them first — and a language whose pack names no
 * articles forgives none: nothing here hard-codes «a/an/the». Plan cannot read Learning (deptrac), so the words are
 * compared on the Shared kernel's canonical form, as Learning compares them.
 */
final readonly class SpeechCoverage
{
    public const ALL = 1.0;

    public const MOST = 0.7;

    public const SHORT_WORDS = 2;

    private const EPSILON = 1e-9;

    public function __construct(private LexicalNormalizer $normalizer = new LexicalNormalizer) {}

    /** @return list<string> the comparable words of a text: canonical, lower-cased, punctuation gone */
    public function words(string $text): array
    {
        $canonical = $this->normalizer->canonicalize($text);

        return array_values(array_filter(explode(' ', $canonical), static fn (string $w): bool => $w !== ''));
    }

    /** How many words of the expected text count: every word but the target language's articles. */
    public function countedWords(string $expected, LanguagePack $target): int
    {
        return count($this->withoutArticles($this->words($expected), $target));
    }

    /** The share a card asks for: all of a short line, most of a longer one. */
    public function minFor(string $expected, LanguagePack $target): float
    {
        return $this->countedWords($expected, $target) <= self::SHORT_WORDS ? self::ALL : self::MOST;
    }

    /** Were at least `$min` of the expected words (articles aside) heard, each heard word used once? */
    public function covers(string $heard, string $expected, float $min, LanguagePack $target): bool
    {
        $wanted = $this->withoutArticles($this->words($expected), $target);
        if ($wanted === []) {
            return false;
        }
        $available = array_count_values($this->words($heard));
        $found = 0;
        foreach ($wanted as $word) {
            if (($available[$word] ?? 0) > 0) {
                $available[$word]--;
                $found++;
            }
        }

        return $found / count($wanted) + self::EPSILON >= $min;
    }

    /** Were the words of `$value` (a filler) heard one after another — articles aside on both sides? */
    public function containsSequence(string $heard, string $value, LanguagePack $target): bool
    {
        $needle = $this->withoutArticles($this->words($value), $target);
        $haystack = $this->withoutArticles($this->words($heard), $target);
        $n = count($needle);
        if ($n === 0 || $n > count($haystack)) {
            return false;
        }
        for ($i = 0; $i + $n <= count($haystack); $i++) {
            if (array_slice($haystack, $i, $n) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * What was heard beyond the frame — the words of `$heard` the frame's own words ({@see FrameParts::part()}) do not
     * account for, as heard, in the order heard: the slot's value when the judge cannot rule on it.
     *
     * Heard is canonicalised ONCE, as a whole, the way {@see covers()} canonicalises it, and every surface word is
     * credited with the tokens it contributes to THAT fold — what the fold loses when the word is dropped. A word
     * folded alone is a different word («He's» is `hes` on its own and `he has` in front of «been», the one rule the
     * canonicaliser reads forward), and a frame word folded one way on one side of the comparison comes back as the
     * learner's own value: «He's been resting since Monday» against «He's been resting ___ .» handed back «He's since
     * Monday». Counting from the suffix is exact here precisely because that rule looks forward and no other does.
     */
    public function slotWords(string $heard, string $frameTarget): string
    {
        $available = array_count_values($this->words(FrameParts::part($frameTarget)));
        $surface = preg_split('/\s+/u', trim($heard), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stream = $this->words(implode(' ', $surface));
        $out = [];
        $taken = 0;
        foreach ($surface as $i => $raw) {
            $rest = count($this->words(implode(' ', array_slice($surface, $i + 1))));
            $tokens = array_slice($stream, $taken, max(0, count($stream) - $rest - $taken));
            $taken += count($tokens);
            if ($tokens === []) {
                continue;
            }
            $need = array_count_values($tokens);
            $framed = true;
            foreach ($need as $token => $count) {
                if (($available[$token] ?? 0) < $count) {
                    $framed = false;
                    break;
                }
            }
            if ($framed) {
                foreach ($need as $token => $count) {
                    $available[$token] -= $count;
                }

                continue;
            }
            $out[] = (string) preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $raw);
        }

        return implode(' ', $out);
    }

    /**
     * @param  list<string>  $words
     * @return list<string>
     */
    private function withoutArticles(array $words, LanguagePack $target): array
    {
        if (! $target->has('articles')) {
            return $words;
        }
        $articles = array_fill_keys($target->words('articles'), true);

        return array_values(array_filter($words, static fn (string $w): bool => ! isset($articles[$w])));
    }
}
