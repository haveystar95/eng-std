<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * WHICH SIDE OF A PAIR A LANGUAGE MAY STAND ON — the one place that answers it.
 *
 * A language plays one of two roles, and they are not the same question:
 *
 *  - **изучаемый / taught** — the language a term is written in, the one being learned. Teaching a
 *    language is a CAPABILITY: strictness rules, normalisation, a grader, trainers. So the list is
 *    derived from {@see LanguageModeSupport} — a language that can carry at least one trainer — and
 *    not written out a second time here. zh and ja carry none in v1 (пп. 84, 136), so they fall out
 *    of this list on their own rather than by an exception someone has to remember.
 *  - **язык поддержки / support** — the language the learner READS: the translation beside the term.
 *    That takes no grader and no trainer, only a name, so it is EVERY language the catalogue knows
 *    ({@see LanguageCatalog}). The audience is not restricted (DECISIONS п. 85): a Turkish speaker
 *    learning German is a pair this deployment serves, and what stands behind it in v1 is the live
 *    lookup, which translates into the pair it was asked in (п. 144).
 *
 * BOTH LISTS ARE DERIVED, neither is typed out. That is the whole point of this class: the roster
 * used to live in `config/languages.php` as `APP_TARGET_LANG` plus a comma-separated
 * `APP_NATIVE_LANGS` — an env var that had drifted to «ru,ro» by accident and quietly decided which
 * pairs the search would refuse. Adding a language is now a row in the catalogue plus a capability,
 * never an environment variable (DECISIONS п. 145).
 *
 * The order is the CATALOGUE's, because that is the order the pickers list languages in and these
 * lists are what the pickers are built from.
 */
final class LanguageRoles
{
    /**
     * The languages this product TEACHES — what may stand on the term side of a pair.
     *
     * @return list<string>
     */
    public static function taught(): array
    {
        return array_values(array_filter(
            LanguageCatalog::codes(),
            static fn (string $code): bool => LanguageModeSupport::modesFor($code) !== [],
        ));
    }

    /**
     * The languages a learner may READ — what may stand on the support side of a pair.
     *
     * @return list<string>
     */
    public static function support(): array
    {
        return LanguageCatalog::codes();
    }

    public static function isTaught(string $code): bool
    {
        return in_array(self::normalize($code), self::taught(), true);
    }

    public static function isSupport(string $code): bool
    {
        return LanguageCatalog::knows(self::normalize($code));
    }

    /** Casefolded and trimmed, so `« RU »` and `ru` are one language and not two. */
    public static function normalize(string $code): string
    {
        return strtolower(trim($code));
    }
}
