<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;

/**
 * THE SPEAKING KEY OF A LEARNER LINE — the server's, never the model's (доработка GEN-2b, `docs/plan-v2.md` §3а): the
 * few words of the line the learner is asked to say first, taken from the frame the line stands on, so a key never
 * holds the filler and never leaves the line.
 *
 * A line on a frame with a slot: the frame without its closing mark is cut at `___` into the part BEFORE the slot and
 * the part AFTER it. The key is the part with content words — a word that is not one of the target pack's
 * `function_words`; when both parts have some, the one with more, the part before on a tie. When neither has one, or
 * the target has no such pack, it is the part before as written — and the part after when nothing stands before the
 * slot. At most four words: the last four of the part before (the words next to the slot), the first four of the
 * part after. A frame without a slot: its first four words.
 *
 * A line with no frame (a rescue): the model's key when it stands in the line (letter case aside), else the line's
 * first four words.
 */
final class SpeakingKey
{
    public const MAX_WORDS = 4;

    public static function ofFrame(string $frame, LanguagePack $target): ?string
    {
        $bare = FrameText::withoutEndMark($frame);
        if (! FrameText::hasSlot($bare)) {
            return self::first($bare);
        }
        $parts = preg_split(FrameText::SLOT_PATTERN, $bare, 2) ?: [$bare];
        $before = $parts[0];
        $after = $parts[1] ?? '';

        if ($target->has('function_words')) {
            $inBefore = self::contentWords($before, $target);
            $inAfter = self::contentWords($after, $target);
            if ($inBefore > 0 || $inAfter > 0) {
                return $inAfter > $inBefore ? self::first($after) : self::last($before);
            }
        }

        return Words::spans($before) !== [] ? self::last($before) : self::first($after);
    }

    public static function ofLine(string $line, ?string $modelKey): ?string
    {
        $key = trim((string) $modelKey);
        if ($key !== '' && mb_stripos($line, $key) !== false) {
            return $key;
        }

        return self::first($line);
    }

    private static function contentWords(string $part, LanguagePack $target): int
    {
        return count(array_filter(Words::spans($part), static fn (array $word): bool => ! $target->listed('function_words', $word[2])));
    }

    /** The first words of a text as it is written — punctuation inside kept, at the edges left out. */
    private static function first(string $text): ?string
    {
        $words = array_slice(Words::spans($text), 0, self::MAX_WORDS);

        return self::cut($text, $words);
    }

    /** The last words of a text as it is written. */
    private static function last(string $text): ?string
    {
        $words = array_slice(Words::spans($text), -self::MAX_WORDS);

        return self::cut($text, $words);
    }

    /** @param list<array{0: int, 1: int, 2: string}> $words */
    private static function cut(string $text, array $words): ?string
    {
        if ($words === []) {
            return null;
        }
        $from = $words[0][0];

        return mb_substr($text, $from, $words[count($words) - 1][1] - $from);
    }
}
