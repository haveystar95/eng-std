<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

use App\Modules\Shared\Domain\ValueObject\SpeechMode;
use App\Modules\Shared\Domain\ValueObject\SpeechPack;

/**
 * DID THE LEARNER SAY IT — the ONE rule of spoken grading in the product (наряд FIX-2, п. 2; хвост SESSION-1a).
 *
 * It used to be two: `Plan/Domain/Service/SpeechCoverage` for the day of the plan and
 * `Learning/Domain/Service/SpokenCoverage` for the collections trainer, written twice because Plan cannot import
 * Learning. Two rules that answer «сколько реплики человек сказал» drift silently, and they had: one re-cut the
 * recogniser's word boundaries and forgave a dropped trailing sibilant, the other did neither. Both are gone; this
 * is what is left, and it lives in the kernel precisely so that neither module owns it.
 *
 * ## Two modes, and the card says which ({@see SpeechMode})
 *
 * `repeat` — THE TEXT IS ON SCREEN. Every content word of the expected text must be heard, IN ITS ORDER; the words
 * a recogniser eats (`unstressed_words` of the target's pack) are left out of both sides, and extra words heard
 * around the line do not matter. `plan.speech.repeat_misses` in the config says how many content words may go
 * missing anyway — zero, until a phone proves otherwise.
 *
 * A share was the wrong bar here, and it cost a live day: «He has a rush» is 70 % of «He has a rash» and was
 * graded «верно» on the owner's phone (проход 20.09, п. 2). Seventy per cent of a sentence the learner is LOOKING
 * AT is not the sentence.
 *
 * `free` — THE LEARNER SAYS THEIR OWN SENTENCE and only its key is checked: a share ({@see minFor()}) of the words
 * the card asked for — for the plan, the frame's own words outside the window. All of a short key, most of a long
 * one, counted as a MULTISET and order-free: a recogniser drops and swaps words, it does not reorder them.
 *
 * ## What is spelling and what is speech
 *
 * Both modes compare {@see words()}: the kernel's canonical form ({@see LexicalNormalizer::canonicalize()} — case,
 * punctuation, English contractions: «I'd» is «I would»), plus two foldings the PACK supplies — an abbreviation to
 * its letters («p.m.» → `pm`, наряд CHECK-1's key read a second time) and the words of a number to its digits, the
 * words of one number read as one — «forty-five» and «forty five» are `45`, «a hundred» is `100` ({@see SpokenNumbers},
 * наряд FIX-3 §4). On top of that the recogniser's own two habits are forgiven wherever words are counted: a boundary it
 * guessed differently ({@see SpokenWordBoundary}) and a trailing sibilant it did not hear
 * ({@see SpokenSuffixTolerance}).
 *
 * Pure: no clock, no storage, no language of its own. Mirrored in Dart, and the mirror may never be STRICTER than
 * this — the phone shows the verdict, the server keeps it.
 */
