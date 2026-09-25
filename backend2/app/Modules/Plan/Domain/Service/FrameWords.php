<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Shared\Domain\Service\TextNormalizer;
use WeakMap;

/**
 * THE WORDS THE JUDGE OF THE CONSTRUCTIONS COMPARES (наряд FIX-4 §2) — the frame and the move in one form: lower case;
 * the pack's contractions spelt out («I'm» → «i am», «don't» → «do not», «he's been» → «he has been»); every mark gone
 * — a hyphen or a slash splits a word, an apostrophe left after the contractions joins its letters; the articles left
 * out unless asked for (a, an, the take no part in the comparison); the spaces one. Numbers stay as said: the window is
 * free, and outside it a frame has none.
 *
 * ONE SPELLING OF A LETTER (наряд LANG-1 §1): every written word is read in the kernel's comparison form first
 * ({@see TextNormalizer::fold()}) — composed, the Romanian cedilla letters written with the comma below (ş → ș, ţ → ț),
 * ß read as ss and œ as oe. A recogniser writes «Straße» one day and «Strasse» the next, «şi» with a cedilla where the
 * frame has «și»; the learner said one word each time. The pack's lists this class and {@see FrameJudge} match words
 * against — articles, contractions, negation, opening and dangling words, conjunctions — are read in that form too, so a
 * pack may write «Straße» or «strasse»; the key spec (`docs/research/lang-1/pack-keys.md`) asks for the folded one.
 *
 * A WORD THAT BEGINS WITH AN ELISION IS ITS TWO WORDS (наряд LANG-1 §1): an entry of the pack's `contractions` whose key
 * ends with an apostrophe is a prefix, not a word — a written word that starts with it and has a letter after it is read
 * as the entry's value and the rest: fr «j'ai» is «je ai», «n'ai» «ne ai», «l'hôpital» «le hôpital»; it «dell'ospedale»
 * is «dello ospedale», «un'ora» «una ora». Read before the apostrophe is deleted and after the whole-word entries — a
 * word the pack spells out whole («s'il» → «si il») is never cut at its elision («s'» → «se»). Either apostrophe (' or ’)
 * counts, in the text and in the pack's keys. So «Je n'ai pas de fièvre» and the frame «J'ai ___» share «je … ai», and
 * the negation between them is the pack's to forgive ({@see FrameJudge}).
 *
 * A move is read sentence by sentence, where a sentence ends by the language's own rule ({@see
 * \App\Modules\Plan\Domain\Check\Language\SentenceEnds}: «3 p.m. today» ends nowhere), and every word keeps the written
 * word it came from — what the window's value is read back from («about one year», «45 seconds») — as written, not folded.
 */
final class FrameWords
{
    /** Every glyph a keyboard or a recogniser writes an apostrophe with — read as the plain one before anything else. */
    private const APOSTROPHES = ['’', '‘', '´', '`'];

    /**
     * WHAT A PACK GIVES THE READING, READ ONCE PER PACK (наряд LANG-1 §1, проверка исполнителя): its articles, contractions
     * and elisions in the comparison form. The judge reads a fragment dozens of times a move — every opening word and
     * conjunction of the pack, every part of every frame — and folding the pack's fifty contractions on each of them made
     * one move of fourteen frames ten times slower (2.4 → 29 ms). A pack is immutable ({@see LanguagePack} is readonly),
     * so what is read of one stays true while it lives; the map forgets a pack nobody holds any more.
     *
     * @var WeakMap<LanguagePack, array{articles: array<string, true>, contractions: array<string, string>, elisions: array<string, string>}>|null
     */
    private static ?WeakMap $packs = null;

