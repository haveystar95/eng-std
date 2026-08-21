<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\CachedLookup;
use App\Modules\Generation\Application\Dto\WordLookupResult;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The lookup cache: one paid answer per word, for everybody, forever.
 *
 * `find` is global on purpose — the answer is a fact about the word, not about who asked — and
 * `countPaidToday` is per user, because the daily cap is a spend guard on the person triggering the
 * calls. That asymmetry is the whole economics of the feature: the second learner to look a word up
 * pays nothing and spends none of their quota.
 */
interface SearchLookupCache
{
    public function find(string $normalizedQuery, string $lang, string $nativeLang): ?CachedLookup;

    public function findById(string $id): ?CachedLookup;

    /** How many lookups this user has PAID for since `$since` — cache hits are not among them. */
    public function countPaidSince(UserId $userId, \DateTimeImmutable $since): int;

    /**
     * Store a freshly bought answer and return it as a cached row.
     *
     * Must tolerate a concurrent writer having stored the same query first: two learners can look
     * the same new word up in the same second, and the loser of that race gets the winner's row
     * rather than an error.
     */
    public function store(
        UserId $payerId,
        string $normalizedQuery,
        string $lang,
        string $nativeLang,
        WordLookupResult $result,
    ): CachedLookup;
}
