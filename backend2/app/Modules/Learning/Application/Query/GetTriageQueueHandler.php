<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Collections\Application\Port\UserCollectionTermsReader;
use App\Modules\Learning\Application\Dto\TriageCardView;
use App\Modules\Learning\Application\Port\ProgressExistenceReader;
use App\Modules\Learning\Application\Port\TriagedTermsReader;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermContentReader;

/**
 * The triage queue is a collection's terms that are both never-studied (no progress row) and
 * never-triaged (no term_triages row), capped at `limit`. Excluding already-triaged terms is
 * what keeps a swipe from being asked twice, even though an "unknown" swipe leaves the term
 * new. Content is hydrated so the client can swipe the whole batch offline.
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

    /** @return list<TriageCardView> */
    public function __invoke(GetTriageQueue $query): array
    {
        $candidates = $this->collectionTerms->termIdsForCollection($query->userId, $query->collectionId, self::CANDIDATE_CAP);
        if ($candidates === []) {
            return [];
        }

        $studied = $this->progress->existingTermIds($query->userId, $candidates);
        $triaged = $this->triaged->triagedTermIds($query->userId, $candidates);

        $pending = [];
        foreach ($candidates as $termId) {
            if (isset($studied[$termId]) || isset($triaged[$termId])) {
                continue;
            }
            $pending[] = $termId;
            if (count($pending) >= max(1, $query->limit)) {
                break;
            }
        }

        if ($pending === []) {
            return [];
        }

        $content = $this->termContent->byIds(array_map(TermId::fromString(...), $pending));

        $cards = [];
        foreach ($pending as $termId) {
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

        return $cards;
    }
}
