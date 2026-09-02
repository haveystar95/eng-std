<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * DOES THE LINE ACTUALLY SAY THE NUMBER THE CARD IS GRADED ON?
 *
 * A `numbers` card is heard, not read (канон §6): the line is spoken — «It's twenty-two euros» —
 * and the learner answers with DIGITS, «22». So the card carries two halves that have to be the
 * same number, one spelled and one typed, and nothing else in the machine can notice when they are
 * not: the digits never appear on screen and the words are never graded.
 *
 * ## A FLOOR, not a parser
 *
 * The check passes when ANY part of `value` can be found in the line — as digits, or as the words
 * this language spells that number with. It is deliberately the same shape as
 * {@see TranslationKeyPresence}: the only unarguable defect is that NOTHING of the value is there
 * («It's about lunchtime» / value «22»), and a gate that tried to prove the whole number would
 * refuse «twenty-two fifty» against «22.50» and cost a paid repair for being clever.
 *
 * A language whose words are not written here is not judged at all — «немецкое правило ещё не
 * написано» must not read as «у каждого немецкого числа сломано значение».
 */
final class NumberSpelling
{
    /**
     * How this language says the numbers a scene actually holds: a price, a time, a house number,
     * a day of the month.
     *
     * Units, teens and tens, plus the two scale words and the ordinals a date needs. Everything
     * else is composed of these on the page («twenty-two», «half past nine»), and a substring
     * search over the composed line finds the pieces.
     *
     * @var array<string, array<int, list<string>>>
     */
    public const DEFAULT_WORDS = [
        'en' => [
            0 => ['zero', 'oh'],
            1 => ['one', 'first'],
            2 => ['two', 'second'],
            3 => ['three', 'third'],
            4 => ['four', 'fourth'],
            5 => ['five', 'fifth'],
            6 => ['six', 'sixth'],
            7 => ['seven', 'seventh'],
            8 => ['eight', 'eighth'],
            9 => ['nine', 'ninth'],
            10 => ['ten', 'tenth'],
            11 => ['eleven', 'eleventh'],
            12 => ['twelve', 'twelfth'],
            13 => ['thirteen', 'thirteenth'],
            14 => ['fourteen', 'fourteenth'],
            15 => ['fifteen', 'fifteenth'],
            16 => ['sixteen', 'sixteenth'],
            17 => ['seventeen', 'seventeenth'],
            18 => ['eighteen', 'eighteenth'],
            19 => ['nineteen', 'nineteenth'],
            20 => ['twenty', 'twentieth'],
            30 => ['thirty', 'thirtieth'],
            40 => ['forty', 'fortieth'],
            50 => ['fifty', 'fiftieth'],
            60 => ['sixty', 'sixtieth'],
            70 => ['seventy', 'seventieth'],
            80 => ['eighty', 'eightieth'],
            90 => ['ninety', 'ninetieth'],
            100 => ['hundred'],
            1000 => ['thousand'],
        ],
    ];

    /**
     * The months an ISO date names, so «2026-09-05» is found in «on the fifth of September».
     *
     * @var array<string, array<int, string>>
     */
    private const MONTHS = [
        'en' => [
            1 => 'january', 2 => 'february', 3 => 'march', 4 => 'april', 5 => 'may', 6 => 'june',
            7 => 'july', 8 => 'august', 9 => 'september', 10 => 'october', 11 => 'november',
            12 => 'december',
        ],
    ];

    /** @param array<string, array<int, list<string>>> $words target language => number => spellings */
    public function __construct(private readonly array $words = self::DEFAULT_WORDS) {}

    /** Is this language's spelling table written? A language without one is not judged. */
    public function judges(string $targetLang): bool
    {
        return $this->wordsFor($targetLang) !== [];
    }

    /**
     * Can `$value` be heard in `$line`?
     *
     * True — including vacuously true — whenever the check cannot be made: no table for the
     * language, no digits in the value, nothing to look in. A gate that cannot see must not refuse.
     */
    public function heardIn(string $targetLang, string $line, string $value): bool
    {
        $table = $this->wordsFor($targetLang);
        if ($table === [] || trim($value) === '' || trim($line) === '') {
            return true;
        }

        $haystack = ' ' . self::normalize($line) . ' ';

        // The literal form first: «22» in «It's 22 euros», and an ISO date written out.
        if (str_contains($haystack, ' ' . self::normalize($value) . ' ')) {
            return true;
        }

        foreach ($this->tokensOf($targetLang, $value) as $token) {
            if (str_contains($haystack, ' ' . $token . ' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every string the line could legitimately say this value with.
     *
     * A plain number gives its digits and its words; a compound one («22») also gives its parts,
     * because «twenty-two» is written as two tokens once the hyphen is normalised away. An ISO date
     * gives the month's name and the day, which is what a spoken date actually contains.
     *
     * @return list<string>
     */
    private function tokensOf(string $targetLang, string $value): array
    {
        $out = [];
        foreach ($this->numbersIn($value) as $number) {
            $out[] = (string) $number;
            foreach ($this->spellingsOf($targetLang, $number) as $spelling) {
                $out[] = self::normalize($spelling);
            }
        }

        $month = $this->monthOf($targetLang, $value);
        if ($month !== null) {
            $out[] = $month;
        }

        return array_values(array_unique(array_filter($out, static fn (string $t): bool => $t !== '')));
    }

    /**
     * The numbers a value holds — the whole of it, and its parts when it is a compound.
     *
     * «22» yields 22, 20 and 2, so a line that says «twenty-two» is found by its halves. A year is
     * left whole: «2026» is said «twenty twenty-six» as often as «two thousand and twenty-six»,
     * and both are two-digit pieces of it.
     *
     * @return list<int>
     */
    private function numbersIn(string $value): array
    {
        if (preg_match_all('/\d+/u', $value, $matches) === false || $matches[0] === []) {
            return [];
        }

        $out = [];
        foreach ($matches[0] as $digits) {
            $number = (int) $digits;
            $out[] = $number;
            if ($number > 20 && $number < 100) {
                $out[] = intdiv($number, 10) * 10;
                if ($number % 10 !== 0) {
                    $out[] = $number % 10;
                }
            }
            if ($number >= 100 && $number < 10000) {
                $out[] = intdiv($number, 100);
                $out[] = $number % 100;
            }
        }

        return array_values(array_unique(array_filter($out, static fn (int $n): bool => $n >= 0)));
    }

    /** @return list<string> */
    private function spellingsOf(string $targetLang, int $number): array
    {
        return $this->wordsFor($targetLang)[$number] ?? [];
    }

    /** The month an ISO date names, in this language, or null when the value is not one. */
    private function monthOf(string $targetLang, string $value): ?string
    {
        if (preg_match('/^\s*(\d{4})-(\d{2})-(\d{2})\s*$/u', $value, $m) !== 1) {
            return null;
        }

        $months = self::MONTHS[$this->key($targetLang)] ?? [];

        return $months[(int) $m[2]] ?? null;
    }

    /** @return array<int, list<string>> */
    private function wordsFor(string $targetLang): array
    {
        return $this->words[$this->key($targetLang)] ?? [];
    }

    private function key(string $lang): string
    {
        $lower = mb_strtolower(trim($lang));

        return isset($this->words[$lower]) ? $lower : mb_substr($lower, 0, 2);
    }

    /** Case-folded, punctuation-free, whitespace-collapsed — hyphens become spaces. */
    private static function normalize(string $value): string
    {
        $lower = mb_strtolower(trim($value));
        $stripped = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $lower) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $stripped));
    }
}
