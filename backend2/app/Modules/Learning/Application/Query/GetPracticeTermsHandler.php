<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\DueTermsReader;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use Random\Randomizer;

/**
 * Assembles the free-practice pool: take every term in scope (a collection, or all the user's
 * collections), attach each term's real progress so the {@see \App\Modules\Learning\Domain\Service\ExerciseSelector}
 * picks the right rung of the ladder (a never-studied term → reps 0 → multiple_choice), then
 * shuffle and cap to the session size. No due/new filtering, no quota — practice ignores the
 * schedule entirely and never introduces or spends anything.
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

        $candidates = $query->collectionId !== null
            ? $this->collectionTerms->termIdsForCollection($query->userId, $query->collectionId, self::CANDIDATE_CAP)
            : $this->collectionTerms->termIdsForUser($query->userId, self::CANDIDATE_CAP);

        if ($candidates === []) {
            return [];
        }

        // Real progress for the studied terms, keyed by term id; the rest are new (reps 0).
        $studied = [];
        foreach ($this->reader->allAmong($query->userId, $candidates, self::CANDIDATE_CAP) as $view) {
            $studied[$view->termId->value] = $view;
        }

        $views = [];
        foreach ($candidates as $termId) {
            // A term with no row is new on both dimensions: unscheduled, and standing at rung 0.
            $views[] = $studied[$termId] ?? new DueTermView(
                TermId::fromString($termId), LearningState::New, 0, null,
                acquisition: Acquisition::New,
            );
        }

        /** @var list<DueTermView> $shuffled */
        $shuffled = $this->rng->shuffleArray($views);

        return array_slice($shuffled, 0, $size);
    }
}
