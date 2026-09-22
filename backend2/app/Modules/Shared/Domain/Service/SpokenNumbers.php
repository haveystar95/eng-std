<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * A NUMBER SAID IN WORDS IS THE NUMBER IN DIGITS — on both sides of every comparison of speech (наряд FIX-3 §4).
 *
 * The owner said «I will rest for 45 seconds» to a card that asked for «I'll rest for forty-five seconds» and was failed
 * twice (зал, день 2): the recogniser writes a number in digits, the lesson in words, and a word-by-word fold turned
 * «forty-five» into «40 5» — never «45». So the words of one number are read as ONE number, the way a person reads them.
 *
 * THE RULE, over the canonical words of a text (case folded, contractions spelt out, every mark — the hyphen too — a
 * space, so «forty-five» is «forty five»), left to right:
 *
 * 1. A word of the pack's `number_words` is its value; an ARTICLE of the pack right before a SCALE counts as one
 *    («a hundred» → 100). A scale is a value of 100 or more that is a power of ten (hundred, thousand).
 * 2. The words of one number join while the next one FITS:
 *    - a scale fits after a number that is above nought and below it, and multiplies it («two hundred» → 200,
 *      «two hundred thousand» → 200000); a thousand or more closes that part and a smaller number starts after it
 *      («two thousand five» → 2005);
 *    - any other value fits after a word of 20 or more when it is smaller than that word's last place — the largest
 *      power of ten that divides it — and above nought: «forty five» → 45 (5 < 10), «hundred twenty» → 120 (20 < 100);
 *      «ten five», «twenty twelve», «two three» do not fit and stay two numbers;
 *    - a JOINER of the pack (en «and») between a scale and a value below a hundred that fits after it is part of the
 *      number: «one hundred and twenty» → 120, «two thousand and five» → 2005; anywhere else it is a word of its own
 *      («two hundred and a thousand», «five and six»).
 * 3. A joined number is written in digits and replaces its words («one minute» → «1 minute», «twenty one» → «21»);
 *    digits the text already has stay as they are and never join anything.
 *
 * Mirrored in Dart one to one (`speech` block of the day: `number_words`, `articles`), and the mirror may never read a
 * number differently: a verdict on the phone that the server would not give is a lie shown to the learner.
 */
final class SpokenNumbers
{
    /**
     * @param  list<string>  $words  canonical words
     * @param  array<string, string>  $numberWords  word → the digits it says
     * @param  list<string>  $articles  the pack's articles — «a» of «a hundred»
     * @param  list<string>  $joiners  the pack's number joiners — «and» of «one hundred and twenty»
     * @return list<string>
     */
    public static function fold(array $words, array $numberWords, array $articles = [], array $joiners = []): array
    {
        if ($numberWords === []) {
            return $words;
        }
        $values = [];
        foreach ($numberWords as $word => $digits) {
            if (is_numeric($digits)) {
                $values[(string) $word] = (int) $digits;
            }
        }
        $isArticle = array_fill_keys($articles, true);
        $isJoiner = array_fill_keys($joiners, true);

        $out = [];
        $n = count($words);
        $i = 0;
        while ($i < $n) {
            $word = $words[$i];
            $articleOne = isset($isArticle[$word]) && self::isScale($values[$words[$i + 1] ?? ''] ?? null);
            if (! isset($values[$word]) && ! $articleOne) {
                $out[] = $word;
                $i++;

                continue;
            }
            $total = 0;
            $current = 0;
            $last = null;
            if ($articleOne) {
                $current = 1;
                $last = 1;
                $i++;
            }
            while ($i < $n) {
                if (isset($isJoiner[$words[$i]]) && self::isScale($last)) {
                    $next = $values[$words[$i + 1] ?? ''] ?? null;
                    if ($next === null || self::isScale($next) || $next <= 0 || $next >= 100) {
                        break;
                    }
                    $i++;
                }
                if (! isset($values[$words[$i]])) {
                    break;
                }
                $value = $values[$words[$i]];
                if ($last === null) {
                    [$total, $current] = self::isScale($value) ? self::scaled(0, 1, $value) : [0, $value];
                } elseif (self::isScale($value) && $current > 0 && $current < $value) {
                    [$total, $current] = self::scaled($total, $current, $value);
                } elseif (! self::isScale($value) && $last >= 20 && $value > 0 && $value < self::place($last)) {
                    $current += $value;
                } else {
                    break;
                }
                $last = $value;
                $i++;
            }
            $out[] = (string) ($total + $current);
        }

        return $out;
    }

    /** @return array{0: int, 1: int} the running total and the part after it, once a scale is said */
    private static function scaled(int $total, int $current, int $scale): array
    {
        return $scale >= 1000 ? [$total + $current * $scale, 0] : [$total, $current * $scale];
    }

    private static function isScale(?int $value): bool
    {
        return $value !== null && $value >= 100 && self::place($value) === $value;
    }

    /** The largest power of ten that divides a positive number: 40 → 10, 200 → 100, 1000 → 1000, 45 → 1. */
    private static function place(int $value): int
    {
        $place = 1;
        while ($value > 0 && $value % ($place * 10) === 0) {
            $place *= 10;
        }

        return $place;
    }
}
