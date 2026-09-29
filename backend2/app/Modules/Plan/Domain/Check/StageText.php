<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;

/**
 * HOW THE STAGES' RULES READ TEXT (наряд GEN-4) — one reading for all of them, so that «the same» and «close» never mean two
 * things: lower case, the stress mark and every mark but the slot's underscores gone, one space between words.
 */
final class StageText
{
    public static function normal(string $text): string
    {
        $text = mb_strtolower(str_replace("\u{0301}", '', $text));
        $text = (string) preg_replace('/_{3,}/u', ' ___ ', $text);
        $text = (string) preg_replace('/[^\p{L}\p{N}_\s]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** How alike two texts are, 0…1: one minus their edit distance over the longer, read {@see normal()}. */
    public static function similarity(string $a, string $b): float
    {
        $x = preg_split('//u', self::normal($a), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $y = preg_split('//u', self::normal($b), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $longer = max(count($x), count($y));
        if ($longer === 0) {
            return 1.0;
        }
        $previous = range(0, count($y));
        foreach ($x as $i => $xc) {
            $current = [$i + 1];
            foreach ($y as $j => $yc) {
                $current[] = min($previous[$j + 1] + 1, $current[$j] + 1, $previous[$j] + ($xc === $yc ? 0 : 1));
            }
            $previous = $current;
        }

        return 1.0 - $previous[count($y)] / $longer;
    }

    /**
     * The words of a text written in two alphabets at once — a Latin letter inside a Cyrillic word or the other way round
     * («Kак», «cтол»): the letters of one word belong to more than one writing. A word wholly in another alphabet is no
     * such word.
     *
     * @return list<string>
     */
    public static function mixedWords(string $text): array
    {
        $out = [];
        foreach (preg_split('/[^\p{L}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $scripts = 0;
            foreach (['Latin', 'Cyrillic', 'Greek', 'Armenian', 'Georgian', 'Arabic', 'Hebrew'] as $script) {
                if (preg_match('/\p{'.$script.'}/u', $word) === 1) {
                    $scripts++;
                }
            }
            if ($scripts > 1) {
                $out[] = $word;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * The letters of a Cyrillic word that are no letters of the learner's own Cyrillic alphabet — a Ukrainian «і» in a
     * Russian word, a Russian «ы» in a Ukrainian one — read by the strict alphabet of the language's pack (`script`). A
     * language written in another alphabet has none: its words are read by {@see mixedWords()}.
     *
     * @return list<string>
     */
    public static function otherCyrillic(string $text, LanguagePack $native): array
    {
        if (! $native->has('script') || preg_match('/\p{Cyrillic}/u', $native->pattern('script')) !== 1) {
            return [];
        }
        $out = [];
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $letter) {
            if (preg_match('/^\p{Cyrillic}$/u', $letter) === 1 && preg_match($native->pattern('script'), $letter) !== 1) {
                $out[$letter] = true;
            }
        }

        return array_keys($out);
    }
}
