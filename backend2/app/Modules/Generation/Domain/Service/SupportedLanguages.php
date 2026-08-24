<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Shared\Domain\Service\LanguageRoles;

/**
 * Which language pairs this deployment will search in.
 *
 * NO FIXED SIDE. A pair is «the language being taught» ↔ «a language the learner reads», and it is
 * valid exactly when the taught side is one this product teaches, the support side is a language
 * the catalogue names, and the two are different (DECISIONS пп. 85, 134). English is not required
 * anywhere: `ru → ro` and `tr → de` are pairs this class accepts, because a term carries its own
 * `lang` and a term may hold translations in several languages at once. What used to stand here —
 * one taught language read from `APP_TARGET_LANG` and a hand-listed `APP_NATIVE_LANGS` — was a
 * product limit that outlived its reason; the roster now comes from {@see LanguageRoles} and from
 * nowhere else (п. 145).
 *
 * DIRECTION IS NOT VALIDATED HERE, only membership. «EN → RU» and «RU → EN» are the same pair asked
 * two ways, and both are legitimate; which way round a given search goes is the learner's choice on
 * the pill, not something this class has an opinion about. WHICH SIDE IS THE TAUGHT ONE is a
 * separate question with its own answer — see {@see soleTaughtSide()}.
 */
final readonly class SupportedLanguages
{
    /**
     * The value of the legacy `target` field of `GET /search/languages`, frozen at `en`.
     *
     * The shipped app reads a SINGLE taught language from that field and builds its «На какой»
     * picker out of it; the list of them arrives in `targets` beside it, which that build does not
     * read yet. Removing the field would break a client that is already on a phone, and computing
     * it («the first taught language») would silently change which language that picker offers the
     * day the catalogue is reordered. So it is a constant with a date on it: it goes when the app
     * reads `targets`.
     */
    public const LEGACY_TARGET = 'en';

    /**
     * Everything that may stand on the TERM side — the languages this product teaches.
     *
     * @return list<string>
     */
    public function targets(): array
    {
        return LanguageRoles::taught();
    }

    /**
     * Everything that may stand on the LEARNER'S side, in the order the pill offers them.
     *
     * @return list<string>
     */
    public function natives(): array
    {
        return LanguageRoles::support();
    }

    /** Is `$code` a language this deployment can TEACH? */
    public function teaches(string $code): bool
    {
        return LanguageRoles::isTaught($code);
    }

    /** Is `$code` a language this deployment can put on the learner's side? */
    public function knowsNative(string $code): bool
    {
        return LanguageRoles::isSupport($code);
    }

    /**
     * Is this an orderable pair — one side taught, the other one we can name, and not the same one?
     *
     * The «not the same one» clause rules out `en → en`, which would ask the vendor to translate a
     * word into its own language and is the shape a swapped-twice client bug takes.
     */
    public function supports(string $source, string $target): bool
    {
        [$from, $to] = [LanguageRoles::normalize($source), LanguageRoles::normalize($target)];

        if ($from === $to) {
            return false;
        }

        return ($this->teaches($from) && $this->knowsNative($to))
            || ($this->teaches($to) && $this->knowsNative($from));
    }

    /**
     * The taught side of a pair this class has already accepted — or NULL when both sides are
     * taught languages and the direction alone cannot say.
     *
     * `de → ru` has one answer: `ru` is not taught, so `de` is the term side. `de → en` has two,
     * because the request carries a DIRECTION and not a pair of roles: it is either German studied
     * with English support or English studied with German support, and both are real. Null is the
     * honest answer to that, and the caller breaks the tie with the one fact the domain does not
     * hold — whose profile is asking (DECISIONS п. 147).
     */
    public function soleTaughtSide(string $source, string $target): ?string
    {
        [$from, $to] = [LanguageRoles::normalize($source), LanguageRoles::normalize($target)];

        if ($this->teaches($from) && $this->teaches($to)) {
            return null;
        }

        return $this->teaches($from) ? $from : ($this->teaches($to) ? $to : null);
    }
}
