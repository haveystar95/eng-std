<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\DueTermsReader;

/**
 * What the trainer deals next, drawn FROM THE LEARNER'S POOL.
 *
 * The pool is the queue; a collection is a catalogue. A word becomes studiable when the learner
 * says so — a «не знаю»/«не уверен» swipe, or «Учить это слово» on the word card — and until then it
 * sits in its collection and is never dealt. That is the whole change this chapter makes here, and
 * it removed a rule rather than adding one: there is no longer a «terms with no progress row are
 * new cards» branch, because a term with no row was never enrolled and a term that was enrolled has
 * a row by definition.
 *
 * Selection rules are otherwise unchanged. Due before new (a backlog of due cards means no new words
 * that day), first meetings only fill the leftover slots and never exceed the day's remaining quota,
 * session size capped so one call can't drown the learner.
 *
 * The two populations are read SEPARATELY and with their own limits, and that separation is load-
 * bearing: rung-0 pairs sort ahead of everything (they have no `due_at`, and the ordering is NULLS
 * FIRST), so a single capped query over a freshly triaged pool of a hundred words would come back
 * as a hundred first meetings, be trimmed to the daily quota, and leave every due repeat out of the
 * session.
 *
 * `collectionId` narrows the pool to one collection's terms — «потренировать аптечные перед
 * аптекой». It is a FILTER on the pool, never a source: a word of that collection which is not
 * enrolled stays out.
 */
final readonly class GetDueTermsHandler
{
    private const MAX_SESSION_SIZE = 100;
    private const SCOPE_CAP = 500;

    public function __construct(
        private DueTermsReader $reader,
        private UserCollectionTermsReader $collectionTerms,
    ) {}

    /** @return list<DueTermView> */
    public function __invoke(GetDueTerms $query): array
    {
        $size = max(1, min(self::MAX_SESSION_SIZE, $query->sessionSize));

        // null = the whole pool. A collection scope is resolved through Collections, so a
        // collection the user no longer has access to narrows the pool to nothing rather than
        // silently widening it to everything.
        $scope = $query->collectionId !== null
            ? $this->collectionTerms->termIdsForCollection($query->userId, $query->collectionId, self::SCOPE_CAP)
            : null;

        $quotaFree = $this->reader->selectableInPool($query->userId, $query->now, $scope, $size);

        $newLimit = min($size - count($quotaFree), max(0, $query->newTermsRemaining));
        if ($newLimit <= 0) {
            return $quotaFree;
        }

        return array_merge(
            $quotaFree,
            $this->reader->introductionsInPool($query->userId, $query->now, $scope, $newLimit),
        );
    }
}
