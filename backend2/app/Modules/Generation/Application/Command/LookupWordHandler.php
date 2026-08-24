<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Dto\LookupOutcome;
use App\Modules\Generation\Application\Dto\WordLookupBrief;
use App\Modules\Generation\Application\Port\SearchLookupCache;
use App\Modules\Generation\Application\Port\WordLookupPort;
use App\Modules\Generation\Application\Service\LookupBarrier;
use App\Modules\Generation\Application\Service\SearchPair;
use App\Modules\Generation\Domain\Exception\LookupRefused;
use App\Modules\Generation\Domain\Service\NegativeVerdictLifetime;
use App\Modules\Generation\Domain\Service\SearchLookupDailyLimit;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
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
 *
 * ## A confirmed translation does NOT reopen the cache
 *
 * `fixedTranslation` travels into the model call and nowhere near the cache key. A cache HIT is
 * still served, free, exactly as before — the row is a fact about the word, the economics of п. 72
 * are the whole reason this endpoint is affordable, and re-buying a card because one learner's
 * translator line worded things differently would spend the daily cap on a card we already have.
 *
 * What honours the confirmation on a cache hit is the SAVE, not the lookup: `POST /search/add`
 * takes the same confirmed translation and pins it on the term
 * ({@see AddSearchResultHandler}). So the contract holds on both paths, and only the branch that
 * was already paying for a call pays anything for it.
 */
final readonly class LookupWordHandler
{
    public function __construct(
        private SearchLookupCache $cache,
        private WordLookupPort $model,
        private LookupBarrier $barrier,
        private SearchLookupDailyLimit $limit,
        private SearchPair $pair,
        private Clock $clock,
    ) {}

    public function __invoke(LookupWord $command): LookupOutcome
    {
        $pair = $this->pair->resolve($command->actorId, $command->source, $command->target, $command->taughtSide);
        $normalized = self::normalize($command->query);
        $cap = $this->limit->cap();

        if ($normalized === '') {
            // Whitespace survives `required`. Refused here rather than passed on: an empty query
            // is the one input guaranteed to produce a paid answer about nothing.
            throw LookupRefused::emptyQuery();
        }

        $cached = $this->cache->find($normalized, $pair->termLang, $pair->translationLang);
        // «Not a word» is a PERISHABLE verdict, unlike the card beside it. It is served free while
        // it is fresh — that is what stops a paste-and-retry loop buying the same refusal ten times
        // — and it is dropped once it is a day old, because the cache is global and a model that
        // declines a real word once would otherwise close that word for everybody forever
        // ({@see NegativeVerdictLifetime}). A stale refusal is treated as a MISS, not as an answer:
        // it must not survive into the branches below either, including the one that serves an old
        // row when the cap is spent.
        if ($cached !== null && $cached->notRecognized) {
            // A RE-TAP outranks the verdict. The learner is looking at «не получилось распознать»
            // over a word they know exists and has pressed the button again; they are the retry,
            // and asking them to wait a day for the automatic expiry would be the app defending a
            // mistake it can see. The expiry below still governs every path nobody is watching.
            $disputed = $command->retry
                || NegativeVerdictLifetime::isStale($cached->createdAt, $this->clock->now());
            if (! $disputed) {
                return LookupOutcome::notRecognized($cap, $this->usedToday($command));
            }
            $cached = null;
        }
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
                targetLang: new LanguageCode($pair->termLang),
                nativeLang: new LanguageCode($pair->translationLang),
                fixedTranslation: $command->fixedTranslation,
            ));
        } catch (LookupRefused $e) {
            throw $e;
        } catch (Throwable) {
            // The vendor, not the content. Same shape of answer to the client either way: the word
            // could not be looked up, here is what the database has.
            throw LookupRefused::modelUnavailable();
        }

        // Nothing to screen and nothing to show. Still STORED, so the day's cap counts the call that
        // was actually bought and the next paste of the same keystrokes is free — for a day, after
        // which the refusal expires and the word gets asked again.
        $screened = $answer->notRecognized
            ? $answer
            : $this->barrier->screen($answer, $pair->termLang, $pair->translationLang);

        $stored = $this->cache->store(
            $command->actorId,
            $normalized,
            $pair->termLang,
            $pair->translationLang,
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
