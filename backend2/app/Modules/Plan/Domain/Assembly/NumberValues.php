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
 *
 * AN OPTION OF «ПОЙМАЙ ЧИСЛО» IS A NUMBER OR AN AMOUNT, never a date on the calendar (наряд BACK-TAILS-1 §1.3, кадр
 * 34-7: «варианты — числа и количества, разбирать остальное не нужно»): a run says an amount when one of its words is
 * a numeral (`number_pattern`) or a unit something is counted in (`amount_pattern` — «неделю», «дня», «часов»), so
 * «через неделю» and «два дня» are values to catch and «сегодня», «завтра», «раньше» are not. The reading of the line
 * itself ({@see says()}, {@see runs()}) is untouched: a line is found by any number OR time it says, and the span
 * marked in it is the first such run — what the option asks about is a narrower thing than what the ear hears.
 */
final readonly class NumberValues
{
    /** What may stand between two words of one value: spaces only, or one separator inside a numeral. */
    private const GAP = '/^(?:\s*|[:.,\/])$/u';

    private function __construct(
        private ?string $numberPattern,
        private ?string $timePattern,
        private ?string $amountPattern,
    ) {}

    /**
     * The reader of a pack's numbers and times; null when the pack has neither pattern. A pack without
     * `amount_pattern` counts only a numeral as an amount — no unit words are borrowed from another language.
     */
    public static function of(LanguagePack $pack): ?self
    {
        $number = $pack->has('number_pattern') ? $pack->pattern('number_pattern') : null;
        $time = $pack->has('time_pattern') ? $pack->pattern('time_pattern') : null;
        $amount = $pack->has('amount_pattern') ? $pack->pattern('amount_pattern') : null;

        return $number === null && $time === null ? null : new self($number, $time, $amount);
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
     * as written, how many words it has, whether one of them is a number — a value with a number is of another
     * kind than a value of time words only («три дня» beside «вчера вечером») — and whether it says an AMOUNT: a
     * numeral or a unit things are counted in.
     *
     * @return list<array{start: int, end: int, text: string, words: int, number: bool, amount: bool}>
     */
    public function runs(string $text): array
    {
        // Each run as [start, end, words, has a number, says an amount] while it grows.
        $runs = [];
        $current = null;
        foreach (Words::spans($text) as [$start, $end, $word]) {
            $number = $this->isNumber($word);
            $amount = $number || $this->isAmount($word);
            if (! $number && ! $this->isTime($word)) {
                if ($current !== null) {
                    $runs[] = $current;
                    $current = null;
                }

                continue;
            }
            if ($current !== null && preg_match(self::GAP, mb_substr($text, $current[1], $start - $current[1])) === 1) {
                $current = [$current[0], $end, $current[2] + 1, $current[3] || $number, $current[4] || $amount];

                continue;
            }
            if ($current !== null) {
                $runs[] = $current;
            }
            $current = [$start, $end, 1, $number, $amount];
        }
        if ($current !== null) {
            $runs[] = $current;
        }

        $out = [];
        foreach ($runs as [$start, $end, $words, $number, $amount]) {
            $out[] = ['start' => $start, 'end' => $end, 'text' => mb_substr($text, $start, $end - $start), 'words' => $words, 'number' => $number, 'amount' => $amount];
        }

        return $out;
    }

    /**
     * The AMOUNT the text names — its longest run that says one, the first between runs of one length — as an option
     * shows it: the words as written, the first letter capital. Null when the text names none: a text whose only
     * values are dates and adverbs of time asks no «поймай число».
     *
     * @return array{text: string, number: bool}|null
     */
    public function value(string $text): ?array
    {
        $best = null;
        foreach ($this->amounts($text) as $run) {
            if ($best === null || $run['words'] > $best['words']) {
                $best = $run;
            }
        }

        return $best === null ? null : ['text' => self::capitalised($best['text']), 'number' => $best['number']];
    }

    /**
     * Every AMOUNT the text says, as options show them — the values of the day a wrong option may be.
     *
     * @return list<array{text: string, number: bool}>
     */
    public function values(string $text): array
    {
        $out = [];
        foreach ($this->amounts($text) as $run) {
            $out[] = ['text' => self::capitalised($run['text']), 'number' => $run['number']];
        }

        return $out;
    }

    /**
     * The runs that say an amount — a numeral or a counted unit.
     *
     * @return list<array{start: int, end: int, text: string, words: int, number: bool, amount: bool}>
     */
    private function amounts(string $text): array
    {
        return array_values(array_filter($this->runs($text), static fn (array $run): bool => $run['amount']));
    }

    private function isNumber(string $word): bool
    {
        return $this->numberPattern !== null && preg_match($this->numberPattern, LanguagePack::normal($word)) === 1;
    }

    private function isTime(string $word): bool
    {
        return $this->timePattern !== null && preg_match($this->timePattern, LanguagePack::normal($word)) === 1;
    }

    private function isAmount(string $word): bool
    {
        return $this->amountPattern !== null && preg_match($this->amountPattern, LanguagePack::normal($word)) === 1;
    }

    private static function capitalised(string $text): string
    {
        $text = trim($text);

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }
}
