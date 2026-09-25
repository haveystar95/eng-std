<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Language;

use App\Modules\Plan\Domain\Service\Words;

/**
 * WHERE A SENTENCE ENDS, IN ONE LANGUAGE (наряд CHECK-1, `docs/plan-v2.md` §4) — the one rule every check that cuts or
 * counts by the end marks reads. A mark of the pack's `sentence_ends` ends a sentence; a full stop does not when it is
 * the dot of an abbreviation the pack lists (`abbreviations`: «3 p.m.», «Dr. Smith», «e.g.»); a question mark and an
 * exclamation mark always do. A pack without the key lists nothing, and every dot ends a sentence.
 *
 * Two questions are asked of it (решение архитектора по сдаче CHECK-1):
 *
 * 1. Is a TEXT closed — does it end with an end mark ({@see closesText()}, {@see terminal()} for which)? An
 *    abbreviation's dot at the very end of the text closes it («Come at 3 p.m.» is a closed sentence, and a frame
 *    ending so has its mark); inside the text it ends no sentence («We have 3 p.m. and 5:30 p.m. today.» is one
 *    sentence, {@see count()}).
 * 2. Does a FRAGMENT carry a sentence of its own ({@see carriesSentence()})? An abbreviation's dot does not («3 p.m.»,
 *    «Dr. Smith», «e.g.» are values for a slot); «See you tomorrow.» and «Yes?» do.
 *
 * The live day of the order («Визит к ветеринару», day 1) failed on both being asked with a bare regex: the filler
 * «3 p.m.» was «a filler with its own full stop» (fatal), and «We have 3 p.m. and 5:30 p.m. today.» was three sentences.
 *
 * A run of marks ends a sentence only where a sentence can end — before a space, a closing quote or bracket, or the end
 * of the text: «3.5» and «5:30» have no end in them. Of a run, only the marks that are no abbreviation's count: «See you
 * at 3 p.m..» carries a sentence by its second dot.
 *
 * Heuristic on purpose, like every rule of the packs: a word of the list before a dot is read as the abbreviation
 * whatever it means there — which is why a list keeps out a word that is also an ordinary one («No.»).
 *
 * THE QUOTES OF SEVEN LANGUAGES (наряд LANG-1 §1). What may stand between an end and the space after it is every
 * closing quote the taught and spoken languages write ({@see self::CLOSERS}): the English ” ’, the German „…“ and ‚…‘
 * (so «„Ich komme.“» ends with «.»), the Polish and Romanian „…”, the French « … » with its spaces inside — a no-break
 * one too, `\s` reads it — and the German reversed »…«. What may stand before an abbreviation is every opening one,
 * and the Spanish ¿ ¡ ({@see self::OPENERS}: «¿Sr. García?» ends once). ¿ and ¡ OPEN a sentence and never end one: a
 * pack that lists them among its `sentence_ends` has them left out, or «¿Puedo pagar?» would ask twice.
 */
