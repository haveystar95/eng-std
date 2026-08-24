<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Generation\Application\Dto\InstantHintView;
use App\Modules\Generation\Application\Dto\ResolvedPair;
use App\Modules\Generation\Application\Port\InstantTranslationCache;
use App\Modules\Generation\Application\Port\TranslationProvider;
use App\Modules\Generation\Application\Service\SearchPair;
use App\Modules\Generation\Domain\Service\SearchQueryLength;
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
 * NOTHING HERE THROWS — except an unsupported pair, which is the client naming a language this
 * deployment does not serve and is not a runtime condition. Every other failure — no key, no
 * budget, a dead vendor, a two-second timeout — is an empty line, and the search and the full
 * lookup carry on untouched. A hint that could break the screen it decorates would not be worth
 * having.
 *
 * ## BOTH WAYS, and the LEARNER says which
 *
 * The field takes either half of the pair. «occasion» is answered in Russian; «случай» is answered
 * in English — and that second direction is the reason somebody opens this screen: a word you
 * cannot yet name in the language you are learning is precisely the word worth turning into a card.
 *
 * WHICH way comes from the pill on the search screen and from nothing else. An earlier version let
 * the vendor's own language detection decide and it was removed: on a single word the detector is
 * confidently wrong often enough to matter — «gate» reads as Norwegian and comes back «улица» —
 * and there is no repair for that, because the wrong answer looks exactly like a right one. A
 * stated direction is always honoured, even when the learner typed the other language into it;
 * that is a mistake they can see and fix in one tap, which a silent mistranslation is not.
 */
final readonly class InstantTranslateHandler
{
    public function __construct(
        private ExactTermTranslationReader $terms,
        private InstantTranslationCache $cache,
        private TranslationProvider $provider,
        private TranslationMonthlyBudget $budget,
        private SearchPair $pair,
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

        $pair = $this->pair->resolve($query->actorId, $query->source, $query->target, $query->taughtSide);

        // 1. Our own catalogue — free, and the string the card would show. ONE direction, the one
        //    the learner asked for: looking the other way too would answer a question they did not
        //    ask, in a language they did not pick.
        $known = $pair->reversed()
            ? $this->reverseHit($normalized, $pair)
            : $this->forwardHit($normalized, $pair);
        if ($known !== null) {
            return $known;
        }

        // 2. The cache — free, bought once by somebody, for everybody. One key, because the key
        //    carries the direction and the direction is now a fact rather than a guess.
        $cached = $this->cache->find($normalized, $pair->direction->pair());
        if ($cached !== null && ! self::isEcho($normalized, $cached)) {
            return InstantHintView::hit(
                $normalized,
                $cached,
                InstantHintView::SOURCE_CACHE,
                reversed: $pair->reversed(),
            );
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
            $translated = $this->provider->translate(
                $normalized,
                $pair->direction->source,
                $pair->direction->target,
            );
        } catch (Throwable) {
            // A dead or slow vendor is an empty line, not an error. Deliberately not logged here:
            // the call itself is already in the outbound request log with its status and duration,
            // which is where someone debugging this would look anyway.
            return InstantHintView::nothing($normalized);
        }

        if ($translated === null) {
            return InstantHintView::nothing($normalized);
        }

        // A word the vendor handed straight back is not an answer, and must not become a cache row.
        // Rarer now that both languages are stated — it used to be what an auto-detected call did
        // when it guessed the source wrong — but still reachable: a proper noun, a loanword, a
        // query already in the target language.
        if (self::isEcho($normalized, $translated->text)) {
            return InstantHintView::nothing($normalized);
        }

        // Written before it is returned, so the very next keystroke that re-sends the same word is
        // already free — a debounced field re-asks constantly, and a cache filled after the answer
        // is handed back would miss most of those.
        $this->cache->store(
            $normalized,
            $pair->direction->pair(),
            $translated->text,
            $translated->provider,
            $translated->characters,
        );

        return InstantHintView::hit(
            $normalized,
            $translated->text,
            $translated->provider,
            reversed: $pair->reversed(),
        );
    }

    /** The learner typed the language being taught: the answer is the term's translation. */
    private function forwardHit(string $normalized, ResolvedPair $pair): ?InstantHintView
    {
        $known = $this->terms->translationFor($normalized, $pair->termLang, $pair->translationLang);

        return $known !== null
            ? InstantHintView::hit($normalized, $known, InstantHintView::SOURCE_VOCABULARY)
            : null;
    }

    /** The learner typed their own language: the answer is the word they were reaching for. */
    private function reverseHit(string $normalized, ResolvedPair $pair): ?InstantHintView
    {
        $known = $this->terms->termForTranslation($normalized, $pair->termLang, $pair->translationLang);

        return $known !== null
            ? InstantHintView::hit($normalized, $known, InstantHintView::SOURCE_VOCABULARY, reversed: true)
            : null;
    }

    /**
     * A «translation» that is the query back again is not an answer, and must never be served.
     *
     * A guard on the CACHE and on the vendor, never on our own catalogue: a curated term whose
     * translation really is its own spelling is content somebody wrote on purpose.
     *
     * It earns its keep on the cache side because the cache is permanent and was filled, for a
     * while, by a version of this code that let the vendor detect the source: asked to turn
     * «случай» into Russian, DeepL handed it straight back and the row was stored. Those rows would
     * answer «случай — случай» forever, on the one screen whose whole job is to name a word the
     * learner cannot name yet. Skipping them costs one vendor call and replaces them with a real
     * answer under the same key — self-healing, with no migration and nothing deleted.
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
