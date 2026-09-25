<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * A NUMBER SAID IN WORDS IS THE NUMBER IN DIGITS — on both sides of every comparison of speech (наряд FIX-3 §4,
 * DECISIONS п. 393; the languages of the plan, наряд LANG-1).
 *
 * The owner said «I will rest for 45 seconds» to a card that asked for «I'll rest for forty-five seconds» and was failed
 * twice (зал, день 2): the recogniser writes a number in digits, the lesson in words, and a word-by-word fold turned
 * «forty-five» into «40 5» — never «45». So the words of one number are read as ONE number, the way a person reads them.
 *
 * THE RULE, over the canonical words of a text (case folded, contractions spelt out, every mark — the hyphen too — a
 * space, so «forty-five» is «forty five» and «quatre-vingt-dix» is «quatre vingt dix»), left to right:
 *
 * 1. An ENTRY of the pack's `number_words` is its value. An entry is one word or SEVERAL, one space apart, written in
 *    that canonical form («soixante dix» → 70, «quatre vingt dix» → 90): at every place the LONGEST entry whose words
 *    stand there is read, as one value — «quatre vingt dix neuf» with an entry for it is 99, «quatre vingt» 80, a lone
 *    «quatre» 4 (наряд LANG-1: French counts by twenties, and no rule of fitting below makes «quatre vingt» of 4 and
 *    20). An ARTICLE of the pack right before a SCALE counts as one («a hundred» → 100, ro «o sută» → 100). A scale is
 *    a value of 100 or more that is a power of ten (hundred, thousand; ro «sute», pl «tysiące» — a plural form of a
 *    scale is one more entry of the same value).
 * 2. The values of one number join while the next one FITS:
 *    - a scale fits after a number that is above nought and below it, and multiplies it («two hundred» → 200,
 *      «two hundred thousand» → 200000, ro «două sute» → 200); a thousand or more closes that part and a smaller number
 *      starts after it («two thousand five» → 2005);
 *    - any other value fits after a value of 20 or more when it is smaller than that value's last place — the largest
 *      power of ten that divides it — and above nought: «forty five» → 45 (5 < 10), «hundred twenty» → 120 (20 < 100),
 *      pl «dwadzieścia jeden» → 21; «ten five», «twenty twelve», «two three» do not fit and stay two numbers;
 *    - a JOINER of the pack stands between two values of one number when the value after it fits after the value before
 *      it and is below a hundred — in the two places a language puts one:
 *      · after a SCALE (en «and»): «one hundred and twenty» → 120, «two thousand and five» → 2005, fr «mille et un» →
 *        1001;
 *      · after a TENS value — 20 or more, not a scale — that no joiner brought in (es «y», ro «și», fr «et»): «treinta
 *        y uno» → 31, «douăzeci și unu» → 21, «vingt et un» → 21, «ciento treinta y uno» → 131; what does not fit after
 *        the tens value is not joined to it («vingt et onze» stays three words — fr 71 «soixante et onze» is read only
 *        through an entry of its own), and a tens value that came in through a joiner takes none after it, so «a hundred
 *        and twenty and five» is 120, «and», 5.
 *      Anywhere else the joiner is a word of its own («two hundred and a thousand», «five and six», «uno y dos»).
 * 3. A joined number is written in digits and replaces its words («one minute» → «1 minute», «twenty one» → «21»);
 *    digits the text already has stay as they are and never join anything.
 *
 * English reads as it did before LANG-1 in every place but one: a tens word that «and» did not bring in now joins a
 * unit after «and» — «twenty and five» is 25, as es «veinte y cinco» is — AND a scale after that unit multiplies the
 * joined number, as es «treinta y un mil» → 31000 must: so en «between twenty and one hundred dollars» — TWO numbers —
 * is «between 2100 dollars» (it was «between 20 and 100 dollars»), and a recogniser that writes «between 20 and 100
 * dollars» no longer matches the line (the verifier of наряд LANG-1, run on the en pack: every English reading that
 * moved has «tens and unit» in it). The rule knows no language, so the only way to keep English as it was is a key of
 * the pack saying after what its joiners stand (an open question of наряд LANG-1); until it is decided, the example
 * table pins this cost where it can be seen.
 *
 * Mirrored in Dart one to one (`speech` block of the day: `number_words`, `articles`, `number_joiners`), and the mirror
 * may never read a number differently: a verdict on the phone that the server would not give is a lie shown to the
 * learner.
 */
final class SpokenNumbers
{
    /**
     * @param  list<string>  $words  canonical words
     * @param  array<string, string>  $numberWords  an entry — a word, or several one space apart — → the digits it says
     * @param  list<string>  $articles  the pack's articles — «a» of «a hundred»
     * @param  list<string>  $joiners  the pack's number joiners — «and» of «one hundred and twenty», «y» of «treinta y uno»
     * @return list<string>
     */
    public static function fold(array $words, array $numberWords, array $articles = [], array $joiners = []): array
    {
        if ($numberWords === []) {
            return $words;
        }
        $values = [];
        $longest = 1;
        foreach ($numberWords as $entry => $digits) {
            if (is_numeric($digits)) {
                $values[(string) $entry] = (int) $digits;
                $longest = max($longest, count(explode(' ', (string) $entry)));
            }
        }
        $isArticle = array_fill_keys($articles, true);
        $isJoiner = array_fill_keys($joiners, true);

        $out = [];
        $n = count($words);
        $i = 0;
        while ($i < $n) {
            $word = $words[$i];
            $articleOne = isset($isArticle[$word])
                && self::isScale(self::valueAt($words, $i + 1, $values, $longest)[0] ?? null);
            if (self::valueAt($words, $i, $values, $longest) === null && ! $articleOne) {
                $out[] = $word;
                $i++;

                continue;
            }
            $total = 0;
            $current = 0;
            $last = null;
            // Did a joiner bring `$last` in? A tens value that one did takes no joiner after it.
            $joined = false;
            if ($articleOne) {
                $current = 1;
                $last = 1;
                $i++;
            }
            while ($i < $n) {
                $viaJoiner = false;
                if (isset($isJoiner[$words[$i]]) && $last !== null && (self::isScale($last) || ($last >= 20 && ! $joined))) {
                    $next = self::valueAt($words, $i + 1, $values, $longest)[0] ?? null;
                    if ($next === null || $next <= 0 || $next >= min(100, self::place($last))) {
                        break;
                    }
                    $viaJoiner = true;
                    $i++;
                }
                $at = self::valueAt($words, $i, $values, $longest);
                if ($at === null) {
                    break;
                }
                [$value, $take] = $at;
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
                $joined = $viaJoiner;
                $i += $take;
            }
            $out[] = (string) ($total + $current);
        }

        return $out;
    }

    /**
     * The value that starts at `$at` — the LONGEST entry whose words stand there — and how many words it takes; null
     * when no entry starts there.
     *
     * @param  list<string>  $words
     * @param  array<string, int>  $values
     * @return array{0: int, 1: int}|null
     */
    private static function valueAt(array $words, int $at, array $values, int $longest): ?array
    {
        for ($take = min($longest, count($words) - $at); $take >= 1; $take--) {
            $entry = implode(' ', array_slice($words, $at, $take));
            if (isset($values[$entry])) {
                return [$values[$entry], $take];
            }
        }

        return null;
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
