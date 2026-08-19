<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\DueTermsReader;
use Random\Randomizer;

/**
 * Assembles the free-practice pool: every pair the learner has ENROLLED (optionally narrowed to one
 * collection), with its real progress attached so the {@see \App\Modules\Learning\Domain\Service\ExerciseSelector}
 * picks the right rung of the ladder, then shuffled and capped to the session size. No due/new
 * filtering, no quota — practice ignores the schedule entirely and never introduces or spends
 * anything.
 *
 * It is the POOL and not the collection, by the same rule the study session follows: practice is
 * training, and a word nobody has decided to study is not in training. Until this chapter the pool
 * here was «every term in the collection, with an invented rung-0 row for the ones never met», which
 * is exactly the behaviour the library/queue split exists to end — opening a 200-word collection
 * would drill 200 words the learner never asked for.
 */
final readonly class GetPracticeTermsHandler
{
    private const MAX_SESSION_SIZE = 100;
    private const CANDIDATE_CAP = 500;

    public function __construct(
        private DueTermsReader $reader,
        private UserCollectionTermsReader $collectionTerms,
        private Randomizer $rng,
    ) {}

    /** @return list<DueTermView> */
    public function __invoke(GetPracticeTerms $query): array
    {
        $size = max(1, min(self::MAX_SESSION_SIZE, $query->sessionSize));

        $scope = $query->collectionId !== null
            ? $this->collectionTerms->termIdsForCollection($query->userId, $query->collectionId, self::CANDIDATE_CAP)
            : null;

        $views = $this->reader->allInPool($query->userId, $scope, self::CANDIDATE_CAP);
        if ($views === []) {
            return [];
        }

        /** @var list<DueTermView> $shuffled */
        $shuffled = $this->rng->shuffleArray($views);

        return array_slice($shuffled, 0, $size);
    }
}
