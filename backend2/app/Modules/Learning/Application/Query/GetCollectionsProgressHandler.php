<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\CollectionProgressView;
use App\Modules\Learning\Application\Port\ProgressSnapshotReader;
use App\Modules\Learning\Domain\Service\Mastery;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\Service\LanguageRoles;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermLanguageReader;

/**
 * Collection progress is derived, never stored: take each of the user's collections'
 * term ids (from Collections) and fold in the (user, term) progress snapshot (from
 * Learning). A term with no progress row is simply "not started".
 *
 * A REFERENCE-LANGUAGE TERM IS NOT COUNTED (DECISIONS пп. 84, 136): zh and ja carry no trainer, so
 * such a word can never be new, due or mastered — it can only be read. A phrasebook collection
 * therefore drops out of this list entirely rather than reporting «0 из 40», which would read as
 * work not yet done instead of work that does not exist.
 */
final readonly class GetCollectionsProgressHandler
{
    public function __construct(
        private UserCollectionTermsReader $collectionTerms,
        private TermLanguageReader $termLangs,
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
        $langs = $this->termLangs->langsFor(array_map(TermId::fromString(...), $allTermIds));
        $snapshots = $this->progress->forTerms($query->userId, $allTermIds);

        $views = [];
        foreach ($byCollection as $collectionId => $termIds) {
            $total = 0;
            $newCount = 0;
            $due = 0;
            $confirmed = 0;
            $familiar = 0;
            $inProgress = 0;
            foreach ($termIds as $termId) {
                $lang = $langs[$termId] ?? null;
                if ($lang !== null && LanguageRoles::isReference($lang)) {
                    continue;
                }
                $total++;
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
            if ($total === 0 && $termIds !== []) {
                // Every word in it is reference-only: a phrasebook, and there is no progress to
                // report. An empty collection still reports its honest zero, as it always did.
                continue;
            }
            $views[] = new CollectionProgressView(
                collectionId: (string) $collectionId,
                total: $total,
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
