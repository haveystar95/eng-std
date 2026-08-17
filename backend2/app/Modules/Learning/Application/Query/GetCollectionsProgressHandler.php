<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\CollectionProgressView;
use App\Modules\Learning\Application\Port\ProgressSnapshotReader;
use App\Modules\Learning\Domain\Service\Mastery;
use App\Modules\Learning\Domain\ValueObject\LearningState;

/**
 * Collection progress is derived, never stored: take each of the user's collections'
 * term ids (from Collections) and fold in the (user, term) progress snapshot (from
 * Learning). A term with no progress row is simply "not started".
 */
final readonly class GetCollectionsProgressHandler
{
    public function __construct(
        private UserCollectionTermsReader $collectionTerms,
        private ProgressSnapshotReader $progress,
    ) {}

    /** @return list<CollectionProgressView> */
    public function __invoke(GetCollectionsProgress $query): array
    {
        $byCollection = $this->collectionTerms->termIdsByCollection($query->userId);
        if ($byCollection === []) {
            return [];
        }

        $allTermIds = array_values(array_unique(array_merge(...array_values($byCollection))));
        $snapshots = $this->progress->forTerms($query->userId, $allTermIds);

        $views = [];
        foreach ($byCollection as $collectionId => $termIds) {
            $newCount = 0;
            $due = 0;
            $confirmed = 0;
            $familiar = 0;
            $inProgress = 0;
            foreach ($termIds as $termId) {
                $snapshot = $snapshots[$termId] ?? null;
                // No row, or a `new` row (returned from known), both mean "not started".
                if ($snapshot === null || $snapshot->state === LearningState::New) {
                    $newCount++;

                    continue;
                }

                if (Mastery::isMastered($snapshot->state, $snapshot->intervalDays)) {
                    // Breakdown of the one «усвоено»: self-marked known vs proven by exercises.
                    $snapshot->state === LearningState::Known ? $familiar++ : $confirmed++;
                } else {
                    $inProgress++;
                }

                if ($snapshot->dueAt !== null && $snapshot->dueAt <= $query->now) {
                    $due++;
                }
            }
            $views[] = new CollectionProgressView(
                collectionId: (string) $collectionId,
                total: count($termIds),
                newCount: $newCount,
                due: $due,
                mastered: $confirmed + $familiar,
                confirmed: $confirmed,
                familiar: $familiar,
                inProgress: $inProgress,
            );
        }

        return $views;
    }
}
