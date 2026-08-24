<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\ResolvedPair;
use App\Modules\Generation\Domain\Exception\TaughtSideNotTaught;
use App\Modules\Generation\Domain\Exception\UnsupportedLanguagePair;
use App\Modules\Generation\Domain\Service\SearchDirection;
use App\Modules\Generation\Domain\Service\SupportedLanguages;
use App\Modules\Generation\Domain\ValueObject\TaughtSide;
use App\Modules\Shared\Domain\Service\LanguageRoles;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The pair a search runs in, resolved once for all three endpoints.
 *
 * ## The learner says which way, and nothing else gets a vote
 *
 * This replaces an earlier design where the vendor's own language detection decided the direction.
 * It was overruled for a concrete reason: on single words the detector is unreliable in a way that
 * is invisible until it bites — «gate» comes back as Norwegian and is answered as «улица», «случай»
 * as Bulgarian. A detector that is right nine times in ten is worse than no detector on a screen
 * where the tenth answer is a word the learner then studies.
 *
 * So the direction comes from the pill on the search screen, the pill remembers what it was last
 * set to, and the server takes it as given. The provider is called with an explicit source and an
 * explicit target every time.
 *
 * ## Falling back
 *
 * A request that names no pair gets the learner's profile one — English and their own language.
 * That is not a nicety for old clients only: `GET /search` is also reached from places that have no
 * pill, and «what this learner reads» is the right default everywhere.
 *
 * A pair that IS named and is not supported is refused ({@see UnsupportedLanguagePair}), never
 * quietly swapped for the default — see that class for why.
 *
 * ## Which side is being learned
 *
 * A direction is not a pair of roles, and the client may now say the roles outright
 * ({@see TaughtSide}). When it does not, {@see taughtSideOf()} works them out — from the pair when
 * only one side is teachable, and from the direction when both are. The profile does not get a
 * vote: it decides the OPENING pair and nothing else (DECISIONS пп. 142, 147).
 */
final readonly class SearchPair
{
    public function __construct(
        private LearnerLanguages $languages,
        private SupportedLanguages $supported,
    ) {}

    /**
     * @param  string|null  $source  the language the query is written in
     * @param  string|null  $target  the language the answer comes back in
     * @param  TaughtSide|null  $taughtSide  which half of the direction the learner is STUDYING,
     *                                       said by the client. Null falls back to `taughtSideOf()`.
     *
     * @throws UnsupportedLanguagePair
     * @throws TaughtSideNotTaught
     */
    public function resolve(
        UserId $actorId,
        ?string $source,
        ?string $target,
        ?TaughtSide $taughtSide = null,
    ): ResolvedPair {
        $from = strtolower(trim($source ?? ''));
        $to = strtolower(trim($target ?? ''));

        // Half a pair is not a pair. Both or neither — a request that named only a target would
        // otherwise get a direction nobody chose.
        if ($from === '' || $to === '') {
            return $this->fromProfile($actorId);
        }

        if (! $this->supported->supports($from, $to)) {
            throw UnsupportedLanguagePair::of($from, $to);
        }

        // A supported pair has a taught language on one side — but «which side» is a question the
        // direction alone answers only when the OTHER side is not taught too. The client may now
        // ANSWER it instead of leaving it to be guessed; `taughtSideOf()` is the fallback for the
        // requests that do not (DECISIONS п. 147).
        $termLang = $taughtSide !== null
            ? $this->namedTaughtSide($taughtSide, $from, $to)
            : $this->taughtSideOf($from, $to);

        return new ResolvedPair(
            direction: new SearchDirection($from, $to),
            termLang: $termLang,
            translationLang: $termLang === $from ? $to : $from,
        );
    }

    /**
     * The side the CLIENT named, checked against what this deployment can actually teach.
     *
     * Checked rather than trusted: a role is a claim about the deployment's capabilities, and a
     * client that has not re-read `GET /search/languages` since a language was retired would
     * otherwise have the server answer in a language nobody teaches — invisibly, because the screen
     * would look exactly the same.
     *
     * @throws TaughtSideNotTaught
     */
    private function namedTaughtSide(TaughtSide $side, string $from, string $to): string
    {
        $lang = $side->of($from, $to);
        if (! $this->supported->teaches($lang)) {
            throw TaughtSideNotTaught::of($side, $lang);
        }

        return $lang;
    }

    /**
     * Which half of the direction is the language being LEARNED.
     *
     * Usually the pair names it: in `ru → ro` only one side is a language this product teaches, and
     * that is the term side whichever way the learner typed it — `ru` has no trainers, so nobody is
     * learning it here. That branch is {@see SupportedLanguages::soleTaughtSide()} and it is most
     * requests.
     *
     * `en → es` names TWO taught languages, and there the DIRECTION is the answer: the learner types
     * what they already have and asks for what they do not. «Translate this English word into
     * Spanish» is somebody studying Spanish, so the TARGET side is the taught one and the source is
     * support. The same reading run backwards is just as true: `es → en` is somebody studying
     * English who happens to write Spanish.
     *
     * THIS REPLACED A PROFILE TIE-BREAK (DECISIONS п. 147, amended 24.08), and the replacement is a
     * bug fix rather than a preference. The profile rule answered `en → es` with «English studied
     * with Spanish support», because English is what `profiles.default_target_lang` holds — so the
     * card put the English word in the headline, the Spanish in small type underneath, and the save
     * sheet offered to file the word under «English ← Spanish». Every one of those is the opposite
     * of what the learner asked for, and none of them looks broken on screen: it looks like an app
     * that disagrees with you. A rule that only holds while somebody studies exactly one language
     * was never going to survive a product whose whole current chapter is «any pair» (п. 134).
     *
     * The profile keeps the one job it is allowed to keep — the OPENING pair of the screen
     * ({@see fromProfile()}, п. 142). It no longer decides what a stated pair means.
     */
    private function taughtSideOf(string $from, string $to): string
    {
        $sole = $this->supported->soleTaughtSide($from, $to);

        return $sole ?? LanguageRoles::normalize($to);
    }

    /**
     * The learner's own pair, as their profile has it.
     *
     * The sides come from the PROFILE, which is where a request that named no pair has to get them:
     * this is the «стартовая пара поиска» the profile is still allowed to decide (DECISIONS п. 142).
     */
    public function fromProfile(UserId $actorId): ResolvedPair
    {
        $langs = $this->languages->forUser($actorId);

        return new ResolvedPair(
            // Taught language first: «EN → RU» is the direction somebody opens the screen in,
            // because looking up a word they have just met is the commoner half of the job.
            direction: new SearchDirection($langs->target->value, $langs->native->value),
            termLang: $langs->target->value,
            translationLang: $langs->native->value,
        );
    }
}
