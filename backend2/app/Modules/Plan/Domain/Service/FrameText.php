<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Check\Language\SentenceEnds;
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
 *
 * THE FIRST LETTER IS A LETTER (наряд LANG-1 §1): Spanish opens a question and an exclamation with ¿ and ¡, and the
 * mark stays with the words it opens — «Sí, ¿puedo pagar con tarjeta?» is the frame «¿Puedo pagar con ___?» after its
 * glue, exactly as «Yes, can I pay by card?» is «Can I pay ___?». So the letter whose case is forgiven, the letter a
 * sentence is capitalised at and the glue a line opens with are read past those opening marks; the marks themselves are
 * compared character by character like everything else (a line that drops the frame's ¿ is not the frame). Nothing of it
 * touches a language that writes no ¿ ¡.
 */
final class FrameText
{
    public const SLOT_PATTERN = '/_{3,}/u';

    /** The mark a sentence ends with, as the assembly reads it — the same for every language of a lesson. */
    private const END_MARK = '/[.!?…]+$/u';

    /** The marks that open a sentence before its first letter (Spanish ¿ ¡) — read past for the first letter's case. */
    private const OPENING_MARKS = '/^[¿¡]+/u';

    private const GLUE_MAX_WORDS = 3;

    public static function hasSlot(string $frame): bool
    {
        return preg_match(self::SLOT_PATTERN, $frame) === 1;
    }

    /**
     * The frame with the filler in its slot; a frame without a slot, or no filler, is the frame itself.
     * A space the frame keeps between its slot and the closing mark («I work ___ .») is typography, not
     * a word: the sentence said with a filler has none («I work from home.»).
     *
     * A FILLER THAT ENDS WITH AN ABBREVIATION'S DOT CLOSES THE SENTENCE WITH IT (наряд FIX-4 §6): «I can come at ___.» said
     * with «3 p.m.» is «I can come at 3 p.m.», not «…3 p.m..» (the vet's day 1 had five cards so). One dot is left when
     * the language's rule of sentence ends (`$ends`, its pack's `abbreviations`) reads the filler's last dot as an
     * abbreviation's and the frame has nothing after its window but its full stop. Without `$ends` — a language whose
     * pack says nothing of its sentence ends — no dot is taken as an abbreviation's, and the frame is filled as written.
     */
    public static function fill(string $frame, ?string $filler, ?SentenceEnds $ends = null): string
    {
        $said = $filler === null || ! self::hasSlot($frame)
            ? $frame
            : (string) preg_replace(self::SLOT_PATTERN, addcslashes($filler, '\\$'), $frame, 1);
        if ($filler !== null && self::hasSlot($frame)) {
            $said = (string) preg_replace('/\s+([.,!?;:…])/u', '$1', $said);
            if ($ends !== null && self::closedByAbbreviation($frame, $filler, $ends)) {
                $said = mb_substr(rtrim($said), 0, -1);
            }
        }

        return trim($said);
    }

    /** Does the filler end with an abbreviation's dot where the frame has only its full stop after the window? */
    private static function closedByAbbreviation(string $frame, string $filler, SentenceEnds $ends): bool
    {
        $parts = preg_split(self::SLOT_PATTERN, $frame, 2);
        $filler = trim($filler);

        return is_array($parts) && count($parts) === 2 && trim($parts[1]) === '.'
            && str_ends_with($filler, '.') && $ends->closesText($filler) && ! $ends->carriesSentence($filler);
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

    /**
     * WHEN TWO PATTERNS, TWO WORDS OR TWO ROLES ARE THE SAME (наряд GEN-3): what the comparison of a line with its frame
     * forgives, and nothing more — the case of the letters, the run of spaces, the mark the text ends with; a slot is a
     * slot however many underscores draw it. «Is ___ still available?» and «is  ___ still available» are one frame.
     */
    public static function identity(string $text): string
    {
        $slotted = (string) preg_replace(self::SLOT_PATTERN, '___', self::withoutEndMark($text));

        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $slotted)));
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

    /**
     * `$text` with no space before the mark it ends with — «Всего ___ .» is «Всего ___.» (доработка GEN-3): a space there is
     * typography the model left, not a word, and a card would show it. Trimmed; nothing else of the text changes.
     */
    public static function withEndMarkClosed(string $text): string
    {
        return (string) preg_replace('/\s+([.!?…]+)$/u', '$1', trim($text));
    }

    /**
     * «I can come at 3 p.m..» → «I can come at 3 p.m.» (хвост ROADMAP, наряд CONV-1).
     *
     * The model writes the abbreviation with its own full stop and then closes the sentence with
     * another, and the line goes to the phone, to the voice and to the judge with two. Exactly TWO
     * are collapsed: three or more are an ellipsis written with dots, and «...» is not a slip to
     * put right — it is how somebody trails off.
     */
    public static function withoutDoubledStop(string $text): string
    {
        return (string) preg_replace('/(?<!\.)\.\.$/u', '.', rtrim($text));
    }

    /** `$text` with the closing mark of `$from` when it has none of its own — what a frame written without one borrows. */
    public static function withEndMarkOf(string $text, string $from): string
    {
        return self::endMark($text) === '' ? rtrim($text).self::endMark($from) : $text;
    }

    /**
     * THE ONE PLACE A SENTENCE IS PUT TOGETHER IN THE LEARNER'S LANGUAGE (наряд FIX-2, п. 1) — the native frame said
     * with a native filler, closed the way the phrase is closed, and STARTING WITH A CAPITAL.
     *
     * The capital is the whole reason this is a method and not three calls at the call site. A native frame whose
     * window stands first («___ нужен ветеринар.») is written lower-cased after the fill, because the filler is a
     * fragment and was written as one («моей кошке»); the sentence that came out went onto a card of the owner's
     * live day exactly like that (проход 20.09, п. 1). Wherever a native sentence is assembled it is assembled
     * here, so the next card cannot get it wrong on its own.
     */
    public static function nativeSentence(string $frameNative, string $fillerNative, string $endLike, ?SentenceEnds $ends = null): string
    {
        return self::capitalized(self::withEndMarkOf(self::fill($frameNative, $fillerNative, $ends), $endLike));
    }

    /**
     * `$text` with its first letter upper-cased — past the Spanish opening marks («¿la farmacia está abierta?» →
     * «¿La farmacia está abierta?»); a text that starts with something else is left alone.
     */
    public static function capitalized(string $text): string
    {
        $trimmed = ltrim($text);
        if ($trimmed === '') {
            return $text;
        }
        [$opening, $rest] = self::openingMarks($trimmed);

        return $rest === '' ? $trimmed : $opening.mb_strtoupper(mb_substr($rest, 0, 1)).mb_substr($rest, 1);
    }

    /**
     * Leading conversational glue of any learner line, by its shape: a short prefix up to the first
     * comma («Yes, », «Okay, thanks, »), an exclamation opened by ¡ too («¡Claro! »). What «10 words, not counting
     * leading glue» leaves out.
     */
    public static function leadingGlue(string $text): string
    {
        if (preg_match('/^([¡¿]?(?:[\p{L}\'’]+\s*){1,'.self::GLUE_MAX_WORDS.'}[,!—–]\s+)/u', trim($text), $m) !== 1) {
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

    /**
     * The same text but, perhaps, the case of its first letter — read past the Spanish opening marks, which must be the
     * same on both sides: «¿puedo pagar…» is «¿Puedo pagar…», «puedo pagar…» is not.
     */
    private static function equalButFirstLetterCase(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        [$openA, $a] = self::openingMarks($a);
        [$openB, $b] = self::openingMarks($b);
        if ($openA !== $openB || $a === '' || $b === '' || mb_substr($a, 1) !== mb_substr($b, 1)) {
            return false;
        }

        return mb_strtolower(mb_substr($a, 0, 1)) === mb_strtolower(mb_substr($b, 0, 1));
    }

    /**
     * The Spanish opening marks a text starts with, and the text after them.
     *
     * @return array{0: string, 1: string}
     */
    private static function openingMarks(string $text): array
    {
        $opening = preg_match(self::OPENING_MARKS, $text, $m) === 1 ? $m[0] : '';

        return [$opening, substr($text, strlen($opening))];
    }
}
