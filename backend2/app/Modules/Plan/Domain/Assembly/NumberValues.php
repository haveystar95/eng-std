<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Service\Words;

/**
 * THE NUMBERS AND TIMES A LINE SAYS, READ OFF ONE LANGUAGE'S PACK (наряд SESSION-1a, `listen_number`, кадр 34-7): the
 * words that match the pack's `number_pattern` or `time_pattern`, and the values they make together — «three days
 * ago», «через неделю».
 *
 * A pattern is about one word ({@see Words::spans()}), so a value is a RUN of such words standing next to each other:
 * nothing but spaces between two of them, or one separator with no space around it — «10:30» is one time, not two
 * numbers, while «сегодня или завтра» and «два, три» are two values. A pack with neither pattern reads no values at
 * all — {@see of()} gives null, and the card that needs them is not dealt: another language's numbers are never
 * borrowed.
 */
final readonly class NumberValues
{
    /** What may stand between two words of one value: spaces only, or one separator inside a numeral. */
    private const GAP = '/^(?:\s*|[:.,\/])$/u';

    private function __construct(
        private ?string $numberPattern,
        private ?string $timePattern,
    ) {}

    /** The reader of a pack's numbers and times; null when the pack has neither pattern. */
    public static function of(LanguagePack $pack): ?self
    {
        $number = $pack->has('number_pattern') ? $pack->pattern('number_pattern') : null;
        $time = $pack->has('time_pattern') ? $pack->pattern('time_pattern') : null;

        return $number === null && $time === null ? null : new self($number, $time);
    }

    /** Does the text say a number or a time anywhere? */
    public function says(string $text): bool
    {
        foreach (Words::spans($text) as [, , $word]) {
            if ($this->isNumber($word) || $this->isTime($word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every value the text says, in the order of the text: where it stands in characters (`end` exclusive), its words
     * as written, how many words it has, and whether one of them is a number — a value with a number is of another
     * kind than a value of time words only («три дня» beside «вчера вечером»).
     *
     * @return list<array{start: int, end: int, text: string, words: int, number: bool}>
     */
    public function runs(string $text): array
    {
        // Each run as [start, end, words, has a number] while it grows.
        $runs = [];
        $current = null;
        foreach (Words::spans($text) as [$start, $end, $word]) {
            $number = $this->isNumber($word);
            if (! $number && ! $this->isTime($word)) {
                if ($current !== null) {
                    $runs[] = $current;
                    $current = null;
                }

                continue;
            }
            if ($current !== null && preg_match(self::GAP, mb_substr($text, $current[1], $start - $current[1])) === 1) {
                $current = [$current[0], $end, $current[2] + 1, $current[3] || $number];

                continue;
            }
            if ($current !== null) {
                $runs[] = $current;
            }
            $current = [$start, $end, 1, $number];
        }
        if ($current !== null) {
            $runs[] = $current;
        }

        $out = [];
        foreach ($runs as [$start, $end, $words, $number]) {
            $out[] = ['start' => $start, 'end' => $end, 'text' => mb_substr($text, $start, $end - $start), 'words' => $words, 'number' => $number];
        }

        return $out;
    }

    /**
     * The value the text names — its longest run, the first between runs of one length — as an option shows it: the
     * words as written, the first letter capital. Null when the text says none.
     *
     * @return array{text: string, number: bool}|null
     */
    public function value(string $text): ?array
    {
        $best = null;
        foreach ($this->runs($text) as $run) {
            if ($best === null || $run['words'] > $best['words']) {
                $best = $run;
            }
        }

        return $best === null ? null : ['text' => self::capitalised($best['text']), 'number' => $best['number']];
    }

    /**
     * Every value the text says, as options show them.
     *
     * @return list<array{text: string, number: bool}>
     */
    public function values(string $text): array
    {
        $out = [];
        foreach ($this->runs($text) as $run) {
            $out[] = ['text' => self::capitalised($run['text']), 'number' => $run['number']];
        }

        return $out;
    }

    private function isNumber(string $word): bool
    {
        return $this->numberPattern !== null && preg_match($this->numberPattern, LanguagePack::normal($word)) === 1;
    }

    private function isTime(string $word): bool
    {
        return $this->timePattern !== null && preg_match($this->timePattern, LanguagePack::normal($word)) === 1;
    }

    private static function capitalised(string $text): string
    {
        $text = trim($text);

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }
}
