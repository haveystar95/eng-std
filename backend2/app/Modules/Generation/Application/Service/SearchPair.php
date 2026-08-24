<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\ResolvedPair;
use App\Modules\Generation\Domain\Exception\TaughtSideNotTaught;
use App\Modules\Generation\Domain\Exception\UnsupportedLanguagePair;
use App\Modules\Generation\Domain\Service\SearchDirection;
use App\Modules\Generation\Domain\Service\SupportedLanguages;
use App\Modules\Generation\Domain\ValueObject\TaughtSide;
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
            : $this->taughtSideOf($actorId, $from, $to);

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
     * that is the term side whichever way the learner typed it. `de → en` names two, and the request
     * carries a direction rather than a pair of roles — it is either German with English support or
     * English with German support, and the query string cannot tell them apart.
     *
     * The tie is broken by the PROFILE, and only in that branch: the learner's own taught language,
     * when it is one of the two. That is a default and not a content read — the same standing the
     * profile has for the opening pair of the search screen (DECISIONS пп. 142, 147) — and it is
     * read lazily, so an unambiguous pair (every pair the shipped app sends today) costs nothing.
     *
     * With neither side matching the profile, the direction's `source` wins: the pill opens
     * «taught → support», so that is the likelier reading of a hand-made request.
     */
    private function taughtSideOf(UserId $actorId, string $from, string $to): string
    {
        $sole = $this->supported->soleTaughtSide($from, $to);
        if ($sole !== null) {
            return $sole;
        }

        $preferred = $this->languages->forUser($actorId)->target->value;

        return in_array($preferred, [$from, $to], true) ? $preferred : $from;
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
