<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

/**
 * Word-level text helpers shared by the checks and the day assembly: one tokenizer, so «is this
 * term in that line» and «how many words is this reply» never disagree about what a word is.
 */
final class Words
{
    /**
     * Lower-cased words with punctuation stripped; an apostrophe inside a word stays («don't»).
     *
     * @return list<string>
     */
    public static function tokens(string $text): array
    {
        $lowered = mb_strtolower($text);
        $cleaned = (string) preg_replace('/[^\p{L}\p{N}\'’\s-]/u', ' ', $lowered);
        $split = preg_split('/\s+/u', trim($cleaned), -1, PREG_SPLIT_NO_EMPTY);

        return $split === false ? [] : array_values(array_map(
            static fn (string $w): string => trim($w, "'’-"),
            array_filter($split, static fn (string $w): bool => trim($w, "'’-") !== ''),
        ));
    }

    public static function count(string $text): int
    {
        return count(self::tokens($text));
    }

    /**
     * The surface words in their original spelling, punctuation stripped — for assembly tiles.
     *
     * @return list<string>
     */
    public static function surface(string $text): array
    {
        $cleaned = (string) preg_replace('/[^\p{L}\p{N}\'’\s-]/u', ' ', $text);
        $split = preg_split('/\s+/u', trim($cleaned), -1, PREG_SPLIT_NO_EMPTY);

        return $split === false ? [] : $split;
    }

    /**
     * Does `$needle` occur in `$haystack` as whole words, in order? A single-word needle also
     * matches an inflected FORM of itself — a word that shares the first max(4, len−2) letters and
     * differs in length by at most three («hurt» → «hurting», «prescription» → «prescriptions»).
     */
    public static function containsTerm(string $needle, string $haystack): bool
    {
        return self::positionOfTerm($needle, $haystack) !== null;
    }

    /**
     * Where the term stands in the text, as a [start, length] over the text's word tokens, or null.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function positionOfTerm(string $needle, string $haystack): ?array
    {
        $term = self::tokens($needle);
        $text = self::tokens($haystack);
        if ($term === [] || $text === []) {
            return null;
        }

        $n = count($term);
        foreach ($text as $i => $_) {
            if ($i + $n > count($text)) {
                break;
            }
            $match = true;
            for ($j = 0; $j < $n; $j++) {
                if ($text[$i + $j] !== $term[$j]) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return [$i, $n];
            }
        }

        if ($n === 1) {
            foreach ($text as $i => $word) {
                if (self::sameStem($term[0], $word)) {
                    return [$i, 1];
                }
            }
        }

        return null;
    }

    private static function sameStem(string $a, string $b): bool
    {
        $stem = max(4, min(mb_strlen($a), mb_strlen($b)) - 2);
        if (mb_strlen($a) < $stem || mb_strlen($b) < $stem) {
            return false;
        }

        return mb_substr($a, 0, $stem) === mb_substr($b, 0, $stem) && abs(mb_strlen($a) - mb_strlen($b)) <= 3;
    }

    /** Share of `$expected` words present in `$spoken`, 0..1. */
    public static function coverage(string $expected, string $spoken): float
    {
        $want = self::tokens($expected);
        if ($want === []) {
            return 0.0;
        }
        $have = array_count_values(self::tokens($spoken));
        $hit = 0;
        foreach ($want as $w) {
            if (($have[$w] ?? 0) > 0) {
                $hit++;
                $have[$w]--;
            }
        }

        return $hit / count($want);
    }

    /** Share of the SHORTER line's words the two lines have in common. */
    public static function overlap(string $a, string $b): float
    {
        $ta = array_unique(self::tokens($a));
        $tb = array_unique(self::tokens($b));
        if ($ta === [] || $tb === []) {
            return 0.0;
        }

        return count(array_intersect($ta, $tb)) / min(count($ta), count($tb));
    }
}
