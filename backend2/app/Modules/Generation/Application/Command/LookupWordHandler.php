<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Dto\LookupOutcome;
use App\Modules\Generation\Application\Dto\WordLookupBrief;
use App\Modules\Generation\Application\Port\SearchLookupCache;
use App\Modules\Generation\Application\Port\WordLookupPort;
use App\Modules\Generation\Application\Service\LearnerLanguages;
use App\Modules\Generation\Application\Service\LookupBarrier;
use App\Modules\Generation\Domain\Exception\LookupRefused;
use App\Modules\Generation\Domain\Service\SearchLookupDailyLimit;
use App\Modules\Shared\Domain\Service\Clock;
use Throwable;

/**
 * Cache, then cap, then call — and the order is the whole design.
 *
 * A cache hit is served BEFORE the cap is consulted, because nothing is bought: charging a quota
 * slot for a free answer would make the feature worse the more it is used, which is exactly
 * backwards. The cap then guards the one branch that spends money, and the call itself happens
 * last, once.
 *
 * There is no retry. A lookup is a person watching a spinner over a fraction of a cent; a second
 * attempt doubles the wait for a model that has already had its say. A transport failure surfaces
 * as a refusal the learner can act on (type it again) rather than as a silent slow path.
 */
final readonly class LookupWordHandler
{
    public function __construct(
        private SearchLookupCache $cache,
        private WordLookupPort $model,
        private LookupBarrier $barrier,
        private SearchLookupDailyLimit $limit,
        private LearnerLanguages $languages,
        private Clock $clock,
    ) {}

    public function __invoke(LookupWord $command): LookupOutcome
    {
        $langs = $this->languages->forUser($command->actorId);
        $normalized = self::normalize($command->query);
        $cap = $this->limit->cap();

        if ($normalized === '') {
            // Whitespace survives `required`. Refused here rather than passed on: an empty query
            // is the one input guaranteed to produce a paid answer about nothing.
            throw LookupRefused::emptyQuery();
        }

        $cached = $this->cache->find($normalized, $langs->target->value, $langs->native->value);
        // A row written before a field the card now needs existed is a STALE hit, not a hit: the
        // cache is global and permanent, so honouring it would let whoever looked a word up first
        // freeze that word's card for everybody, forever. Re-asking costs one cheap call and
        // replaces the row — see CachedLookup::$illustrationDecided.
        if ($cached !== null && $cached->illustrationDecided) {
            // Free, for this learner and every other one, forever. The cap is not even read.
            return LookupOutcome::answered($cached, $cap, $this->usedToday($command));
        }

        $used = $this->usedToday($command);
        // …but a stale row still beats nothing. With the cap spent, serve what we already have
        // rather than withholding a perfectly readable card over a missing photo.
        if ($cached !== null && ! $this->limit->allows($used)) {
            return LookupOutcome::answered($cached, $cap, $used);
        }
        if (! $this->limit->allows($used)) {
            return LookupOutcome::capReached($cap, $used);
        }

        try {
            $answer = $this->model->lookUp(new WordLookupBrief(
                query: $command->query,
                targetLang: $langs->target,
                nativeLang: $langs->native,
            ));
        } catch (LookupRefused $e) {
            throw $e;
        } catch (Throwable) {
            // The vendor, not the content. Same shape of answer to the client either way: the word
            // could not be looked up, here is what the database has.
            throw LookupRefused::modelUnavailable();
        }

        $screened = $this->barrier->screen($answer, $langs->target->value, $langs->native->value);

        $stored = $this->cache->store(
            $command->actorId,
            $normalized,
            $langs->target->value,
            $langs->native->value,
            $screened,
        );

        return LookupOutcome::answered($stored, $cap, $used + 1);
    }

    private function usedToday(LookupWord $command): int
    {
        // A rolling UTC day, like the collection quota: a calendar day in the learner's own timezone
        // would make the cap move when they travel, and this is a runaway guard, not a promise.
        $since = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0);

        return $this->cache->countPaidSince($command->actorId, $since);
    }

    /** The cache key. Same casefold + whitespace collapse the free search normalises with. */
    public static function normalize(string $query): string
    {
        return (string) preg_replace('/\s+/u', ' ', mb_strtolower(trim($query)));
    }
}