final readonly class SentenceEnds
{
    /** What may stand after an end mark and before the space — or the end — that follows it: spaces, closing quotes, brackets. */
    private const CLOSERS = '\s»«"\'“”‘’›‹)\]';

    /** What may stand before an abbreviation: a space, an opening quote or bracket, a Spanish opening mark. */
    private const OPENERS = '\s("«»\'“„‚‹›\[¿¡';

    /** The marks that open a sentence — never an end, whatever a pack lists. */
    private const OPENING_MARKS = ['¿', '¡'];

    /** @var array<string, string> mark → what it says (`statement`, `question`, …) */
    private array $marks;

    private string $markClass;

    /** The abbreviations of the pack as one pattern, or null when it lists none. */
    private ?string $abbreviations;

    public function __construct(LanguagePack $pack)
    {
        $marks = [];
        foreach ($pack->map('sentence_ends') as $mark => $kind) {
            if (! in_array((string) $mark, self::OPENING_MARKS, true)) {
                $marks[(string) $mark] = is_string($kind) ? $kind : '';
            }
        }
        $this->marks = $marks;
        $this->markClass = implode('', array_map(static fn (string $m): string => preg_quote($m, '/'), array_keys($marks)));

        $listed = $pack->has('abbreviations') ? $pack->words('abbreviations') : [];
        $this->abbreviations = $listed === [] ? null : '/(?<![^'.self::OPENERS.'])(?:'
            .implode('|', array_map(static fn (string $a): string => str_replace(' ', '\s+', preg_quote($a, '/')), $listed))
            .')(?![^'.self::CLOSERS.$this->markClass.',;:])/iu';
    }

    /**
     * The mark a text ends with — closing quotes and brackets aside — or '' when it ends with none. An abbreviation's
     * dot at the very end closes the text: «Come at 3 p.m.» ends with «.».
     */
    public function terminal(string $text): string
    {
        $last = $this->last($text);

        return $last === null ? '' : mb_substr($last[1], -1);
    }

    /** Is the text closed — does it end with an end mark? */
    public function closesText(string $text): bool
    {
        return $this->terminal($text) !== '';
    }

    /**
     * Does a fragment carry a sentence of its own — end with a mark that is no abbreviation's? «See you tomorrow.» and
     * «Yes?» do; «3 p.m.», «Dr. Smith» and «e.g.» do not; «at 3 p.m..» does, by its second dot.
     */
    public function carriesSentence(string $fragment): bool
    {
        $last = $this->last($fragment);

        return $last !== null && ! $last[3];
    }

    /** What the mark a text ends with says — `question`, `statement`… — or '' when it ends with none. */
    public function terminalKind(string $text): string
    {
        $mark = $this->terminal($text);

        return $mark === '' ? '' : $this->marks[$mark];
    }

    /** How many sentences a text has — the pieces between its ends that have a word in them; a text without an end is one. */
    public function count(string $text): int
    {
        return count($this->sentences($text));
    }

    /**
     * The sentences of a text, each trimmed, without its end marks; pieces without a word are left out.
     *
     * @return list<string>
     */
    public function sentences(string $text): array
    {
        $out = [];
        $from = 0;
        foreach ($this->ends($text) as [$offset, , $length]) {
            $out[] = substr($text, $from, $offset - $from);
            $from = $offset + $length;
        }
        $out[] = substr($text, $from);

        return array_values(array_filter(array_map(trim(...), $out), static fn (string $p): bool => Words::count($p) > 0));
    }

    /** How many question marks a text has, anywhere in it. */
    public function questionMarks(string $text): int
    {
        $count = 0;
        foreach ($this->marks as $mark => $kind) {
            if ($kind === 'question') {
                $count += mb_substr_count($text, $mark);
            }
        }

        return $count;
    }

    /**
     * The end the text closes with — the last end of {@see ends()} when nothing but spaces, closing quotes and brackets
     * follow it — or null.
     *
     * @return array{0: int, 1: string, 2: int, 3: bool}|null
     */
    private function last(string $text): ?array
    {
        $ends = $this->ends($text);
        if ($ends === []) {
            return null;
        }
        $last = $ends[count($ends) - 1];

        return preg_match('/^['.self::CLOSERS.']*$/u', substr($text, $last[0] + $last[2])) === 1 ? $last : null;
    }

    /**
     * Where the sentences of a text end: for every run of end marks that stands where a sentence can end — [byte offset
     * of its first mark that counts, those marks, the bytes to the run's end, whether the run is wholly an
     * abbreviation's]. A run that is wholly an abbreviation's is an end only at the very end of the text — «Come at
     * 3 p.m.» is closed; inside the text it is left out, and «We have 3 p.m. and 5:30 p.m. today.» has one end.
     *
     * @return list<array{0: int, 1: string, 2: int, 3: bool}>
     */
    private function ends(string $text): array
    {
        // A pack whose `sentence_ends` lists nothing (or only the opening ¿ ¡) knows no end: every text is one sentence
        // with no mark — an empty class would be no pattern at all.
        if ($this->markClass === '') {
            return [];
        }
        $masked = $this->abbreviationMarks($text);
        preg_match_all('/['.$this->markClass.']+(?=['.self::CLOSERS.']|$)/u', $text, $runs, PREG_OFFSET_CAPTURE);

        $out = [];
        foreach ($runs[0] as [$run, $offset]) {
            $kept = '';
            $first = null;
            $at = $offset;
            foreach (preg_split('//u', $run, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $mark) {
                if (! isset($masked[$at])) {
                    $kept .= $mark;
                    $first ??= $at;
                }
                $at += strlen($mark);
            }
            if ($first !== null) {
                $out[] = [$first, $kept, $offset + strlen($run) - $first, false];
            } elseif (preg_match('/^['.self::CLOSERS.']*$/u', substr($text, $offset + strlen($run))) === 1) {
                $out[] = [$offset, $run, strlen($run), true];
            }
        }

        return $out;
    }

    /**
     * The byte offsets of the marks that belong to an abbreviation of the pack — a listed word standing on its own
     * (after a space, an opening quote or bracket, or at the start; before a space, a closing one, a mark or the end).
     *
     * @return array<int, true>
     */
    private function abbreviationMarks(string $text): array
    {
        if ($this->abbreviations === null) {
            return [];
        }
        preg_match_all($this->abbreviations, $text, $hits, PREG_OFFSET_CAPTURE);

        $out = [];
        foreach ($hits[0] as [$hit, $offset]) {
            $at = $offset;
            foreach (preg_split('//u', $hit, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
                if (isset($this->marks[$char])) {
                    $out[$at] = true;
                }
                $at += strlen($char);
            }
        }

        return $out;
    }
}
