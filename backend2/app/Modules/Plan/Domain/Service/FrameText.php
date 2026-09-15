<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Lesson\Phrase;

/**
 * A FRAME SAID WITH A FILLER — the server's own assembly of a learner line (`lesson_day.v4.5`,
 * LEARNER MESSAGES: «apart from the glue, text_target must equal the substituted frame character by
 * character»).
 *
 * The slot is `___` (three underscores or more); the filler goes in its place. A line is the frame
 * with its filler, optionally after leading conversational glue («Yes, », «Okay, »): a prefix of at
 * most three words that ends in a comma, a full stop, an exclamation mark or a dash. Where glue
 * stands, the frame's first letter may be lowered («Okay, he will rest at home.» for «He will rest
 * ___.») — that one letter is the only difference the comparison forgives.
 */
final class FrameText
{
    public const SLOT_PATTERN = '/_{3,}/u';

    private const GLUE_MAX_WORDS = 3;

    public static function hasSlot(string $frame): bool
    {
        return preg_match(self::SLOT_PATTERN, $frame) === 1;
    }

    /**
     * The frame with the filler in its slot; a frame without a slot, or no filler, is the frame itself.
     * A space the frame keeps between its slot and the closing mark («I work ___ .») is typography, not
     * a word: the sentence said with a filler has none («I work from home.»).
     */
    public static function fill(string $frame, ?string $filler): string
    {
        $said = $filler === null || ! self::hasSlot($frame)
            ? $frame
            : (string) preg_replace(self::SLOT_PATTERN, addcslashes($filler, '\\$'), $frame, 1);
        if ($filler !== null && self::hasSlot($frame)) {
            $said = (string) preg_replace('/\s+([.,!?;:…])/u', '$1', $said);
        }

        return trim($said);
    }

    /**
     * The line the server serves for a learner message that stands on `$phrase` with `$filler`, and
     * whether the model's `$modelText` is exactly that line.
     *
     * The model's glue is kept when the rest of its text IS the frame with the filler; otherwise the
     * served line is the frame with the filler and no glue, and `matches` is false. A line that cannot
     * be put together at all — a slot and no filler, a second slot left after filling — is served as
     * the model wrote it (a `___` never reaches the learner) and does not match either.
     *
     * @return array{text: string, matches: bool, glue: string}
     */
    public static function line(Phrase $phrase, ?string $filler, string $modelText): array
    {
        $said = trim($modelText);
        $core = self::hasSlot($phrase->frameTarget) && $filler === null ? '' : self::fill($phrase->frameTarget, $filler);
        if ($core === '' || self::hasSlot($core)) {
            return ['text' => $said, 'matches' => false, 'glue' => ''];
        }
        if (self::equalButFirstLetterCase($said, $core)) {
            return ['text' => $said, 'matches' => true, 'glue' => ''];
        }

        $coreLength = mb_strlen($core);
        if (mb_strlen($said) > $coreLength) {
            $tail = mb_substr($said, -$coreLength);
            $glue = mb_substr($said, 0, mb_strlen($said) - $coreLength);
            if (self::equalButFirstLetterCase($tail, $core) && self::isGlue($glue)) {
                return ['text' => $said, 'matches' => true, 'glue' => $glue];
            }
        }

        return ['text' => $core, 'matches' => false, 'glue' => ''];
    }

    /**
     * Leading conversational glue of any learner line, by its shape: a short prefix up to the first
     * comma («Yes, », «Okay, thanks, »). What «10 words, not counting leading glue» leaves out.
     */
    public static function leadingGlue(string $text): string
    {
        if (preg_match('/^((?:[\p{L}\'’]+\s*){1,'.self::GLUE_MAX_WORDS.'}[,!—–]\s+)/u', trim($text), $m) !== 1) {
            return '';
        }

        return $m[1];
    }

    /** The line's words, without its leading glue. */
    public static function wordsWithoutGlue(string $text): int
    {
        $text = trim($text);

        return Words::count(mb_substr($text, mb_strlen(self::leadingGlue($text))));
    }

    private static function isGlue(string $prefix): bool
    {
        $prefix = trim($prefix);
        if ($prefix === '' || preg_match('/[,.!—–-]$/u', $prefix) !== 1) {
            return false;
        }

        return Words::count($prefix) <= self::GLUE_MAX_WORDS;
    }

    private static function equalButFirstLetterCase(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        if ($a === '' || $b === '' || mb_substr($a, 1) !== mb_substr($b, 1)) {
            return false;
        }

        return mb_strtolower(mb_substr($a, 0, 1)) === mb_strtolower(mb_substr($b, 0, 1));
    }
}
