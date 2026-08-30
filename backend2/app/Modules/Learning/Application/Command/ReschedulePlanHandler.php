<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Service\PlanDaysFromComputed;
use App\Modules\Learning\Domain\Exception\InvalidPlanOutline;
use App\Modules\Learning\Domain\Exception\PlanNotFound;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Service\PlanScheduler;
use App\Modules\Learning\Domain\ValueObject\PlanOutline;
use App\Modules\Learning\Domain\ValueObject\PlanOutlineDay;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;
use DateTimeImmutable;

/**
 * The learner adjusts the plan before committing: fewer minutes, a different date, one day fewer.
 * A1 runs again over the SAME model answer — no call, no money.
 *
 * That is the whole reason `outline` and `computed` are two columns. The model's answer explains
 * what the plan is; the arithmetic decides how it lands on a calendar; and the second changes
 * often while the first should not change at all. A design that stored one merged blob would have
 * to re-ask the model to move a slider.
 */
final readonly class ReschedulePlanHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private PlanScheduler $scheduler,
        private PlanDaysFromComputed $daysFromComputed,
        private TransactionManager $tx,
        private Clock $clock,
    ) {}

    public function __invoke(ReschedulePlan $command): void
    {
        $plan = $this->plans->findById($command->planId);
        if ($plan === null || ! $plan->userId()->equals($command->actorId)) {
            throw PlanNotFound::withId($command->planId->value);
        }

        $outline = $plan->parsedOutline();
        if ($outline === null) {
            throw InvalidPlanOutline::because(['у плана ещё нет каркаса — пересчитывать нечего']);
        }

        if ($command->dropDayIndex !== null) {
            $outline = $this->without($outline, $command->dropDayIndex);
        }

        $minutes = $command->minutesPerDay ?? $plan->minutesPerDay();
        $eventDate = $command->eventDate !== null
            ? new DateTimeImmutable($command->eventDate . ' 00:00:00')
            : $plan->eventDate();

        $computed = $this->scheduler->compute($outline, $minutes, $eventDate, $this->clock->now()->setTime(0, 0, 0));

        $this->tx->run(function () use ($plan, $computed, $minutes, $eventDate, $outline, $command): void {
            // Dropping a day edits the model's answer, so the stored outline moves with it —
            // otherwise the next reschedule would bring the dropped day back, and the plan the
            // learner is reading would not be the plan that is stored.
            if ($command->dropDayIndex !== null) {
                $plan->applyOutline($this->toArray($outline, $plan->outline() ?? []), $computed->toArray(), $outline);
            }
            $plan->reschedule($computed->toArray(), $minutes, $eventDate);
            $this->plans->save($plan);
            $this->days->replaceAll($plan->id(), $this->daysFromComputed->build($plan->id(), $computed));
        });
    }

    /** The outline minus one day, re-indexed so the remaining days stay 1..N and contiguous. */
    private function without(PlanOutline $outline, int $dayIndex): PlanOutline
    {
        $kept = [];
        foreach ($outline->days as $day) {
            if ($day->index === $dayIndex) {
                continue;
            }
            $kept[] = new PlanOutlineDay(
                index: count($kept) + 1,
                title: $day->title,
                termBudget: $day->termBudget,
                outcome: $day->outcome,
                role: $day->role,
                topics: $day->topics,
            );
        }

        if ($kept === []) {
            throw InvalidPlanOutline::because(['нельзя убрать последний день знакомства — от плана ничего не останется']);
        }

        return new PlanOutline(
            title: $outline->title,
            goalRestated: $outline->goalRestated,
            entities: $outline->entities,
            constraints: $outline->constraints,
            goalTerms: $outline->goalTerms,
            days: $kept,
            finalDayTitle: $outline->finalDayTitle,
        );
    }

    /**
     * The edited outline, back in the model's own JSON shape.
     *
     * Only the `days` array is rewritten; everything else is carried over from what the model
     * actually said, so the stored outline stays as close to «one model answer» as an edit allows.
     *
     * @param  array<string, mixed>  $original
     * @return array<string, mixed>
     */
    private function toArray(PlanOutline $outline, array $original): array
    {
        $days = [];
        foreach ($outline->days as $day) {
            $days[] = [
                'index' => $day->index,
                'title' => $day->title,
                'term_budget' => $day->termBudget,
                'outcome' => $day->outcome,
                'topics' => $day->topics,
                'role' => $day->role === null ? null : [
                    'name' => $day->role->name,
                    'opening_lines' => $day->role->openingLines,
                    'checkpoints' => $day->role->checkpoints,
                    'if_silent' => $day->role->ifSilent,
                ],
            ];
        }

        return [...$original, 'days' => $days];
    }
}
