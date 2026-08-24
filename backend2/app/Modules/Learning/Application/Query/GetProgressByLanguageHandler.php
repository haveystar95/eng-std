<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\LanguageProgressView;
use App\Modules\Learning\Application\Port\ProgressSnapshotReader;
use App\Modules\Learning\Domain\Service\Mastery;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\Service\LanguageRoles;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermLanguageReader;

/**
 * The language cut of `GET /study/progress` — «сколько усвоено в румынском» (DECISIONS п. 139).
 *
 * A SIBLING of {@see GetCollectionsProgressHandler} and not a rewrite of it: the per-collection
 * rows it returns are untouched and this rides beside them. Two folds rather than one because they
 * are two different questions about the same rows — a folder's own bar counts a word once per
 * folder, while «how much Romanian do I know» has to count a word ONCE no matter how many folders
 * hold it. Deriving one from the other would silently multiply a shared word by its folders.
 *
 * REFERENCE LANGUAGES ARE NOT HERE AT ALL. zh and ja carry no trainer (пп. 84, 136), so their terms
 * have no progress to report and a «0 из 40» row would invite a learner to fix a number that is not
 * broken. They are excluded, rather than shown empty, for the same reason the pool refuses them.
 */
final readonly class GetProgressByLanguageHandler
{
    public function __construct(
        private UserCollectionTermsReader $collectionTerms,
        private TermLanguageReader $termLangs,
        private ProgressSnapshotReader $progress,
    ) {}

    /** @return list<LanguageProgressView> */
    public function __invoke(GetProgressByLanguage $query): array
    {
        $byCollection = $this->collectionTerms->termIdsByCollection($query->userId);
        if ($byCollection === []) {
            return [];
        }

        // Distinct across folders: this is the whole difference from the per-collection fold.
        $termIds = array_values(array_unique(array_merge(...array_values($byCollection))));
        $langs = $this->termLangs->langsFor(array_map(TermId::fromString(...), $termIds));
        $snapshots = $this->progress->forTerms($query->userId, $termIds);

        /** @var array<string, array{total: int, new: int, due: int, confirmed: int, familiar: int, inProgress: int}> $buckets */
        $buckets = [];
        foreach ($termIds as $termId) {
            $lang = $langs[$termId] ?? null;
            if ($lang === null || LanguageRoles::isReference($lang)) {
                continue;
            }

            $buckets[$lang] ??= ['total' => 0, 'new' => 0, 'due' => 0, 'confirmed' => 0, 'familiar' => 0, 'inProgress' => 0];
            $buckets[$lang]['total']++;

            $snapshot = $snapshots[$termId] ?? null;
            // No row, or a `new` row (returned from known), both mean "not started".
            if ($snapshot === null || $snapshot->state === LearningState::New) {
                $buckets[$lang]['new']++;

                continue;
            }

            if (Mastery::isMastered($snapshot->state, $snapshot->intervalDays)) {
                // The same breakdown of the one «усвоено» the collection rows carry, not a second
                // definition of it (п. 27).
                $snapshot->state === LearningState::Known
                    ? $buckets[$lang]['familiar']++
                    : $buckets[$lang]['confirmed']++;
            } else {
                $buckets[$lang]['inProgress']++;
            }

            if ($snapshot->dueAt !== null && $snapshot->dueAt <= $query->now) {
                $buckets[$lang]['due']++;
            }
        }

        // Alphabetical by code: the caller has no meaningful order of its own, and a stable one
        // keeps a response diffable between runs.
        ksort($buckets);

        return array_map(
            static fn (array $b, string $lang): LanguageProgressView => new LanguageProgressView(
                lang: $lang,
                total: $b['total'],
                newCount: $b['new'],
                due: $b['due'],
                mastered: $b['confirmed'] + $b['familiar'],
                confirmed: $b['confirmed'],
                familiar: $b['familiar'],
                inProgress: $b['inProgress'],
            ),
            array_values($buckets),
            array_keys($buckets),
        );
    }
}
