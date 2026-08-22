<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Generation\Application\Dto\InstantHintView;
use App\Modules\Generation\Application\Port\InstantTranslationCache;
use App\Modules\Generation\Application\Port\TranslationProvider;
use App\Modules\Generation\Application\Service\LearnerLanguages;
use App\Modules\Generation\Domain\Service\SearchDirection;
use App\Modules\Generation\Domain\Service\SearchQueryLength;
use App\Modules\Generation\Domain\Service\TranslationDirection;
use App\Modules\Generation\Domain\Service\TranslationMonthlyBudget;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Vocabulary\Application\Query\ExactTermTranslationReader;
use Throwable;

/**
 * THREE LADDERS DOWN, and each rung is only reached because the one above it had nothing.
 *
 *   1. OUR OWN CATALOGUE. If the word is already a term with a translation, that is the answer —
 *      and it is the BEST answer, not merely the cheapest: it is the string the learner will see on
 *      the card if they save the word, written by the lookup model against rules a general-purpose
 *      translator knows nothing about. A hint that disagreed with the card it previews would be
 *      worse than no hint.
 *   2. THE CACHE. Bought once, by whoever typed the word first, and free for everybody after.
 *   3. THE VENDOR. The only rung that costs anything, and the only one with a budget in front of it.
 *
 * The order is the whole design. On a debounced search field the same few hundred words are typed
 * over and over, so rungs 1 and 2 answer the overwhelming majority of requests, and the free plan's
 * half-million characters a month stretch far further than the raw request count suggests.
 *
 * NOTHING HERE THROWS. It is a hint under a text field: every failure — no key, no budget, a dead
 * vendor, a two-second timeout — is an empty line, and the search and the full lookup carry on
 * untouched. A hint that could break the screen it decorates would not be worth having.
 *
 * ## BOTH WAYS, and the reverse one is the point
 *
 * The field takes either half of the learner's pair. «occasion» is answered in Russian; «случай» is
 * answered in English — and that second direction is not a courtesy, it is the reason somebody
 * opens this screen: a word you cannot yet name in the language you are learning is precisely the
 * word worth turning into a card.
 *
 * WHICH way is not decided here and not decided by the alphabet. It is decided by the provider's
 * own detection, because Cyrillic-versus-Latin is the only thing an alphabet can tell us and it
 * says nothing at all about Romanian, Polish or German. The alphabet gets two jobs — picking which
 * direction to ASK for, since a target language has to be named before a source one can be
 * reported, and standing in when the detector names a language that is neither half of the pair.
 * See {@see TranslationDirection}, whose docblock explains why that second job exists.
 */
