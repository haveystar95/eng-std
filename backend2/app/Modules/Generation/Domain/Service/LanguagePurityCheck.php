<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * Script-level language purity: is a field written in the language it claims to be?
 *
 * This catches HALF the problem, and it is important to know which half. A character check finds
 * Cyrillic that leaked into an English sentence, and it finds Ukrainian letters that only exist in
 * Ukrainian (і ї є ґ). It CANNOT find Ukrainian words spelled entirely in letters Russian also has
 * — «здаватися», «треба», «потрібно» — which is precisely the class the original one-off scan was
 * chasing. Those need a reader who knows both languages, which is why the model is asked for lexis
 * notes as well and both streams end up in the same findings table.
 *
 * Pure and cheap on purpose: it runs over every field of every term on every run.
 */
final class LanguagePurityCheck
{
    /** Letters that exist in Ukrainian and not in Russian. Their presence in a RU field is decisive. */
    private const UA_ONLY_LETTERS = ['і', 'ї', 'є', 'ґ', 'І', 'Ї', 'Є', 'Ґ'];

    /**
     * Non-Latin letters in a field that is supposed to be English. Returns the offending
     * characters (deduped, in order) or an empty list.
     *
     * @return list<string>
     */
    public function nonEnglishLetters(string $value): array
    {
        return $this->matchAll('/[\p{L}]/u', $value, static fn (string $ch): bool => preg_match('/[\p{Latin}]/u', $ch) !== 1);
    }

    /**
     * Ukrainian-only letters in a field that is supposed to be Russian.
     *
     * @return list<string>
     */
    public function ukrainianLetters(string $value): array
    {
        return $this->matchAll('/[\p{L}]/u', $value, static fn (string $ch): bool => in_array($ch, self::UA_ONLY_LETTERS, true));
    }

    /**
     * @param  callable(string): bool  $offends
     * @return list<string>
     */
    private function matchAll(string $pattern, string $value, callable $offends): array
    {
        if (preg_match_all($pattern, $value, $m) === false) {
            return [];
        }

        $found = [];
        foreach ($m[0] as $char) {
            if ($offends($char) && ! in_array($char, $found, true)) {
                $found[] = $char;
            }
        }

        return $found;
    }
}
