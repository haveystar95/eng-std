<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Learning\Application\Dto\PlanDayView;
use App\Modules\Learning\Application\Dto\PlanView;
use App\Modules\Learning\Application\Port\PlanReadinessReader;
use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\ValueObject\PlanId;

/**
 * The whole plan in one read — structure, days, arithmetic and readiness.
 *
 * Everything, deliberately. The screens land in 1c and a payload trimmed against imagined screens
 * is how an API needs a second version the week the real ones arrive.
 */
final readonly class GetPlanHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private PlanReadinessReader $readiness,
    ) {}

    public function __invoke(GetPlan $query): ?PlanView
    {
        $plan = $query->planId !== null
            ? $this->plans->findById(PlanId::fromString($query->planId))
            : $this->plans->findActiveFor($query->actorId);

        if ($plan === null || ! $plan->userId()->equals($query->actorId)) {
            return null;
        }

        return $this->view($plan);
    }

    private function view(LearningPlan $plan): PlanView
    {
        $outline = $plan->parsedOutline();
        // The PLAN's language, not the account's — see LearningPlan::$supportLang.
        $support = $plan->supportLang();

        $days = array_map(
            fn (PlanDay $day): PlanDayView => $this->dayView($day),
            $this->days->listForPlan($plan->id()),
        );

        return new PlanView(
            id: $plan->id()->value,
            status: $plan->status()->value,
            title: $plan->title(),
            goalText: $plan->goalText(),
            goalRestated: $plan->goalRestated(),
            supportLang: $support->value,
            targetLang: $plan->targetLang()->value,
            level: $plan->level()->value,
            eventDate: $plan->eventDate()->format('Y-m-d'),
            minutesPerDay: $plan->minutesPerDay(),
            startedAt: $plan->startedAt()?->format(DATE_ATOM),
            completedAt: $plan->completedAt()?->format(DATE_ATOM),
            days: $days,
            computed: $plan->computed(),
            entities: $outline === null ? [] : $outline->entities,
            constraints: $outline === null ? [] : $outline->constraints,
            goalTerms: $outline === null ? [] : $outline->goalTerms,
            readiness: $this->readinessOf($plan, $days),
        );
    }

    private function dayView(PlanDay $day): PlanDayView
    {
        $brief = $day->roleBrief() ?? [];

        /** @var list<string> $checkpoints */
        $checkpoints = is_array($brief['checkpoints'] ?? null)
            ? array_values(array_filter($brief['checkpoints'], static fn (mixed $c): bool => is_string($c)))
            : [];
        /** @var list<string> $topics */
        $topics = is_array($brief['topics'] ?? null)
            ? array_values(array_filter($brief['topics'], static fn (mixed $t): bool => is_string($t)))
            : [];

        $outcomes = [];
        foreach ($day->skills() as $skill) {
            $outcome = $skill['outcome'] ?? null;
            if (is_string($outcome) && $outcome !== '') {
                $outcomes[] = $outcome;
            }
        }

        return new PlanDayView(
            id: $day->id()->value,
            index: $day->dayIndex(),
            kind: $day->kind()->value,
            title: $day->title(),
            scheduledOn: $day->scheduledOn()?->format('Y-m-d'),
            collectionId: $day->collectionId()?->value,
            status: $day->status()->value,
            generationAttempts: $day->generationAttempts(),
            failReason: $day->failReason(),
            termBudget: is_int($brief['term_budget'] ?? null) ? $brief['term_budget'] : 0,
            outcomes: $outcomes,
            checkpoints: $checkpoints,
            topics: $topics,
            role: is_array($brief['role'] ?? null) ? $brief['role'] : null,
        );
    }

    /**
     * READINESS, v1a — and it is deliberately a small number that cannot lie upwards.
     *
     * The full formula has two halves: how much of the material the learner has actually acquired,
     * and how many of the plan's checkpoints they have hit in conversation. The second half needs
     * the conversation, which is CONV-1, so today it contributes a literal ZERO — not an estimate,
     * not a proxy. The first half is capped at 0.4, which is the weight it carries in the full
     * formula, so a learner who has genuinely learned every word of every day reads as 0.4 and not
     * as «готов».
     *
     * «Ступень C» is the acquisition ladder's top rung and it lands in 1b; until then the reader
     * answers with the terms this learner has enrolled from the plan and graduated. The number
     * therefore only ever grows when the ladder ships — it never has to be revised downwards, which
     * is the property that makes shipping half a formula safe.
     *
     * @param  list<PlanDayView>  $days
     */
    private function readinessOf(LearningPlan $plan, array $days): float
    {
        $collectionIds = [];
        foreach ($days as $day) {
            if ($day->collectionId !== null) {
                $collectionIds[] = $day->collectionId;
            }
        }

        if ($collectionIds === []) {
            return 0.0;
        }

        $share = $this->readiness->acquiredShare($plan->userId(), $collectionIds);

        return round(0.4 * $share, 4);
    }
}
