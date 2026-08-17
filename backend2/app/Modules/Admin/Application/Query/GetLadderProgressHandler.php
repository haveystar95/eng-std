<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Dto\AdminLadderPair;
use App\Modules\Admin\Application\Dto\AdminLadderPairFacts;
use App\Modules\Admin\Application\Dto\AdminLadderProgressView;
use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Port\AdminLadderReader;
use App\Modules\Learning\Application\Service\LadderStepResolver;

/**
 * Reads a learner's pairs and asks Learning what rung each one stands on.
 *
 * The rung is the only thing this handler adds, and it is the whole reason the handler exists: the
 * reporting reader may read Learning's TABLES (that is what a projection is) but it may not re-derive
 * Learning's RULES. Same shape as the day-plan simulator, which likewise borrows the real scheduler
 * instead of reimplementing it.
 */
final readonly class GetLadderProgressHandler
{
    public function __construct(
        private AdminLadderReader $reader,
        private LadderStepResolver $ladder,
    ) {}

    public function __invoke(GetLadderProgress $query): AdminLadderProgressView
    {
        $page = $this->reader->pairs($query->userId->value, $query->filter, $query->window);

        $items = array_map(fn (AdminLadderPairFacts $facts): AdminLadderPair => new AdminLadderPair(
            facts: $facts,
            step: $this->ladder->forStoredPair(
                acquisition: $facts->acquisition,
                reps: $facts->reps,
                learningStep: $facts->learningStep,
                isKnown: $facts->isKnown(),
            ),
        ), $page->items);

        return new AdminLadderProgressView(
            page: new Page(
                items: $items,
                total: $page->total,
                page: $page->page,
                perPage: $page->perPage,
                nextCursor: $page->nextCursor,
            ),
            counts: $this->reader->counts($query->userId->value, $query->filter->collectionId),
        );
    }
}
