<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Lesson\Filler;
use App\Modules\Plan\Domain\Lesson\Phrase;

/**
 * A FRAME SAID WITH A FILLER — the server's own reading of a learner line (`lesson_day.v4.5`, LEARNER MESSAGES:
 * «apart from the glue, text_target must equal the substituted frame character by character»; доработка GEN-2b).
 *
 * The slot is `___` (three underscores or more); a filler goes in its place. A line is the frame with ONE OF ITS
 * FILLERS, optionally after leading conversational glue («Yes, », «Okay, »): a prefix of at most three words that ends
 * in a comma, a full stop, an exclamation mark or a dash. Which filler the line says is found by its text — the
 * model's own `filler` field is read by nobody. Two things the comparison forgives, and nothing else: where glue
 * stands, the frame's first letter may be lowered («Okay, he will rest at home.» for «He will rest ___.»), and the
 * mark that ends the sentence (. ! ? …) is left out on both sides — a frame written without its full stop is still
 * the line that has one.
 */
final class FrameText
{
    public const SLOT_PATTERN = '/_{3,}/u';

    /** The mark a sentence ends with, as the assembly reads it — the same for every language of a lesson. */
    private const END_MARK = '/[.!?…]+$/u';

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
     * The line the server serves for a learner message that stands on `$phrase`, whether the model's `$modelText` is
     * that frame with one of its fillers, and which one.
     *
     * The fillers are tried in the frame's order; the first whose sentence the text is (glue, one lowered letter and
     * the closing mark aside) is the line's filler, and the model's text is served as written — its glue and its own
     * closing mark. A frame without a slot is its own sentence. When no filler makes the text — or the frame cannot
     * be said at all (a slot and no fillers, a second slot left) — nothing matches, and the text is served as the
     * model wrote it: there is no filler to put it together with, and the day is not dealt until a repair
     * (`line.ne_frame`).
     *
     * @return array{text: string, matches: bool, glue: string, filler: Filler|null}
     */
    public static function line(Phrase $phrase, string $modelText): array
    {
        $said = trim($modelText);
        if (! self::hasSlot($phrase->frameTarget)) {
            $glue = self::glueBefore($said, self::fill($phrase->frameTarget, null));

            return ['text' => $said, 'matches' => $glue !== null, 'glue' => $glue ?? '', 'filler' => null];
        }
        foreach ($phrase->fillers() as $filler) {
            $core = self::fill($phrase->frameTarget, $filler->target);
            if (self::hasSlot($core)) {
                continue;
            }
            $glue = self::glueBefore($said, $core);
            if ($glue !== null) {
                return ['text' => $said, 'matches' => true, 'glue' => $glue, 'filler' => $filler];
            }
        }

        return ['text' => $said, 'matches' => false, 'glue' => '', 'filler' => null];
    }

    /** The mark a text ends with — a run of . ! ? … — or '' when it ends with none. */
    public static function endMark(string $text): string
    {
        return preg_match(self::END_MARK, trim($text), $m) === 1 ? $m[0] : '';
    }

    /** The text without the mark it ends with, and without the space before that mark. */
    public static function withoutEndMark(string $text): string
    {
        return rtrim((string) preg_replace(self::END_MARK, '', trim($text)));
    }

    /** `$text` with the closing mark of `$from` when it has none of its own — what a frame written without one borrows. */
    public static function withEndMarkOf(string $text, string $from): string
    {
        return self::endMark($text) === '' ? rtrim($text).self::endMark($from) : $text;
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

    /**
     * The glue before `$core` when `$said` is `$core` — '' for none; null when it is not. Both closing marks are left
     * out of the comparison; the rest is character by character, the first letter of the frame in either case.
     */
    private static function glueBefore(string $said, string $core): ?string
    {
        $said = self::withoutEndMark($said);
        $core = self::withoutEndMark($core);
        if ($core === '') {
            return null;
        }
        if (self::equalButFirstLetterCase($said, $core)) {
            return '';
        }

        $coreLength = mb_strlen($core);
        if (mb_strlen($said) > $coreLength) {
            $tail = mb_substr($said, -$coreLength);
            $glue = mb_substr($said, 0, mb_strlen($said) - $coreLength);
            if (self::equalButFirstLetterCase($tail, $core) && self::isGlue($glue)) {
                return $glue;
            }
        }

        return null;
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
