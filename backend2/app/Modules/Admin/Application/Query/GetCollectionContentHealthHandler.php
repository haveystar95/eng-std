<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Dto\CollectionContentHealth;
use App\Modules\Admin\Application\Dto\ContentHealthTermRow;
use App\Modules\Admin\Application\Port\AdminContentHealthReader;
use App\Modules\Admin\Application\Service\ContentHealthAssessor;
use App\Modules\Admin\Application\Service\ContentTopUp;

/** One collection's terms with the same verdicts the summary counted, most under-stocked first. */
final readonly class GetCollectionContentHealthHandler
{
    public function __construct(
        private AdminContentHealthReader $content,
        private ContentHealthAssessor $assessor,
        private ContentTopUp $topUp,
    ) {}

    public function __invoke(GetCollectionContentHealth $query): ?CollectionContentHealth
    {
        $collection = $this->content->collection($query->collectionId);
        if ($collection === null) {
            return null;
        }

        $rows = array_map(
            fn ($facts): ContentHealthTermRow => $this->assessor->row($facts),
            $this->content->termFacts($collection->id),
        );
        $rows = $this->assessor->worstFirst($rows);

        $needs = count(array_filter($rows, static fn (ContentHealthTermRow $r): bool => $r->needsEnrichment));

        return new CollectionContentHealth(
            collectionId: $collection->id,
            title: $collection->title,
            type: $collection->type,
            terms: $rows,
            needsEnrichment: $needs,
            withoutExample: count(array_filter($rows, static fn (ContentHealthTermRow $r): bool => $r->missingExample)),
            pickCorrectReady: count(array_filter($rows, static fn (ContentHealthTermRow $r): bool => $r->pickCorrectReady)),
            estimatedTopUpUsd: $this->topUp->estimateUsd($needs),
            topUpCommand: $this->topUp->command([$collection->id]),
            minDistractors: ContentTopUp::MIN_DISTRACTORS,
            costPerTermUsd: ContentTopUp::COST_PER_TERM_USD,
        );
    }
}
