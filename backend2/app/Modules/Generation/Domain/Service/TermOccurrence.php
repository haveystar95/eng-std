<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

/**
 * Does an example sentence actually contain the term it is supposed to teach?
 *
 * One question with two consumers that must never disagree: the server refuses a regenerated
 * example that does not contain its term, and the client's intro card bolds the term inside the
 * example it was given (`termSearchForm` + `spanPositionIn` in
 * mobile/lib/features/training/session/session_exercise.dart). If the two used different rules,
 * the server would accept a sentence the card cannot mark up — which is precisely how «How much
 * does this bag cost?» ended up taught by «How much does that coat cost?» and shown flat.
 *
 * The only normalisation is the term's TRAILING punctuation. A sentence-like term carries its own
 * final mark, and the example embeds it in a longer sentence that supplies its own — «I have a
 * fever.» inside «I have a fever and feel very weak.» Nothing else is normalised: the term must
 * appear as it is written, because that is what the learner is being taught to produce.
 */
final class TermOccurrence
{
    /** The trailing marks a term may shed when it is embedded in a bigger sentence. */
    private const TRAILING = '/[.?!,…]+$/u';

    /** Does [$example] contain [$term], case-insensitively, allowing for the term's own tail mark? */
    public static function inExample(string $example, string $term): bool
    {
        $needle = self::searchForm($term);

        return $needle !== '' && mb_stripos($example, $needle) !== false;
    }

    /** The term as it is looked for inside its example: trailing sentence punctuation dropped. */
    public static function searchForm(string $term): string
    {
        $trimmed = trim($term);
        $stripped = rtrim((string) preg_replace(self::TRAILING, '', $trimmed));

        // A term that is nothing but punctuation would normalise to the empty string, which every
        // sentence "contains" — keep it as it is rather than turning the check into a no-op.
        return $stripped !== '' ? $stripped : $trimmed;
    }
}