final readonly class InstantTranslateHandler
{
    public function __construct(
        private ExactTermTranslationReader $terms,
        private InstantTranslationCache $cache,
        private TranslationProvider $provider,
        private TranslationMonthlyBudget $budget,
        private LearnerLanguages $languages,
        private TranslationDirection $direction,
        private SearchQueryLength $length,
        private Clock $clock,
    ) {}

    public function __invoke(InstantTranslate $query): InstantHintView
    {
        $normalized = self::normalize($query->query);
        if ($normalized === '') {
            return InstantHintView::nothing($query->query);
        }

        // Before anything that costs: a pasted paragraph is billed by the character, and this app
        // has nothing to say about one anyway.
        if ($this->length->exceeded($normalized)) {
            return InstantHintView::tooLong($normalized);
        }

        $langs = $this->languages->forUser($query->actorId);
        $native = $langs->native->value;
        $target = $langs->target->value;

        // The alphabet's opinion, used to ORDER the free lookups below and to pick the direction the
        // vendor is asked for. Never reported, never stored, never allowed to be the answer.
        $guess = $this->direction->guess($normalized, $native, $target);
        $typedNative = $guess->source === $native;

        // 1. Our own catalogue — free, and the string the card would show. Asked in the likely
        //    direction first; both are exact, so a hit either way is a real hit.
        $known = $typedNative
            ? $this->reverseHit($normalized, $target, $native) ?? $this->forwardHit($normalized, $target, $native)
            : $this->forwardHit($normalized, $target, $native) ?? $this->reverseHit($normalized, $target, $native);
        if ($known !== null) {
            return $known;
        }

        // 2. The cache — free, bought once by somebody, for everybody. BOTH directions are checked,
        //    because the key carries the direction and the guess that would pick one of them is
        //    exactly the thing we do not trust. Two indexed reads are cheaper than one vendor call.
        $cached = $this->cachedHit($normalized, $guess, $native, $target);
        if ($cached !== null) {
            return $cached;
        }

        // 3. The vendor. Everything below this line can cost money, so everything below this line
        //    is guarded.
        if (! $this->provider->isAvailable()) {
            return InstantHintView::disabled($normalized);
        }

        $length = mb_strlen($normalized);
        $used = $this->cache->charactersUsedSince($this->provider->name(), $this->monthStart());
        if (! $this->budget->allows($used, $length)) {
            return InstantHintView::outOfBudget($normalized);
        }

        try {
            $translated = $this->provider->translate($normalized, TranslationProvider::SOURCE_AUTO, $guess->target);
        } catch (Throwable) {
            // A dead or slow vendor is an empty line, not an error. Deliberately not logged here:
            // the call itself is already in the outbound request log with its status and duration,
            // which is where someone debugging this would look anyway.
            return InstantHintView::nothing($normalized);
        }

        if ($translated === null) {
            return InstantHintView::nothing($normalized);
        }

        $resolved = $this->direction->resolve($translated->detectedSource, $native, $target, $guess);
        $spent = $translated->characters;

        // The guess was wrong — the learner's own language written in a script that does not betray
        // it. What came back is in the language they typed, which is no answer at all, so it is
        // bought again the right way round and BOTH calls are metered: the first one was billed
        // whether or not it was useful, and a meter that forgot it would drift from the real quota.
        if (! $resolved->equals($guess)) {
            if (! $this->budget->allows($used + $spent, $length)) {
                return InstantHintView::outOfBudget($normalized);
            }

            try {
                $corrected = $this->provider->translate($normalized, TranslationProvider::SOURCE_AUTO, $resolved->target);
            } catch (Throwable) {
                return InstantHintView::nothing($normalized);
            }

            if ($corrected === null) {
                return InstantHintView::nothing($normalized);
            }

            $translated = $corrected;
            $spent += $corrected->characters;
        }

        // Checked once, after any correction, so neither call can teach the cache a non-answer.
        // The characters are spent either way; a stored echo would go on returning nothing for as
        // long as the deployment lives, which is how the rows this guard was written for got there.
        if (self::isEcho($normalized, $translated->text)) {
            return InstantHintView::nothing($normalized);
        }

        // Written before it is returned, so the very next keystroke that re-sends the same word is
        // already free — a debounced field re-asks constantly, and a cache filled after the answer
        // is handed back would miss most of those.
        $this->cache->store(
            $normalized,
            $resolved->pair(),
            $translated->text,
            $translated->provider,
            $spent,
        );

        return InstantHintView::hit(
            $normalized,
            $translated->text,
            $translated->provider,
            reversed: $resolved->source === $native,
        );
    }

    /** The learner typed the language being learned: the answer is in their own. */
    private function forwardHit(string $normalized, string $target, string $native): ?InstantHintView
    {
        $known = $this->terms->translationFor($normalized, $target, $native);

        return $known !== null
            ? InstantHintView::hit($normalized, $known, InstantHintView::SOURCE_VOCABULARY)
            : null;
    }

    /** The learner typed their own language: the answer is the word they were reaching for. */
    private function reverseHit(string $normalized, string $target, string $native): ?InstantHintView
    {
        $known = $this->terms->termForTranslation($normalized, $target, $native);

        return $known !== null
            ? InstantHintView::hit($normalized, $known, InstantHintView::SOURCE_VOCABULARY, reversed: true)
            : null;
    }

    private function cachedHit(string $normalized, SearchDirection $guess, string $native, string $target): ?InstantHintView
    {
        $other = $guess->source === $native
            ? new SearchDirection($target, $native)
            : new SearchDirection($native, $target);

        foreach ([$guess, $other] as $direction) {
            $cached = $this->cache->find($normalized, $direction->pair());
            if ($cached !== null && ! self::isEcho($normalized, $cached)) {
                return InstantHintView::hit(
                    $normalized,
                    $cached,
                    InstantHintView::SOURCE_CACHE,
                    reversed: $direction->source === $native,
                );
            }
        }

        return null;
    }

    /**
     * A «translation» that is the query back again is not an answer, and must never be served.
     *
     * This is a guard on the CACHE and on the vendor, never on our own catalogue: a curated term
     * whose translation really is its own spelling («taxi» → «такси» is not this, but «kiwi» →
     * «kiwi» could be) is content somebody wrote on purpose.
     *
     * It earns its place because the cache is permanent and was filled by a version of this code
     * that only ever translated one way: asked to turn «случай» into Russian, DeepL handed it
     * straight back, and the row was stored. Those rows would answer «случай — случай» forever, on
     * the one screen whose whole job is to name a word the learner cannot name yet. Skipping them
     * costs one vendor call and replaces them with a real answer under the right key — self-healing,
     * with no migration and nothing deleted.
     */
    private static function isEcho(string $query, string $translation): bool
    {
        return mb_strtolower(trim($translation)) === mb_strtolower(trim($query));
    }

    /**
     * The 1st of the current month, UTC — where the vendor's meter resets.
     *
     * UTC and not the learner's timezone: the quota belongs to the DEPLOYMENT, not to a person, and
     * a budget whose month began at a different hour for each user could not be summed at all.
     */
    private function monthStart(): \DateTimeImmutable
    {
        return $this->clock->now()
            ->setTimezone(new \DateTimeZone('UTC'))
            ->modify('first day of this month')
            ->setTime(0, 0);
    }

    /** The cache key, and the same normalisation the free search and the full lookup use. */
    public static function normalize(string $query): string
    {
        return (string) preg_replace('/\s+/u', ' ', mb_strtolower(trim($query)));
    }
}
