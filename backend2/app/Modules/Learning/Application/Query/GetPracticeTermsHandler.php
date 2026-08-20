<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\DueTermsReader;
use App\Modules\Shared\Domain\ValueObject\TermId;
use Random\Randomizer;

/**
 * Assembles the free-practice pool, with its real progress attached so the card assembler deals the
 * right shape, then shuffled and capped to the session size. No due/new filtering, no quota —
 * practice ignores the schedule entirely and never introduces or spends anything.
 *
 * WHAT IT DRAWS FROM depends on the scope, and the two answers are different on purpose:
 *
 *  * NO COLLECTION («свободная тренировка» over everything) — the POOL. There is no other honest
 *    answer: «everything the learner has ever had access to» is a catalogue of thousands, and a
 *    drill over it would be a random-word generator.
 *  * ONE COLLECTION («Тренировка по теме») — THE WHOLE COLLECTION, untriaged words included. The
 *    scope is the learner's own act: they opened this topic and asked to drill it. «Зашёл в кафе,
 *    открыл тему, прошёл маленькую тренировку без разбора коллекции» — which the pool-only rule
 *    made impossible, since an untriaged collection produced an empty session.
 *
 * Reaching a word outside the pool costs it nothing: practice enrols nothing, writes no exposure,
 * schedules nothing and resolves no verification, so the word is still outside the pool when the
 * session ends. What it does change is the CARD — see {@see \App\Modules\Learning\Domain\Service\LearningLadder::STEP_UNENROLLED_PRACTICE}
 * and {@see \App\Modules\Learning\Application\Service\StudyCardAssembler} — and the ORDER: the words
 * being studied lead the session, the catalogue fills the tail, so a session too small to hold
 * everything is spent on the queue first.
 *
 * STUDY selection is untouched by all of this: {@see GetDueTermsHandler} still reads the pool alone.
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

        if ($query->collectionId === null) {
            /** @var list<DueTermView> $shuffled */
            $shuffled = $this->rng->shuffleArray($this->reader->allInPool($query->userId, null, self::CANDIDATE_CAP));

            return array_slice($shuffled, 0, $size);
        }

        $scope = $this->collectionTerms->termIdsForCollection($query->userId, $query->collectionId, self::CANDIDATE_CAP);
        if ($scope === []) {
            return [];
        }

        [$inPool, $catalogue] = $this->split($query, $scope);
        if ($inPool === [] && $catalogue === []) {
            return [];
        }

        /** @var list<DueTermView> $studied */
        $studied = $this->rng->shuffleArray($inPool);
        /** @var list<DueTermView> $rest */
        $rest = $this->rng->shuffleArray($catalogue);

        // Each half shuffled on its own, so a repeat run varies WITHIN the halves without mixing
        // them — the pool never falls behind the catalogue in a session it could have filled.
        return array_slice([...$studied, ...$rest], 0, $size);
    }

    /**
     * The collection's terms split into «being studied» and «only in the catalogue».
     *
     * A term with no progress row is the ordinary catalogue case and gets {@see DueTermView::outOfPool()};
     * a term WITH a row that is not enrolled is a PAUSED word, and it is catalogue too — «Убрать из
     * изучения» took it out of the queue, and a drill over the topic must not quietly put it back.
     *
     * @param  list<string>  $scope
     * @return array{list<DueTermView>, list<DueTermView>}
     */
    private function split(GetPracticeTerms $query, array $scope): array
    {
        $inPool = [];
        $catalogue = [];
        $known = [];

        foreach ($this->reader->allInScope($query->userId, $scope, self::CANDIDATE_CAP) as $view) {
            $known[$view->termId->value] = true;
            if ($view->inPool) {
                $inPool[] = $view;
            } else {
                $catalogue[] = $view;
            }
        }

        foreach ($scope as $termId) {
            if (! isset($known[$termId])) {
                $catalogue[] = DueTermView::outOfPool(TermId::fromString($termId));
            }
        }

        return [$inPool, $catalogue];
    }
}