    private static ?TextNormalizer $fold = null;

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
        $fold = self::$fold ??= new TextNormalizer;
        $written = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $trimmed = array_map(static fn (string $w): string => (string) preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $w), $written);
        $cores = array_map(
            static fn (string $w): string => (string) preg_replace("/^[^\\p{L}\\p{N}']+|[^\\p{L}\\p{N}']+$/u", '', self::canonical($w, $fold)),
            $written,
        );
        $read = self::packReading($pack, $fold);
        $drop = $articles ? [] : $read['articles'];
        $contractions = $read['contractions'];
        $elisions = $read['elisions'];

        $words = [];
        $at = [];
        foreach ($cores as $i => $core) {
            foreach (self::spelt($core, $cores[$i + 1] ?? null, $pack, $contractions, $elisions) as $word) {
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
     * (`contractions_before`: «'s» before «been» is «has»), else by the pack's list as a whole word, else by an elision it
     * begins with («j'» + «ai») — and anything else split at its marks.
     *
     * @param  array<string, string>  $contractions  the pack's `contractions`, keys in the comparison form
     * @param  array<string, string>  $elisions  the entries of them that are prefixes (the key ends with an apostrophe)
     * @return list<string>
     */
    private static function spelt(string $core, ?string $next, LanguagePack $pack, array $contractions, array $elisions): array
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
        if (isset($contractions[$core])) {
            return preg_split('/\s+/u', trim($contractions[$core]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        foreach ($elisions as $prefix => $word) {
            if (! str_starts_with($core, $prefix)) {
                continue;
            }
            $rest = substr($core, strlen($prefix));
            if (preg_match('/^\p{L}/u', $rest) === 1) {
                return [...(preg_split('/\s+/u', trim($word), -1, PREG_SPLIT_NO_EMPTY) ?: []), ...self::spelt($rest, $next, $pack, $contractions, $elisions)];
            }
        }

        return self::pieces($core);
    }

    /**
     * What the reading takes of the pack — its articles, contractions and elisions in the comparison form — read once per
     * pack ({@see self::$packs}).
     *
     * @return array{articles: array<string, true>, contractions: array<string, string>, elisions: array<string, string>}
     */
    private static function packReading(LanguagePack $pack, TextNormalizer $fold): array
    {
        self::$packs ??= new WeakMap;
        if (! isset(self::$packs[$pack])) {
            $contractions = self::contractions($pack, $fold);
            self::$packs[$pack] = [
                'articles' => $pack->has('articles')
                    ? array_fill_keys(array_map(static fn (string $a): string => self::canonical($a, $fold), $pack->words('articles')), true)
                    : [],
                'contractions' => $contractions,
                'elisions' => self::elisions($contractions),
            ];
        }

        return self::$packs[$pack];
    }

    /**
     * The pack's `contractions` with their keys in the form the words are compared in (folded, lower case, the plain
     * apostrophe) — so a pack that writes «J’» finds «j'ai». A key the pack does not write is an empty map.
     *
     * @return array<string, string>
     */
    private static function contractions(LanguagePack $pack, TextNormalizer $fold): array
    {
        $out = [];
        foreach ($pack->has('contractions') ? $pack->map('contractions') : [] as $key => $words) {
            $key = self::canonical((string) $key, $fold);
            if ($key !== '' && is_string($words)) {
                $out[$key] = self::canonical($words, $fold);
            }
        }

        return $out;
    }

    /**
     * The contractions that are ELISIONS — a key of more than the apostrophe that ends with one («j'», «dell'»). A word
     * starts with one of them at most: two such keys cannot be one the start of the other, the apostrophe of the shorter
     * standing where the longer has a letter.
     *
     * @param  array<string, string>  $contractions
     * @return array<string, string>
     */
    private static function elisions(array $contractions): array
    {
        return array_filter($contractions, static fn (string $key): bool => strlen($key) > 1 && str_ends_with($key, "'"), ARRAY_FILTER_USE_KEY);
    }

    /** A written word in the form the comparison reads: folded ({@see TextNormalizer::fold()}), lower case, one apostrophe. */
    private static function canonical(string $word, TextNormalizer $fold): string
    {
        return str_replace(self::APOSTROPHES, "'", mb_strtolower($fold->fold($word)));
    }

    /** @return list<string> */
    private static function pieces(string $word): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', str_replace("'", '', $word), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
