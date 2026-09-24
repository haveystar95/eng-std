<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;

/**
 * THE WORDS THE JUDGE OF THE CONSTRUCTIONS COMPARES (наряд FIX-4 §2) — the frame and the move in one form: lower case;
 * the pack's contractions spelt out («I'm» → «i am», «don't» → «do not», «he's been» → «he has been»); every mark gone
 * — a hyphen or a slash splits a word, an apostrophe left after the contractions joins its letters; the articles left
 * out unless asked for (a, an, the take no part in the comparison); the spaces one. Numbers stay as said: the window is
 * free, and outside it a frame has none.
 *
 * A move is read sentence by sentence, where a sentence ends by the language's own rule ({@see
 * \App\Modules\Plan\Domain\Check\Language\SentenceEnds}: «3 p.m. today» ends nowhere), and every word keeps the written
 * word it came from — what the window's value is read back from («about one year», «45 seconds»).
 */
final class FrameWords
{
    /**
     * @return list<array{words: list<string>, at: list<int>, written: list<string>}> each sentence: its words, the place of
     *   the written word each came from, and the written words themselves (marks around them trimmed, case kept)
     */
    public static function sentences(string $text, LanguagePack $pack, bool $articles = false): array
    {
        $ends = $pack->sentenceEnds();
        $pieces = $ends === null
            ? (preg_split('/(?<=[.?!…])\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            : $ends->sentences($text);
        $out = [];
        foreach ($pieces as $piece) {
            $sentence = self::read($piece, $pack, $articles);
            if ($sentence['words'] !== []) {
                $out[] = $sentence;
            }
        }

        return $out;
    }

    /**
     * The words of a fragment read whole — a frame's part before or after its window, an opening word of the pack.
     *
     * @return list<string>
     */
    public static function of(string $text, LanguagePack $pack, bool $articles = false): array
    {
        return self::read($text, $pack, $articles)['words'];
    }

    /** @return array{words: list<string>, at: list<int>, written: list<string>} */
    private static function read(string $text, LanguagePack $pack, bool $articles): array
    {
        $written = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $trimmed = array_map(static fn (string $w): string => (string) preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $w), $written);
        $cores = array_map(
            static fn (string $w): string => (string) preg_replace("/^[^\\p{L}\\p{N}']+|[^\\p{L}\\p{N}']+$/u", '', str_replace(['’', '‘', '´', '`'], "'", mb_strtolower($w))),
            $written,
        );
        $drop = $articles || ! $pack->has('articles') ? [] : array_fill_keys($pack->words('articles'), true);

        $words = [];
        $at = [];
        foreach ($cores as $i => $core) {
            foreach (self::spelt($core, $cores[$i + 1] ?? null, $pack) as $word) {
                if (! isset($drop[$word])) {
                    $words[] = $word;
                    $at[] = $i;
                }
            }
        }

        return ['words' => $words, 'at' => $at, 'written' => $trimmed];
    }

    /**
     * One written word as the words it says: a contraction spelt out — by the word after it where the pack says so
     * (`contractions_before`: «'s» before «been» is «has»), else by the pack's list — and anything else split at its marks.
     *
     * @return list<string>
     */
    private static function spelt(string $core, ?string $next, LanguagePack $pack): array
    {
        if ($core === '') {
            return [];
        }
        $before = $next !== null && $pack->has('contractions_before') ? ($pack->map('contractions_before')[$next] ?? null) : null;
        if (is_array($before)) {
            foreach ($before as $tail => $word) {
                if (is_string($word) && str_ends_with($core, (string) $tail) && mb_strlen($core) > mb_strlen((string) $tail)) {
                    return [...self::pieces(mb_substr($core, 0, mb_strlen($core) - mb_strlen((string) $tail))), $word];
                }
            }
        }
        $spelt = $pack->has('contractions') ? ($pack->map('contractions')[$core] ?? null) : null;
        if (is_string($spelt)) {
            return preg_split('/\s+/u', trim($spelt), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return self::pieces($core);
    }

    /** @return list<string> */
    private static function pieces(string $word): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', str_replace("'", '', $word), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
