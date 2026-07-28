<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\DueTermsReader;
use App\Modules\Learning\Application\Port\ProgressExistenceReader;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Selection rules: due before new (a backlog of due cards means no new words that day),
 * new terms only fill the leftover session slots and never exceed the day's remaining
 * quota. Session size is capped so one call can't drown the user.
 *
 * "New" is derived, not stored: a term the user has in a collection but has never answered
 * (no progress row) is a new card. We ask Collections for the user's terms and subtract the
 * ones Learning already tracks — no `state='new'` rows are ever seeded.
 */
final readonly class GetDueTermsHandler
{
    private const MAX_SESSION_SIZE = 100;
    private const NEW_CANDIDATE_CAP = 500;

    public function __construct(
        private DueTermsReader $reader,
        private UserCollectionTermsReader $collectionTerms,
        private ProgressExistenceReader $progress,
    ) {}

    /** @return list<DueTermView> */
    public function __invoke(GetDueTerms $query): array
    {
        $size = max(1, min(self::MAX_SESSION_SIZE, $query->sessionSize));

        $due = $this->reader->due($query->userId, $query->now, $size);

        $remaining = $size - count($due);
        $newLimit = min($remaining, max(0, $query->newTermsRemaining));
        if ($newLimit <= 0) {
            return $due;
        }

        return array_merge($due, $this->newTerms($query->userId, $newLimit));
    }

    /** @return list<DueTermView> */
    private function newTerms(UserId $userId, int $limit): array
    {
        $candidates = $this->collectionTerms->termIdsForUser($userId, self::NEW_CANDIDATE_CAP);
        if ($candidates === []) {
            return [];
        }

        $started = $this->progress->existingTermIds($userId, $candidates);

        $views = [];
        foreach ($candidates as $termId) {
            if (isset($started[$termId])) {
                continue;
            }
            $views[] = new DueTermView(TermId::fromString($termId), LearningState::New, 0, null);
            if (count($views) >= $limit) {
                break;
            }
        }

        return $views;
    }
}
