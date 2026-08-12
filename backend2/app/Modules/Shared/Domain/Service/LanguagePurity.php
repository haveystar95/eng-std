<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * Script-level language purity: is a field written in the language it claims to be?
 *
 * This catches HALF the problem, and it is important to know which half. A character check finds
 * Cyrillic that leaked into an English sentence, and it finds Ukrainian letters that only exist in
 * Ukrainian (і ї є ґ). It CANNOT find Ukrainian words spelled entirely in letters Russian also has
 * — «здаватися», «треба», «потрібно» — which is precisely the class the original one-off scan was
 * chasing. Those need a reader who knows both languages, which is why the enrichment model is asked
 * for lexis notes as well and both streams end up in the same findings table.
 *
 * It also cannot see a translation that is honestly labelled as another language — a `uk` row next
 * to a `ru` one is not impure, it is simply the wrong row to show. That is a query's job, not this
 * class's (see docs/ua-audit.md, class A).
 *
 * Lives in Shared because it has two unrelated consumers and no business asking either module for
 * permission: the enrichment станок, which REPORTS what it finds after the fact, and the generation
 * pipeline, which REFUSES to write what it finds. One detector, two verdicts — if they ever
 * disagreed about what "Ukrainian" means, the barrier and the audit would be describing different
 * databases.
 *
 * Pure and cheap on purpose: it runs over every field of every term on every run.
 */
final class LanguagePurity
{
    /** Letters that exist in Ukrainian and not in Russian. Their presence in a RU field is decisive. */
    private const UA_ONLY_LETTERS = ['і', 'ї', 'є', 'ґ', 'І', 'Ї', 'Є', 'Ґ'];

    /**
     * Letters that betray a foreign language in a field DECLARED to be `$lang`. Empty = clean.
     *
     * Only two languages have a rule here, and that is deliberate rather than unfinished: we know
     * what a Russian field must not contain (its close relative's letters) and what an English one
     * must not (anything non-Latin). For any other language an honest "no opinion" beats a guess —
     * a caller that treats silence as a pass keeps writing, which is the correct default for a
     * check that exists to catch one specific, known failure.
     *
     * @param  string  $lang  ISO code the value claims to be written in
     * @return list<string>  the offending characters, deduped, in order
     */
    public function foreignLetters(string $lang, string $value): array
    {
        return match (strtolower(trim($lang))) {
            'ru' => $this->ukrainianLetters($value),
            'en' => $this->nonEnglishLetters($value),
            default => [],
        };
    }

    /** @see foreignLetters() — the boolean form, for callers that only decide pass/fail. */
    public function isClean(string $lang, string $value): bool
    {
        return $this->foreignLetters($lang, $value) === [];
    }

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
