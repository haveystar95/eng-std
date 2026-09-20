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
 * Two questions are asked of it: does a text end with an end mark ({@see terminal()}), and how many sentences a text has
 * ({@see count()}). The live day of the order («Визит к ветеринару», day 1) failed on both being asked with a bare regex:
 * the filler «3 p.m.» was «a filler with its own full stop» (fatal), and «We have 3 p.m. and 5:30 p.m. today.» was three
 * sentences.
 *
 * A run of marks ends a sentence only where a sentence can end — before a space, a closing quote or bracket, or the end
 * of the text: «3.5» and «5:30» have no end in them. Of a run, only the marks that are no abbreviation's count: «See you
 * at 3 p.m..» ends with the second dot. The text ending with an abbreviation's dot («Come at 3 p.m.») ends, by this rule,
 * with no mark: the rule is one, and a filler is read by it exactly as a frame is.
 *
 * Heuristic on purpose, like every rule of the packs: a word of the list before a dot is read as the abbreviation
 * whatever it means there — which is why a list keeps out a word that is also an ordinary one («No.»).
 */
final readonly class SentenceEnds
{
    /** @var array<string, string> mark → what it says (`statement`, `question`, …) */
    private array $marks;

    private string $markClass;

    /** The abbreviations of the pack as one pattern, or null when it lists none. */
    private ?string $abbreviations;

    public function __construct(LanguagePack $pack)
    {
        $marks = [];
        foreach ($pack->map('sentence_ends') as $mark => $kind) {
            $marks[(string) $mark] = is_string($kind) ? $kind : '';
        }
        $this->marks = $marks;
        $this->markClass = implode('', array_map(static fn (string $m): string => preg_quote($m, '/'), array_keys($marks)));

        $listed = $pack->has('abbreviations') ? $pack->words('abbreviations') : [];
        $this->abbreviations = $listed === [] ? null : '/(?<![^\s("«\'“\[])(?:'
            .implode('|', array_map(static fn (string $a): string => str_replace(' ', '\s+', preg_quote($a, '/')), $listed))
            .')(?![^\s»"\'”’)\]'.$this->markClass.',;:])/iu';
    }

    /** The mark a text ends with — closing quotes and brackets aside — or '' when it ends with none. */
    public function terminal(string $text): string
    {
        $ends = $this->ends($text);
        if ($ends === []) {
            return '';
        }
        [$offset, $marks, $length] = $ends[count($ends) - 1];

        return preg_match('/^[\s»"\'”’)\]]*$/u', substr($text, $offset + $length)) === 1 ? mb_substr($marks, -1) : '';
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
     * Where the sentences of a text end: for every run of end marks that stands where a sentence can end and is not
     * wholly an abbreviation's — [byte offset of its first mark that counts, those marks, the bytes to the run's end].
     *
     * @return list<array{0: int, 1: string, 2: int}>
     */
    private function ends(string $text): array
    {
        $masked = $this->abbreviationMarks($text);
        preg_match_all('/['.$this->markClass.']+(?=[\s»"\'”’)\]]|$)/u', $text, $runs, PREG_OFFSET_CAPTURE);

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
                $out[] = [$first, $kept, $offset + strlen($run) - $first];
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
