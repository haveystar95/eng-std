<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\ResolvedPair;
use App\Modules\Generation\Domain\Exception\UnsupportedLanguagePair;
use App\Modules\Generation\Domain\Service\SearchDirection;
use App\Modules\Generation\Domain\Service\SupportedLanguages;
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
     *
     * @throws UnsupportedLanguagePair
     */
    public function resolve(UserId $actorId, ?string $source, ?string $target): ResolvedPair
    {
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

        return new ResolvedPair(
            direction: new SearchDirection($from, $to),
            // A supported pair has the taught language on one side by definition, so the sides are
            // read off the configuration rather than guessed from the direction.
            termLang: $this->supported->target(),
            translationLang: $this->supported->nativeSideOf($from, $to),
        );
    }

    /**
     * The learner's own pair, as their profile has it.
     *
     * The sides come from the PROFILE here and not from the configured taught language, which is
     * the difference that matters for a learner whose target is not English: the database already
     * holds Polish terms, and reading their side off the config would look for English ones.
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
