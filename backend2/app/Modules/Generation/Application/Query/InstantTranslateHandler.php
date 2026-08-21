<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Query;

use App\Modules\Generation\Application\Dto\InstantHintView;
use App\Modules\Generation\Application\Port\InstantTranslationCache;
use App\Modules\Generation\Application\Port\TranslationProvider;
use App\Modules\Generation\Application\Service\LearnerLanguages;
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
 */
final readonly class InstantTranslateHandler
{
    public function __construct(
        private ExactTermTranslationReader $terms,
        private InstantTranslationCache $cache,
        private TranslationProvider $provider,
        private TranslationMonthlyBudget $budget,
        private LearnerLanguages $languages,
        private Clock $clock,
    ) {}

    public function __invoke(InstantTranslate $query): InstantHintView
    {
        $normalized = self::normalize($query->query);
        if ($normalized === '') {
            return InstantHintView::nothing($query->query);
        }

        $langs = $this->languages->forUser($query->actorId);
        $pair = $langs->target->value . ':' . $langs->native->value;

        // 1. Our own catalogue — free, and the string the card would show.
        $known = $this->terms->translationFor($normalized, $langs->target->value, $langs->native->value);
        if ($known !== null) {
            return InstantHintView::hit($normalized, $known, InstantHintView::SOURCE_VOCABULARY);
        }

        // 2. The cache — free, bought once by somebody, for everybody.
        $cached = $this->cache->find($normalized, $pair);
        if ($cached !== null) {
            return InstantHintView::hit($normalized, $cached, InstantHintView::SOURCE_CACHE);
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
            $translated = $this->provider->translate($normalized, $langs->target->value, $langs->native->value);
        } catch (Throwable) {
            // A dead or slow vendor is an empty line, not an error. Deliberately not logged here:
            // the call itself is already in the outbound request log with its status and duration,
            // which is where someone debugging this would look anyway.
            return InstantHintView::nothing($normalized);
        }

        if ($translated === null) {
            return InstantHintView::nothing($normalized);
        }

        // Written before it is returned, so the very next keystroke that re-sends the same word is
        // already free — a debounced field re-asks constantly, and a cache filled after the answer
        // is handed back would miss most of those.
        $this->cache->store(
            $normalized,
            $pair,
            $translated->text,
            $translated->provider,
            $translated->characters,
        );

        return InstantHintView::hit($normalized, $translated->text, $translated->provider);
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
