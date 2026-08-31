<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Service\PlanDaysFromComputed;
use App\Modules\Learning\Application\Service\PlanSkillsFromComputed;
use App\Modules\Learning\Domain\Exception\InvalidPlanOutline;
use App\Modules\Learning\Domain\Exception\PlanNotFound;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanSkillRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Service\PlanScheduler;
use App\Modules\Learning\Domain\ValueObject\PlanOutline;
use App\Modules\Learning\Domain\ValueObject\PlanScene;
use App\Modules\Learning\Domain\ValueObject\PlanSkill;
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
        private PlanSkillRepository $skills,
        private PlanScheduler $scheduler,
        private PlanDaysFromComputed $daysFromComputed,
        private PlanSkillsFromComputed $skillsFromComputed,
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

        $computed = $this->scheduler->compute(
            $outline,
            $minutes,
            $eventDate,
            $this->clock->now()->setTime(0, 0, 0),
            $plan->supportLang()->value,
        );

        $this->tx->run(function () use ($plan, $computed, $minutes, $eventDate, $outline, $command): void {
            // Dropping a day edits the model's answer, so the stored outline moves with it —
            // otherwise the next reschedule would bring the dropped day back, and the plan the
            // learner is reading would not be the plan that is stored.
            if ($command->dropDayIndex !== null) {
                $plan->applyOutline($this->toArray($outline, $plan->outline() ?? []), $computed->toArray(), $outline);
            }
            $plan->reschedule($computed->toArray(), $minutes, $eventDate);
            $this->plans->save($plan);
            // A7 rewrites BOTH: the abilities get their new `day_index` and their new verdict about
            // whether the deadline still holds them, and the days are rebuilt from that.
            $this->skills->replaceAll($plan->id(), $this->skillsFromComputed->build($outline, $computed));
            $this->days->replaceAll($plan->id(), $this->daysFromComputed->build($plan->id(), $computed));
        });
    }

    /**
     * The outline minus one SCENE, re-indexed so the remaining scenes stay 1..N and contiguous.
     *
     * The command still calls it a «day» because that is what the learner taps, and until v0.2 the
     * two were the same thing: P1 answered in days and the scheduler kept them. Now a day is
     * assembled from scenes and a scene is what can actually be removed — dropping «HR-звонок»
     * removes an ability the plan promised, which is a decision, whereas dropping «day 2» would
     * remove whatever the packer happened to put there this morning.
     */
    private function without(PlanOutline $outline, int $sceneIndex): PlanOutline
    {
        $kept = [];
        $position = 0;
        foreach ($outline->scenes as $scene) {
            if ($scene->index === $sceneIndex) {
                continue;
            }

            $index = count($kept) + 1;
            $skills = [];
            foreach ($scene->skills as $skill) {
                $skills[] = new PlanSkill(
                    outcome: $skill->outcome,
                    checkpoint: $skill->checkpoint,
                    estTerms: $skill->estTerms,
                    sceneIndex: $index,
                    skillIndex: count($skills),
                    position: $position++,
                    topics: $skill->topics,
                );
            }

            $kept[] = new PlanScene(
                index: $index,
                title: $scene->title,
                role: $scene->role,
                skills: $skills,
            );
        }

        if ($kept === []) {
            throw InvalidPlanOutline::because(['нельзя убрать последнюю сцену — от плана ничего не останется']);
        }

        return new PlanOutline(
            title: $outline->title,
            goalRestated: $outline->goalRestated,
            entities: $outline->entities,
            constraints: $outline->constraints,
            goalTerms: $outline->goalTerms,
            scenes: $kept,
        );
    }

    /**
     * The edited outline, back in the model's own JSON shape.
     *
     * Only the `scenes` array is rewritten; everything else is carried over from what the model
     * actually said, so the stored outline stays as close to «one model answer» as an edit allows.
     *
     * @param  array<string, mixed>  $original
     * @return array<string, mixed>
     */
    private function toArray(PlanOutline $outline, array $original): array
    {
        $scenes = [];
        foreach ($outline->scenes as $scene) {
            $skills = [];
            foreach ($scene->skills as $skill) {
                $skills[] = [
                    'outcome' => $skill->outcome,
                    'checkpoint' => $skill->checkpoint,
                    'est_terms' => $skill->estTerms,
                    'topics' => $skill->topics,
                ];
            }

            $scenes[] = [
                'title' => $scene->title,
                'role' => $scene->role === null ? null : [
                    'name' => $scene->role->name,
                    'opening_lines' => $scene->role->openingLines,
                    'if_silent' => $scene->role->ifSilent,
                ],
                'skills' => $skills,
            ];
        }

        return [...$original, 'scenes' => $scenes];
    }
}
