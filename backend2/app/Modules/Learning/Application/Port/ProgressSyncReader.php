<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Application\Dto\PooledTermRef;
use App\Modules\Learning\Application\Dto\ProgressSyncRow;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * The user's (user, term) progress rows changed in (since, upper]; `since` null = all of them
 * (full snapshot). Ordered by (updated_at, term_id) for deterministic offset paging.
 */
interface ProgressSyncReader
{
    /** @return list<ProgressSyncRow> */
    public function changedProgress(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array;

    /**
     * Every term in the learner's POOL — the ids the sync feed must keep in scope no matter what
     * happened to the collections the words came from.
     *
     * The pool is the queue and a collection is a catalogue: a word stays in the trainer when its
     * folder is deleted, and it can enter the pool with no folder at all («учить это слово» from
     * search). Scoped by collections alone, the feed stopped shipping such a word's CONTENT while
     * still shipping its progress — so the phone held a queued pair it could not render, the word
     * vanished from «Мои слова», and the next full snapshot reaped it outright while the server went
     * on dealing it in sessions. That is the shape of the bug this exists to close.
     *
     * @return list<string>
     */
    public function pooledTermIds(UserId $userId): array;

    /**
     * Terms that ENTERED the pool in `(since, upper]`, with their own `updated_at`.
     *
     * Enrolment writes `user_term_progress.enrolled_at` and touches the term not at all, so a word
     * taken into study long after it was created has an old timestamp and `changedTermIds` alone
     * would skip it — the delta would carry the progress row and no content. Exactly the gap
     * {@see \App\Modules\Collections\Application\Port\CollectionSyncReader::newlySubscribedTermRefs()}
     * closes for a fresh subscription, closed the same way.
     *
     * Empty for a full snapshot, which already ships every scoped term.
     *
     * @return list<PooledTermRef>
     */
    public function newlyEnrolledTermRefs(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array;
}