final readonly class SpeechMatch
{
    /** «Say all of it» — the share a short key asks for. */
    public const ALL = 1.0;

    /** «Say most of it» — the share a longer key asks for. */
    public const MOST = 0.7;

    /** Up to how many counted words a key is short enough to be asked for whole. */
    public const SHORT_WORDS = 2;

    /** 7 of 10 is exactly 0.7, and a float says otherwise. */
    private const EPSILON = 1e-9;

    public function __construct(
        private LexicalNormalizer $normalizer = new LexicalNormalizer,
        private SpokenWordBoundary $boundary = new SpokenWordBoundary,
        private SpokenSuffixTolerance $suffix = new SpokenSuffixTolerance,
    ) {}

    /**
     * THE VERDICT OF ONE ATTEMPT, by the mode the card was dealt with.
     *
     * @param  string  $expected  `repeat`: the whole line. `free`: the key — the words the card asks for
     * @param  int  $misses  `repeat` only: content words allowed to go missing anyway (config, default 0)
     */
    public function said(string $heard, string $expected, SpeechMode $mode, SpeechPack $pack, int $misses = 0): bool
    {
        return match ($mode) {
            SpeechMode::Repeat => $this->repeated($heard, $expected, $pack, $misses),
            SpeechMode::Free => $this->covers($heard, $expected, $this->minFor($expected, $pack), $pack),
        };
    }

    /**
     * `repeat`: every content word of `$expected`, in its order, in what was heard — `$misses` of them forgiven.
     *
     * The walk is over the EXPECTED words: each is looked for at or after the place the previous one was found, so
     * a line said in another order is not this line, while everything the learner said around it is ignored. A
     * text whose every word is unstressed (a frame of nothing but function words) is asked for whole — there is no
     * content to anchor on, so nothing may be dropped.
     */
    public function repeated(string $heard, string $expected, SpeechPack $pack, int $misses = 0): bool
    {
        $all = $this->words($expected, $pack);
        $wanted = self::without($all, $pack->unstressed);
        // A text of nothing but function words has no content to anchor on, so it is asked for WHOLE — and then the
        // function words are not dropped from what was heard either, or there would be nothing left to find them in.
        $drop = $wanted === [] ? [] : $pack->unstressed;
        $wanted = $wanted === [] ? $all : $wanted;
        if ($wanted === []) {
            return false;
        }
        $said = self::without($this->boundary->align($this->words($heard, $pack), $all), $drop);

        $at = 0;
        $lost = 0;
        foreach ($wanted as $word) {
            $found = null;
            for ($i = $at, $n = count($said); $i < $n; $i++) {
                if ($word === $said[$i] || $this->suffix->equal($word, $said[$i])) {
                    $found = $i;
                    break;
                }
            }
            if ($found === null) {
                $lost++;
                continue;
            }
            $at = $found + 1;
        }

        return $lost <= max(0, $misses);
    }

    /** `free`: were at least `$min` of the key's words (articles aside) heard, each heard word spent once? */
    public function covers(string $heard, string $expected, float $min, SpeechPack $pack): bool
    {
        $wanted = self::without($this->words($expected, $pack), $pack->articles);
        if ($wanted === []) {
            return false;
        }

        return $this->ratio($heard, $expected, $pack) + self::EPSILON >= $min;
    }

    /**
     * The share of the key's words (articles aside) present in what was heard, 0…1. An empty key covers nothing —
     * «the card asked for nothing» must not read as «the learner said it».
     */
    public function ratio(string $heard, string $expected, SpeechPack $pack): float
    {
        $expectedWords = $this->words($expected, $pack);
        $wanted = self::without($expectedWords, $pack->articles);
        if ($wanted === []) {
            return 0.0;
        }
        $available = array_count_values($this->boundary->align($this->words($heard, $pack), $expectedWords));
        $found = 0;
        foreach ($wanted as $word) {
            if ($this->consume($available, $word)) {
                $found++;
            }
        }

        return $found / count($wanted);
    }

    /** How many words of the key count: every word but the target's articles. */
    public function countedWords(string $expected, SpeechPack $pack): int
    {
        return count(self::without($this->words($expected, $pack), $pack->articles));
    }

    /** The share a key asks for: all of a short one, most of a longer one. */
    public function minFor(string $expected, SpeechPack $pack): float
    {
        return $this->countedWords($expected, $pack) <= self::SHORT_WORDS ? self::ALL : self::MOST;
    }

    /**
     * КАКИХ СЛОВ ОЖИДАЕМОГО НЕ ХВАТИЛО — в том виде, в каком они написаны, в порядке чтения (наряд SPEECH-2, Ч.3.5;
     * зеркало клиентского `SessionGrader.uncoveredWords`).
     *
     * Считается тем же ходом, что и {@see ratio()} — те же границы слов, тот же хвостовой сибилянт, тот же
     * мультимножественный расход, — иначе вердикт говорил бы «не хватило X», когда покрытие X засчитало.
     * Группировка по НАПИСАННОМУ слову, а не по канонизированному токену: «don't» — одно слово на экране и два
     * токена внутри, и человеку показывают слово.
     *
     * @return list<string>
     */
    public function missing(string $heard, string $expected, SpeechPack $pack): array
    {
        $raw = trim($expected);
        if ($raw === '') {
            return [];
        }
        $available = array_count_values($this->boundary->align($this->words($heard, $pack), $this->words($expected, $pack)));

        $missing = [];
        foreach (preg_split('/\s+/u', $raw) ?: [] as $word) {
            $tokens = $this->words($word, $pack);
            // Слово, которое канонизируется в пустоту (одна пунктуация), не пропущено и не найдено.
            if ($tokens === []) {
                continue;
            }
            foreach ($tokens as $token) {
                if (! $this->consume($available, $token)) {
                    $missing[] = $word;
                    break;
                }
            }
        }

        return $missing;
    }

    /** Were the words of `$value` (a filler) heard one after another — the target's articles aside on both sides? */
    public function containsSequence(string $heard, string $value, SpeechPack $pack): bool
    {
        $needle = self::without($this->words($value, $pack), $pack->articles);
        $haystack = self::without($this->boundary->align($this->words($heard, $pack), $this->words($value, $pack)), $pack->articles);
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
     * WHAT WAS HEARD BEYOND THE KEY — the words of `$heard` that `$key`'s own words do not account for, as heard,
     * in the order heard: the value of a window when the judge cannot rule on it.
     *
     * Heard is canonicalised ONCE, as a whole, the way {@see ratio()} canonicalises it, and every surface word is
     * credited with the tokens it contributes to THAT fold — what the fold loses when the word is dropped. A word
     * folded alone is a different word («He's» is `hes` on its own and `he has` in front of «been», the one rule
     * the canonicaliser reads forward), and a key word folded one way on one side of the comparison comes back as
     * the learner's own value: «He's been resting since Monday» against the key «He's been resting» handed back
     * «He's since Monday». Counting from the suffix is exact here precisely because that rule looks forward and no
     * other does.
     */
    public function slotWords(string $heard, string $key, SpeechPack $pack): string
    {
        $available = array_count_values($this->words($key, $pack));
        $out = [];
        foreach ($this->surfaceWords($heard, $pack) as [$surface, $tokens]) {
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
            $out[] = $surface;
        }

        return implode(' ', $out);
    }

    /**
     * EVERY WORD OF A TEXT AS WRITTEN, WITH THE COMPARABLE WORDS IT MAKES ({@see words()}) — so a rule that finds its
     * words among the comparable ones can hand back what was actually said. The text is folded ONCE, as a whole, and
     * each written word is credited with the comparable words it contributes to that fold: what the fold of the rest
     * loses when the word is left out. A word folded alone is a different word («He's» is `hes` on its own and `he has`
     * in front of «been», the one rule the canonicaliser reads forward), and counting from the end is exact precisely
     * because that rule looks forward and no other does. A written word that makes no comparable word of its own is part
     * of the next one's — «forty» of «forty five» goes where `45` goes; marks around a word are not the word.
     *
     * @return list<array{0: string, 1: list<string>}> each written word (or run of words that fold together) and its comparable words
     */
    public function surfaceWords(string $text, SpeechPack $pack): array
    {
        $surface = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stream = $this->words(implode(' ', $surface), $pack);
        $out = [];
        $taken = 0;
        $pending = [];
        foreach ($surface as $i => $raw) {
            $rest = count($this->words(implode(' ', array_slice($surface, $i + 1)), $pack));
            $tokens = array_slice($stream, $taken, max(0, count($stream) - $rest - $taken));
            $taken += count($tokens);
            $pending[] = (string) preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $raw);
            if ($tokens === []) {
                continue;
            }
            $out[] = [implode(' ', array_filter($pending, static fn (string $w): bool => $w !== '')), $tokens];
            $pending = [];
        }

        return $out;
    }

    /**
     * THE COMPARABLE WORDS OF A TEXT — the one order of the foldings, the same on the phone: (1) the pack's
     * abbreviations to their letters, (2) the kernel's canonical form — case, English contractions spelt out («I'll» is
     * «I will»), every mark but the apostrophe a space (the hyphen too: «forty-five» is «forty five»), (3) the words of a
     * number to its digits, the words of one number read as one ({@see SpokenNumbers}: «forty five» → `45`, «a hundred»
     * → `100`, «one minute» → `1 minute`).
     *
     * The abbreviation goes FIRST because its dots would otherwise become word breaks («p.m.» → `p m`); the numbers go
     * LAST because a number is made of whole words, and there is nothing to read until the words exist.
     *
     * @return list<string>
     */
    public function words(string $text, SpeechPack $pack = new SpeechPack): array
    {
        $canonical = $this->normalizer->canonicalize(self::foldAbbreviations($text, $pack->abbreviations));
        if ($canonical === '') {
            return [];
        }

        return SpokenNumbers::fold(
            explode(' ', $canonical),
            $pack->numberWords,
            $pack->articles,
            $pack->numberJoiners,
            $pack->numberTensJoiners,
        );
    }

    /**
     * An abbreviation written as its letters: «3 p.m.» → «3 pm». Matched as a whole token, letter case aside, so
     * «St. Petersburg» loses the dot of «St.» and nothing else does.
     *
     * @param  list<string>  $abbreviations
     */
    private static function foldAbbreviations(string $text, array $abbreviations): string
    {
        foreach ($abbreviations as $abbreviation) {
            $bare = str_replace('.', '', $abbreviation);
            if ($bare === '') {
                continue;
            }
            $text = (string) preg_replace(
                '/(?<![\p{L}\p{N}])'.preg_quote($abbreviation, '/').'/iu',
                $bare,
                $text,
            );
        }

        return $text;
    }

    /**
     * Marks one occurrence of `$word` as used in `$available` and returns true — exact first, then the
     * suffix-tolerant match ({@see SpokenSuffixTolerance}).
     *
     * `(string) $candidate`, and not decoration: `array_count_values` hands back an INT key for a word that is all
     * digits, so a transcript containing «5» — exactly what a recogniser writes for «five» — made the tolerant scan
     * throw a TypeError and 500 a whole review batch (BUGFIX-2 Ч.3б).
     *
     * @param  array<array-key, int>  $available
     */
    private function consume(array &$available, string $word): bool
    {
        if (($available[$word] ?? 0) > 0) {
            $available[$word]--;

            return true;
        }
        foreach ($available as $candidate => $count) {
            if ($count > 0 && $this->suffix->equal($word, (string) $candidate)) {
                $available[$candidate]--;

                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $words
     * @param  list<string>  $drop
     * @return list<string>
     */
    private static function without(array $words, array $drop): array
    {
        if ($drop === []) {
            return $words;
        }
        $index = array_fill_keys($drop, true);

        return array_values(array_filter($words, static fn (string $w): bool => ! isset($index[$w])));
    }
}
