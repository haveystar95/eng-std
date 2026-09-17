<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Service\Shuffle;

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
 */
final class Options
{
    /** The key an option's text lives under unless the card says otherwise. */
    public const TEXT = 'text';

    /**
     * The fewest options a choice card is dealt with: the right one and one wrong one. A card left with fewer — a day
     * with nothing to tell its only word or frame from — is not dealt at all; a choice with one answer is no check.
     */
    public const MIN = 2;

    /**
     * @param  array<string, mixed>  $correct  the right option: its text (under `$textKey`) and any keys it carries (`audio`)
     * @param  list<array<string, mixed>>  $candidates  the wrong ones, in the order they are preferred
     * @param  string  $textKey  the key two options may not share — `text`, or `text_target` for options that are lines
     * @return array{options: list<array<string, mixed>>, correct: string}
     */
    public static function choose(string $seed, array $correct, array $candidates, int $size, string $textKey = self::TEXT): array
    {
        $correct[$textKey] = trim((string) $correct[$textKey]);
        $seen = [self::key($correct[$textKey]) => true];
        $chosen = [$correct];
        foreach ($candidates as $candidate) {
            if (count($chosen) >= $size) {
                break;
            }
            $text = trim((string) $candidate[$textKey]);
            $key = self::key($text);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
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
}
