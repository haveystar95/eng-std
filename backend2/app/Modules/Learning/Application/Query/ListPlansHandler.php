<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Learning\Application\Dto\PlanSummaryView;
use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;

/**
 * The plans this learner has run — the completed one at the top of the План tab and the archive
 * under it (кадр 1c · 11).
 *
 * «Завершённый план не исчезает»: a finished preparation is a thing that happened, and the tab is
 * where it goes on being visible. `/plans/active` deliberately cannot serve this — it answers «what
 * am I on now» and a completed plan is the answer to a different question.
 */
final readonly class ListPlansHandler
{
    private const MAX = 50;

    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
    ) {}

    /** @return list<PlanSummaryView> */
    public function __invoke(ListPlans $query): array
    {
        $limit = max(1, min(self::MAX, $query->limit));

        return array_map(
            fn (LearningPlan $plan): PlanSummaryView => new PlanSummaryView(
                id: $plan->id()->value,
                status: $plan->status()->value,
                title: $plan->title(),
                targetLang: $plan->targetLang()->value,
                eventDate: $plan->eventDate()?->format('Y-m-d'),
                dayCount: count($this->days->listForPlan($plan->id())),
                startedAt: $plan->startedAt()?->format(DATE_ATOM),
                completedAt: $plan->completedAt()?->format(DATE_ATOM),
            ),
            $this->plans->listFor($query->actorId, $limit),
        );
    }
}
