<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Service\Shuffle;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;

/**
 * THE OPTIONS OF A CHOICE CARD (наряд SESSION-1a, разд. 0): the right one and the wrong ones, none of them equal to
 * another by text — case and the spaces around aside — shuffled by the card's own seed, and numbered `o1…` in the
 * order they are SHOWN, so an id says nothing about which one is right.
 *
 * The wrong ones are taken in the order the card offers them — its own material first, a top-up after — so a short
 * card runs out of the top-up, never of its own material.
 *
 * WHICH KEY HOLDS THE TEXT is the caller's (наряд BACK-TAILS-1): most cards offer one string and call it `text`, but
 * `listen_predict` offers whole lines of the day — `text_target`, `text_native` and a sound apiece — and there the
 * identity of an option is the line it is, its target text. Whatever key is named is the one two options may not
 * share; every other key of an option rides along untouched.
 *
 * TWO OPTIONS THAT MEAN THE SAME THING are not two options (наряд FIX-2, пп. 1 и 4). A card that asks the learner to
 * pick a MEANING — the options on their own language, and the lines of «Что прозвучит в ответ?» — passes
 * {@see APART}, and a candidate that shares that much of its words with the right one or with a candidate already
 * taken is dropped, the next one tried in its place. «Что мне нужно принести на приём?» beside «…на визит?»
 * marked a right answer wrong on the owner's phone (проход 20.09, п. 1); «Please bring his vaccination record and
 * arrive ten minutes early.» beside the rescue line that restates it made three indistinguishable bars (п. 4).
 *
 * A card that asks the learner to pick a FORM does NOT pass it: the fillers of one window («lower back», «upper
 * back») and the amounts of «Поймай число» («два дня», «два часа») are meant to stand close together — that is the
 * whole exercise.
 */
final class Options
{
    /** The key an option's text lives under unless the card says otherwise. */
    public const TEXT = 'text';

    /**
     * How much of what TWO OPTIONS SAY BETWEEN THEM may be the same words, when the card asks for a MEANING: half.
     *
     * Counted over the two together — shared words against every word either of them has — and not over the shorter
     * one, because a short sentence in an inflected language is mostly function words: «Температуры у него нет.» and
     * «У него болит плечо.» share «у него» and nothing else, which is two of four words of each and 33 % of the six
     * words they have between them. The first reading would throw away a perfectly good option; the second keeps it
     * and still catches «Что мне нужно принести на приём?» beside «…на визит?» at 71 %.
     */
    public const APART = 0.5;

    /**
     * The fewest options a choice card is dealt with: the right one and one wrong one. A card left with fewer — a day
     * with nothing to tell its only word or frame from — is not dealt at all; a choice with one answer is no check.
     */
    public const MIN = 2;

    /**
     * @param  array<string, mixed>  $correct  the right option: its text (under `$textKey`) and any keys it carries (`audio`)
     * @param  list<array<string, mixed>>  $candidates  the wrong ones, in the order they are preferred
     * @param  string  $textKey  the key two options may not share — `text`, or `text_target` for options that are lines
     * @param  float|null  $apart  the share of words two options may not share ({@see APART}); null — only equal texts are one option
     * @return array{options: list<array<string, mixed>>, correct: string}
     */
    public static function choose(string $seed, array $correct, array $candidates, int $size, string $textKey = self::TEXT, ?float $apart = null): array
    {
        $correct[$textKey] = trim((string) $correct[$textKey]);
        $seen = [self::key($correct[$textKey]) => true];
        $chosen = [$correct];
        $words = [self::words($correct[$textKey])];
        foreach ($candidates as $candidate) {
            if (count($chosen) >= $size) {
                break;
            }
            $text = trim((string) $candidate[$textKey]);
            $key = self::key($text);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $mine = self::words($text);
            if ($apart !== null && self::tooClose($mine, $words, $apart)) {
                continue;
            }
            $seen[$key] = true;
            $words[] = $mine;
            $candidate[$textKey] = $text;
            $chosen[] = $candidate;
        }

        $indexes = Shuffle::seeded($seed, array_keys($chosen));
        $options = [];
        $correctId = '';
        foreach ($indexes as $shown => $index) {
            $id = 'o'.($shown + 1);
            $options[] = ['id' => $id, ...$chosen[$index]];
            if ($index === 0) {
                $correctId = $id;
            }
        }

        return ['options' => $options, 'correct' => $correctId];
    }

    private static function key(string $text): string
    {
        return mb_strtolower(trim($text));
    }

    /**
     * Does this candidate read as one of the options already taken — at least `$apart` of the words the two have
     * between them shared, counted as a MULTISET so a sentence saying a word twice needs it twice?
     *
     * @param  list<string>  $mine
     * @param  list<list<string>>  $taken
     */
    private static function tooClose(array $mine, array $taken, float $apart): bool
    {
        foreach ($taken as $other) {
            if ($mine === [] || $other === []) {
                continue;
            }
            $left = array_count_values($other);
            $shared = 0;
            foreach ($mine as $word) {
                if (($left[$word] ?? 0) > 0) {
                    $left[$word]--;
                    $shared++;
                }
            }
            if ($shared / (count($mine) + count($other) - $shared) >= $apart) {
                return true;
            }
        }

        return false;
    }

    /**
     * The comparable words of an option — the kernel's canonical form, the one the whole product compares words in
     * ({@see LexicalNormalizer::canonicalize()}): case, punctuation and the apostrophe aside.
     *
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $canonical = (new LexicalNormalizer)->canonicalize($text);

        return $canonical === '' ? [] : explode(' ', $canonical);
    }
}
