<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * ARE THESE TWO STRINGS THE SAME WORD, WRITTEN IN TWO ALPHABETS?
 *
 * «Ivanov» and «Иванов» are one word and one piece of information. A card that shows the first and
 * offers the second as its TRANSLATION asks the learner nothing: they read the Latin, they say the
 * Cyrillic, and they have learned that a name is spelled the way it sounds. The owner's phone
 * showed exactly that card — term «Ivanov», reading «[иванов]», translation «Иванов», example «My
 * last name is Ivanov, yes.» — and every gate passed it, because character by character the two
 * strings are different.
 *
 * ## Deliberately conservative
 *
 * One mapping per Cyrillic letter and no cleverness: the cost of a false positive here is a REFUSED
 * DAY, which is money and an error on the learner's screen, while the cost of a miss is one weak
 * card that reading catches. So «Иванов» → `ivanov` matches `Ivanov`, and anything needing a guess
 * about which of two spellings a translator preferred is simply not matched.
 *
 * The table is the common Russian romanisation, with the digraphs spelled the way they are usually
 * spelled in a passport. Ukrainian's own four letters ride along because the pool legitimately
 * carries them.
 */
final class TransliteratedSameness
{
    /**
     * Cyrillic → the Latin skeleton it is usually written as.
     *
     * `ъ` and `ь` map to nothing: they are not sounds and no romanisation writes them, so a word
     * carrying one must still match the Latin spelling that does not.
     */
    private const TABLE = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        // Ukrainian's own, so a `uk` pair is compared rather than silently skipped.
        'і' => 'i', 'ї' => 'yi', 'є' => 'ye', 'ґ' => 'g',
    ];

    /**
     * The variant spellings that are the SAME romanisation choice made differently.
     *
     * Applied to both sides after the table, so «Ivanoff» is not dragged in but «Ivanov» / «Иванов»
     * and «Alexey» / «Алексей» land on one string. Ordered longest-first: `shch` must be folded
     * before `sh`.
     *
     * @var array<string, string>
     */
    private const VARIANTS = [
        'shch' => 'sch',
        'kh' => 'h',
        'yo' => 'e',
        'ye' => 'e',
        'iy' => 'y',
        'ij' => 'y',
        'j' => 'y',
        'x' => 'ks',
        'w' => 'v',
    ];

    /** Is `$a` the same word as `$b`, allowing for the two alphabets? */
    public function same(string $a, string $b): bool
    {
        $left = $this->skeleton($a);

        return $left !== '' && $left === $this->skeleton($b);
    }

    /**
     * The word as a run of Latin letters with no spelling opinion left in it.
     *
     * Public because the gate that uses this also wants to SAY what it compared, and re-deriving
     * the skeleton at the call site would be a second implementation of the same table.
     */
    public function skeleton(string $value): string
    {
        $lower = mb_strtolower(trim($value));
        $out = '';
        $length = mb_strlen($lower);

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($lower, $i, 1);
            $out .= self::TABLE[$char] ?? $char;
        }

        $out = strtr($out, self::VARIANTS);

        // Everything that is not a Latin letter goes: a hyphen, an apostrophe and a space are
        // spelling, and two spellings of one name differ in exactly those.
        return (string) preg_replace('/[^a-z]+/', '', $out);
    }
}
