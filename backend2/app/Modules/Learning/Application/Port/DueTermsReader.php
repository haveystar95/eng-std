<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * Reads the selectable-cards projection (`user_term_progress`) for session assembly. Backs the
 * hot query that runs at every session start, so implementations must use the partial indexes
 * added with the acquisition ladder and re-cut with the pool.
 *
 * EVERY method here is scoped to THE POOL — `enrolled_at IS NOT NULL`. That is the chapter's whole
 * point: a collection is a catalogue of a topic, and the trainer works through the learner's own
 * list of words. A term sitting in a collection the learner has never triaged is not a card waiting
 * to be dealt; it is a word in a book.
 *
 * `$termIds` is the OPTIONAL narrowing on top of that — a collection's terms, for «потренировать
 * аптечные перед аптекой». `null` means the whole pool, which is the ordinary case; an empty array
 * means an empty scope and therefore an empty result.
 */
interface DueTermsReader
{
    /**
     * Pool pairs the trainer may deal right now WITHOUT spending the daily new-term quota. There are
     * two reasons a pair qualifies, and they are different in kind:
     *
     *  * ON THE LADDER (`acquisition = 'learning'`) — introduced, unfinished. It has no `due_at`,
     *    because the recognition rungs never schedule; it is selectable because it is half-taught,
     *    and it is the most urgent thing the trainer has.
     *  * SCHEDULABLE (`acquisition = 'graduated'`) — either due (`due_at <= now`) or never yet
     *    scheduled (`due_at IS NULL`): a pair that has just graduated off the ladder is owed its
     *    first SRS review, and so is a pair returned from `known` to `new`.
     *
     * Pairs at rung 0 are deliberately NOT here — they are a first meeting and they cost quota, so
     * they have their own method and their own limit. Mixing the two populations under one limit is
     * how a freshly triaged pool of a hundred words would push every due repeat out of the session.
     *
     * Ordered `due_at NULLS FIRST`, which puts the unfinished ahead of the overdue and both ahead
     * of the merely due, then soonest first within them.
     *
     * @param  list<string>|null  $termIds  null = the whole pool
     * @return list<DueTermView>
     */
    public function selectableInPool(UserId $userId, DateTimeImmutable $now, ?array $termIds, int $limit): array;

    /**
     * Pool pairs standing at RUNG 0 — enrolled, never shown. Each one is a first meeting and is
     * charged to the day's new-term quota, which is why the caller passes that quota as `$limit`.
     *
     * Oldest enrolment first: the words the learner asked for earliest are taught earliest, which is
     * the only ordering a queue can defend.
     *
     * @param  list<string>|null  $termIds  null = the whole pool
     * @return list<DueTermView>
     */
    public function introductionsInPool(UserId $userId, ?array $termIds, int $limit): array;

    /**
     * Every pool pair in scope — ANY state, ANY rung, ignoring `due_at`. Backs free practice, which
     * drills what the learner is studying on demand, so it deliberately skips the due/state filters
     * (a studied-but-not-due word is exactly what practice is for).
     *
     * @param  list<string>|null  $termIds  null = the whole pool
     * @return list<DueTermView>
     */
    public function allInPool(UserId $userId, ?array $termIds, int $limit): array;
}
