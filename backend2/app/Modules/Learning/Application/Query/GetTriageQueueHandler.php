<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\TriageCardView;
use App\Modules\Learning\Application\Dto\TriageQueueView;
use App\Modules\Learning\Application\Port\ProgressExistenceReader;
use App\Modules\Learning\Application\Port\TriagedTermsReader;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermContentReader;

/**
 * The triage queue is a collection's terms that are both never-studied (no progress row) and
 * never-triaged (no term_triages row), capped at `limit`. Excluding already-triaged terms is
 * what keeps a swipe from being asked twice, even though an "unknown" swipe leaves the term
 * new. Content is hydrated so the client can swipe the whole batch offline.
 *
 * `remaining` counts the eligible terms left AFTER this page — the same exclusion the cards go
 * through — so it equals what subsequent GETs will serve, not "all new terms in the collection".
 * (Bounded by CANDIDATE_CAP like the page itself; a collection larger than the cap is not a real
 * case here and would simply under-report, consistently with the next GET.)
 */
final readonly class GetTriageQueueHandler
{
    private const CANDIDATE_CAP = 500;

    public function __construct(
        private UserCollectionTermsReader $collectionTerms,
        private ProgressExistenceReader $progress,
        private TriagedTermsReader $triaged,
        private TermContentReader $termContent,
    ) {}

    public function __invoke(GetTriageQueue $query): TriageQueueView
    {
        $candidates = $this->collectionTerms->termIdsForCollection($query->userId, $query->collectionId, self::CANDIDATE_CAP);
        if ($candidates === []) {
            return new TriageQueueView(cards: [], remaining: 0);
        }

        $studied = $this->progress->existingTermIds($query->userId, $candidates);
        $triaged = $this->triaged->triagedTermIds($query->userId, $candidates);

        // All eligible terms, in order — NOT stopped at `limit`, so `remaining` is exact.
        $eligible = [];
        foreach ($candidates as $termId) {
            if (! isset($studied[$termId]) && ! isset($triaged[$termId])) {
                $eligible[] = $termId;
            }
        }

        $limit = max(1, $query->limit);
        $page = array_slice($eligible, 0, $limit);
        $remaining = count($eligible) - count($page);

        if ($page === []) {
            return new TriageQueueView(cards: [], remaining: $remaining);
        }

        $content = $this->termContent->byIds(array_map(TermId::fromString(...), $page));

        $cards = [];
        foreach ($page as $termId) {
            $view = $content[$termId] ?? null;
            $cards[] = new TriageCardView(
                termId: $termId,
                text: $view?->text,
                type: $view?->type,
                transcription: $view?->transcription,
                translation: $view?->translation,
                example: $view?->example,
                exampleTranslation: $view?->exampleTranslation,
            );
        }

        return new TriageQueueView(cards: $cards, remaining: $remaining);
    }
}
