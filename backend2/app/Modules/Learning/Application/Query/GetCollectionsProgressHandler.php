<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\CollectionProgressView;
use App\Modules\Learning\Application\Port\ProgressSnapshotReader;
use App\Modules\Learning\Domain\ValueObject\LearningState;

/**
 * Collection progress is derived, never stored: take each of the user's collections'
 * term ids (from Collections) and fold in the (user, term) progress snapshot (from
 * Learning). A term with no progress row is simply "not started".
 */
final readonly class GetCollectionsProgressHandler
{
    private const MASTERED_INTERVAL_DAYS = 21;

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
            $learned = 0;
            $mastered = 0;
            $due = 0;
            foreach ($termIds as $termId) {
                $snapshot = $snapshots[$termId] ?? null;
                if ($snapshot === null) {
                    continue;
                }
                if ($snapshot->state === LearningState::Review) {
                    $learned++;
                    if ($snapshot->intervalDays >= self::MASTERED_INTERVAL_DAYS) {
                        $mastered++;
                    }
                }
                if ($snapshot->state !== LearningState::New && $snapshot->dueAt !== null && $snapshot->dueAt <= $query->now) {
                    $due++;
                }
            }
            $views[] = new CollectionProgressView(
                collectionId: (string) $collectionId,
                total: count($termIds),
                learned: $learned,
                mastered: $mastered,
                due: $due,
            );
        }

        return $views;
    }
}
