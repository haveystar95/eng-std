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
 * Every SELECTION method here is scoped to THE POOL — `enrolled_at IS NOT NULL`. That is the
 * chapter's whole point: a collection is a catalogue of a topic, and the trainer works through the
 * learner's own list of words. A term sitting in a collection the learner has never triaged is not
 * a card waiting to be dealt; it is a word in a book.
 *
 * {@see allInScope()} is the ONE exception, and it is not a selection for the trainer: it backs the
 * free-practice DRILL over a collection, which is allowed to open the book. It says so in its name
 * and returns rows tagged with {@see DueTermView::$inPool} so a caller can never confuse the two.
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
     * JUST-ENROLLED FIRST, then oldest enrolment first.
     *
     * The queue's own defensible ordering is FIFO — the words asked for earliest are taught
     * earliest — and it stays the rule for everything but the last day. What broke it: taking a word
     * with «Учить сразу» from the translator, on a day already closed, put it at the BACK of a queue
     * of forty, so the act the learner had just performed produced no visible word anywhere. A word
     * taken a minute ago is the word they came to study.
     *
     * The window is the last 24 HOURS and not «today» on purpose: this reader has a clock and no
     * timezone, and a calendar rule would drop a word added at 23:50 to the back of the queue at
     * 00:10 — which is the same complaint, ten minutes later.
     *
     * @param  list<string>|null  $termIds  null = the whole pool
     * @return list<DueTermView>
     */
    public function introductionsInPool(UserId $userId, DateTimeImmutable $now, ?array $termIds, int $limit): array;

    /**
     * Every pool pair in scope — ANY state, ANY rung, ignoring `due_at`. Backs free practice, which
     * drills what the learner is studying on demand, so it deliberately skips the due/state filters
     * (a studied-but-not-due word is exactly what practice is for).
     *
     * @param  list<string>|null  $termIds  null = the whole pool
     * @return list<DueTermView>
     */
    public function allInPool(UserId $userId, ?array $termIds, int $limit): array;

    /**
     * Every progress row in scope, IN THE POOL OR NOT — the one read here that steps outside it.
     *
     * It backs free practice over a COLLECTION, which drills the topic and not the queue: «зашёл в
     * кафе, открыл тему, прошёл маленькую тренировку без разбора коллекции». Practice enrols
     * nothing, writes no exposure and schedules nothing, so reaching a word outside the pool costs
     * that word nothing — it is still outside the pool when the session ends.
     *
     * Rows come back tagged with {@see DueTermView::$inPool}, because the two populations are dealt
     * DIFFERENT cards (see {@see \App\Modules\Learning\Domain\Service\LearningLadder::STEP_UNENROLLED_PRACTICE}).
     * A scope term with no progress row at all is simply absent — the caller fills it in with
     * {@see DueTermView::outOfPool()} rather than this seeding a row for every unseen word.
     *
     * The scope is REQUIRED: there is no «everything, pool or not» question, and an empty array is
     * an empty scope and therefore an empty result.
     *
     * @param  list<string>  $termIds
     * @return list<DueTermView>
     */
    public function allInScope(UserId $userId, array $termIds, int $limit): array;
}
