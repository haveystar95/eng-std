<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Blueprint;

/**
 * THE WORDS OF A SURVIVAL SET, AS THE CODE READS THEM (наряд GEN-4) — the set is English whatever the pair (`plan-builder-v2.1`,
 * STEP 4), so its items are read by one small English reading: the verb an item starts with, and its CONTENT words — lower
 * case, the words that carry no subject of their own left out, each cut to its stem by the plainest English endings
 * («duties» — «duty», «worked» — «work»). No meaning is judged: two items «share» a subject when they share a content word.
 */
final class SurvivalWords
{
    /** The verbs a `must_say` item starts with (`plan-builder-v2.1`, STEP 4). */
    public const SAY_VERBS = ['say', 'ask', 'answer', 'confirm', 'explain', 'give'];

    /** What carries no subject of its own in an item of the set: its verbs, the learner and the partner, the question words. */
    private const EMPTY = [
        'say', 'says', 'ask', 'asks', 'answer', 'answers', 'confirm', 'confirms', 'explain', 'explains', 'give', 'gives',
        'tell', 'tells', 'offer', 'offers', 'you', 'your', 'yours', 'the', 'a', 'an', 'and', 'or', 'but', 'what', 'which',
        'who', 'whom', 'whose', 'where', 'when', 'why', 'how', 'whether', 'if', 'is', 'are', 'was', 'were', 'be', 'been',
        'do', 'does', 'did', 'have', 'has', 'had', 'for', 'to', 'of', 'in', 'on', 'at', 'about', 'with', 'from', 'by',
        'any', 'anything', 'something', 'someone', 'there', 'it', 'its', 'this', 'that', 'these', 'those', 'they', 'them',
        'he', 'she', 'him', 'her', 'his', 'would', 'will', 'can', 'could', 'should', 'may', 'might', 'want', 'wants',
        'like', 'please', 'one', 'thing', 'things', 'kind', 'slot', 'none', 'not', 'no', 'yes', 'so', 'as', 'than',
        'then', 'into', 'up', 'out', 'over', 'partner', 'learner', 'here', 'now',
    ];

    /** The word an item starts with, in lower case — «Say where…» starts with `say`. */
    public static function verb(string $item): string
    {
        return preg_match('/^\s*([\p{L}]+)/u', $item, $m) === 1 ? mb_strtolower($m[1]) : '';
    }

    /**
     * The item's content words as stems, each once.
     *
     * @return list<string>
     */
    public static function content(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($words as $word) {
            if (in_array($word, self::EMPTY, true) || mb_strlen($word) < 3) {
                continue;
            }
            $out[self::stem($word)] = true;
        }

        return array_keys($out);
    }

    /** Do the two texts share a content word? */
    public static function share(string $a, string $b): bool
    {
        return array_intersect(self::content($a), self::content($b)) !== [];
    }

    /**
     * The plainest English stem: -ies → -y; -ing, -ed off; -es off after s, x, z, ch, sh; -s off (not -ss) — each only when
     * three letters stay.
     */
    private static function stem(string $word): string
    {
        $length = mb_strlen($word);
        if ($length > 4 && str_ends_with($word, 'ies')) {
            return mb_substr($word, 0, -3).'y';
        }
        if ($length >= 6 && str_ends_with($word, 'ing')) {
            return mb_substr($word, 0, -3);
        }
        if ($length >= 5 && str_ends_with($word, 'ed')) {
            return mb_substr($word, 0, -2);
        }
        if ($length >= 5 && preg_match('/(?:s|x|z|ch|sh)es$/u', $word) === 1) {
            return mb_substr($word, 0, -2);
        }
        if ($length >= 4 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss')) {
            return mb_substr($word, 0, -1);
        }

        return $word;
    }
}
