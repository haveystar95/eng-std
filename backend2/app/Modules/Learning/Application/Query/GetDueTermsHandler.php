<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\DueTermsReader;
use App\Modules\Learning\Application\Port\ProgressExistenceReader;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Selection rules: due before new (a backlog of due cards means no new words that day),
 * new terms only fill the leftover session slots and never exceed the day's remaining
 * quota. Session size is capped so one call can't drown the user.
 *
 * "New" is derived, not stored: a term the user has in a collection but has never been SHOWN
 * (no progress row, or a row still at `acquisition = 'new'`) is a new card. We ask Collections
 * for the user's terms and subtract the ones Learning has already introduced.
 *
 * The acquisition ladder adds one wrinkle, and only one. A pair mid-ladder is returned by
 * `selectableAmong` even though it has no due date — it is unfinished, which outranks being due —
 * but a pair still at rung 0 is a pair the user has never seen, so it SPENDS THE DAILY QUOTA like
 * any other new word. Otherwise a `known` mark undone (which returns the row to rung 0) would
 * smuggle an unlimited number of first meetings past the norm.
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

        // Study only terms that are still in the user's (non-deleted) collections — scoped to
        // one collection or across all of them. Both due and new draw from this pool, so a
        // deleted collection's words drop out of the trainer even though their progress rows
        // (which are per-term, not per-collection) live on.
        $candidates = $query->collectionId !== null
            ? $this->collectionTerms->termIdsForCollection($query->userId, $query->collectionId, self::NEW_CANDIDATE_CAP)
            : $this->collectionTerms->termIdsForUser($query->userId, self::NEW_CANDIDATE_CAP);

        if ($candidates === []) {
            return [];
        }

        $selectable = $this->reader->selectableAmong($query->userId, $query->now, $candidates, $size);

        // Split the one query's result by what it costs. A row still at rung 0 is a first meeting
        // and is charged to the daily quota; everything else (mid-ladder, due, or a fresh graduate
        // owed its first review) is free.
        $quotaFree = [];
        $introductions = [];
        foreach ($selectable as $view) {
            if ($view->acquisition === Acquisition::New) {
                $introductions[] = $view;

                continue;
            }
            $quotaFree[] = $view;
        }

        $newLimit = min($size - count($quotaFree), max(0, $query->newTermsRemaining));
        if ($newLimit <= 0) {
            return $quotaFree;
        }

        $introductions = array_slice($introductions, 0, $newLimit);
        $selected = $quotaFree;
        foreach ($introductions as $view) {
            $selected[] = $view;
        }

        $remainingQuota = $newLimit - count($introductions);
        if ($remainingQuota <= 0) {
            return $selected;
        }

        $taken = [];
        foreach ($selected as $view) {
            $taken[$view->termId->value] = true;
        }

        return array_merge($selected, $this->newTerms($query->userId, $candidates, $remainingQuota, $taken));
    }

    /**
     * Terms with no progress row at all. They are new on BOTH dimensions — unscheduled, and
     * standing at rung 0 of the acquisition ladder — and no row is seeded for them here: a row
     * appears only when the pair is actually shown or answered.
     *
     * `$taken` excludes what the selectable query already returned. Without it the rows sitting at
     * rung 0 would come back twice: once as unfinished ladder rows, once as "not started", since
     * both questions answer yes for a pair that has a row but has never been shown.
     *
     * @param  non-empty-list<string>  $candidates
     * @param  array<string, true>  $taken
     * @return list<DueTermView>
     */
    private function newTerms(UserId $userId, array $candidates, int $limit, array $taken = []): array
    {
        $started = $this->progress->existingTermIds($userId, $candidates);

        $views = [];
        foreach ($candidates as $termId) {
            if (isset($started[$termId]) || isset($taken[$termId])) {
                continue;
            }
            $views[] = new DueTermView(
                TermId::fromString($termId), LearningState::New, 0, null,
                acquisition: Acquisition::New,
            );
            if (count($views) >= $limit) {
                break;
            }
        }

        return $views;
    }
}
