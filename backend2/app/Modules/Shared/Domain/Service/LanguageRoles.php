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
 *
 * THE LEARNING PLAN'S TWO LISTS ARE THE EXCEPTION, and they are typed out on purpose (наряд LANG-1
 * §7). A plan is not a collection: its day is written by the model in BOTH languages of the pair and
 * checked in code on both sides — so a language may stand on a side of a plan only once
 * the plan's language pack for that side exists (`config/lesson/lang/<code>.php`: the native side's
 * talk title template and common words; the target side's negation, contractions and number words —
 * the key spec of the order). That is a product decision taken per language, not a capability that
 * falls out of a table, and a derived list would have offered Portuguese the day it grew a trainer.
 * They still live HERE, in code, and nowhere else (DECISIONS п. 145) — `config/plan.php` may only
 * NARROW the targets behind a flag (п. 82), never add one — and they are held to the derived lists by
 * tests: every plan target is taught, every plan native is a language the catalogue names. Their
 * order is the plan's own: the order the entry screen offers them in.
 */
final class LanguageRoles
{
    /**
     * What a plan may TEACH, in the order the entry screen offers it (наряд LANG-1 §7). English first —
     * the language every learner of this app started with.
     *
     * @var list<string>
     */
    private const PLAN_TARGETS = ['en', 'pl', 'ro', 'es', 'it', 'de', 'fr'];

    /**
     * What a plan may be READ in — the learner's own language, the side the day's native lines, titles
     * and translations are written on (наряд LANG-1 §7). The three Eastern Slavic languages first — the
     * audience the app was built for — then the six that are targets as well. English is NOT on this
     * side: the order names nine natives and English is not one of them, so an English speaker's plan is
     * a `language_pair_invalid` until a decision says otherwise.
     *
     * @var list<string>
     */
    private const PLAN_NATIVES = ['ru', 'uk', 'be', 'pl', 'ro', 'es', 'it', 'de', 'fr'];

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

    /**
     * A REFERENCE language: one the catalogue names but this product does not teach.
     *
     * Derived, never stored (DECISIONS п. 136): zh and ja carry no trainer ({@see
     * LanguageModeSupport}), so a collection that teaches one of them is a phrasebook — a term, a
     * translation and an audio — and its words are outside the pool, the schedule and the daily
     * goal (п. 84). The day a judge and a normaliser exist for Chinese, that row grows a trainer and
     * every screen that asks this question changes its answer on its own.
     *
     * A code the catalogue has never heard of is NOT reference — it is nothing at all, and saying
     * «reference» about it would turn a typo into a product decision.
     */
    public static function isReference(string $code): bool
    {
        $normalized = self::normalize($code);

        return LanguageCatalog::knows($normalized) && ! self::isTaught($normalized);
    }

    /**
     * The languages a learning plan may teach — its target side (наряд LANG-1 §7). The deployment's
     * EFFECTIVE list is this one, narrowed by `PLAN_LANGUAGES` when that is set (`config/plan.php`);
     * it is never wider.
     *
     * @return list<string>
     */
    public static function planTargets(): array
    {
        return self::PLAN_TARGETS;
    }

    /**
     * The languages a learning plan may be read in — its native side (наряд LANG-1 §7). The plan takes
     * the learner's `profiles.native_language` and refuses a plan when it is not one of these
     * (`language_pair_invalid`). NOT the allow-list of the profile itself: a collection or a search may
     * be read in any language the catalogue names (п. 85), so the profile takes all of those.
     *
     * @return list<string>
     */
    public static function planNatives(): array
    {
        return self::PLAN_NATIVES;
    }

    /**
     * The first of the device's languages that a plan may be read in — or null when none of them is.
     *
     * The first sign-in's default for `profiles.native_language` (наряд LANG-1 §7): a phone set to
     * Ukrainian starts its learner on Ukrainian rather than on the column's `ru`. `$locales` is the
     * `Accept-Language` list, best first, as the framework spells it (`uk_UA`, `uk`, `be-BY`); an entry
     * is matched by its PRIMARY subtag only — the part before `-` or `_`, lower-cased — so `uk-UA` and
     * `uk_UA` and `UK` are all Ukrainian. The first entry that is a plan native wins, in the header's
     * own order: `en-US, uk;q=0.8` is Ukrainian, not «no answer», because English is not a language a
     * plan is read in and the learner named a second one.
     *
     * Null is the answer for a header of English only (the framework's default when a client sends
     * none), for an empty one, and for languages the plan does not read: the caller then leaves the
     * column's own default alone.
     *
     * @param  list<string>  $locales
     */
    public static function planNativeFromLocales(array $locales): ?string
    {
        foreach ($locales as $locale) {
            $primary = self::normalize((string) preg_replace('/[-_].*$/s', '', $locale));
            if (in_array($primary, self::PLAN_NATIVES, true)) {
                return $primary;
            }
        }

        return null;
    }

    /** Casefolded and trimmed, so `« RU »` and `ru` are one language and not two. */
    public static function normalize(string $code): string
    {
        return strtolower(trim($code));
    }
}
