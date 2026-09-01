<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Learning\Application\Dto\PlanDayProgressView;
use App\Modules\Learning\Application\Dto\PlanDayTermView;
use App\Modules\Learning\Application\Service\PlanProgress;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\ValueObject\PlanId;

/**
 * THE DAY SCREEN'S REGISTER — «фразы дня» and «слова в этих фразах», each with its stage
 * (макет «Фаза 4», кадр 1c · 02).
 *
 * Two populations, and the screen draws them differently because they mean different things:
 *
 *  * THE DAY'S OWN terms — what this sitting introduces. Phrases first, in serif with a terracotta
 *    rule («то, что ты скажешь»), then the words those phrases are built from.
 *  * CARRIED terms — words met on an EARLIER day that have not lived out their three stages. They
 *    are what makes «B · со дня 1» true on screen, and they are the visible half of the rule that
 *    the plan's days are connected rather than independent lessons.
 *
 * A carried word that is FINISHED is left out entirely. It has nothing left to say on today's
 * screen, and listing it would grow the register by every word of every past day.
 *
 * The whole thing runs on {@see PlanProgress} — the same computation the plan session and the plan
 * screen use. That is deliberate and it is the reason this is a query handler rather than a couple
 * of joins: a register that named a stage the session then disagreed with is exactly the class of
 * bug PLAN-1b spent a наряд making impossible.
 */
final readonly class GetPlanDayTermsHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private PlanProgress $progress,
    ) {}

    /** @return list<PlanDayTermView>|null  null when the plan is not this learner's, or has no such day */
    public function __invoke(GetPlanDayTerms $query): ?array
    {
        $plan = $this->plans->findById(PlanId::fromString($query->planId));
        if ($plan === null || ! $plan->userId()->equals($query->actorId)) {
            return null;
        }

        $days = $this->days->listForPlan($plan->id());
        $progress = $this->progress->forPlan($plan, $days);

        $out = [];
        // The day's own words first, in the order the collection holds them, then everything still
        // in flight from the days before it — oldest day first, so the register reads as a history.
        foreach ($this->termsOf($progress->days[$query->dayIndex] ?? null, $query->dayIndex) as $term) {
            $out[] = $term;
        }
        foreach ($progress->days as $index => $day) {
            if ($index >= $query->dayIndex) {
                continue;
            }
            foreach ($this->termsOf($day, $index) as $term) {
                if (! $term->finished) {
                    $out[] = $term;
                }
            }
        }

        return $out;
    }

    /** @return list<PlanDayTermView> */
    private function termsOf(?PlanDayProgressView $day, int $index): array
    {
        if ($day === null) {
            return [];
        }

        $out = [];
        foreach ($day->termIds as $termId) {
            $standing = $day->standings[$termId] ?? null;
            $content = $day->content[$termId] ?? null;
            if ($standing === null || $content === null) {
                // A term whose content never arrived has no card and therefore no standing. The
                // session drops it for the same reason; showing it here would name a word the
                // trainer cannot deal.
                continue;
            }

            $out[] = new PlanDayTermView(
                termId: $termId,
                text: $content->text,
                translation: $content->translation,
                type: $content->type,
                kind: $content->kind,
                speaker: $content->speaker,
                stage: $standing->stage->value,
                stageComplete: $standing->stageComplete,
                finished: $standing->finished,
                fromDayIndex: $index,
            );
        }

        return $out;
    }
}
