<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Dto\PlanDayGenerationBrief;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * The idempotency gate for a paid call.
 *
 * The claim and the read of the brief happen in ONE transaction, over a locked day row. Two
 * workers handed the same day — a replayed dispatch, a restart mid-job, a learner who tapped twice
 * — means one of them gets a brief and the other gets null and returns. Without the lock both would
 * read `pending`, both would write `generating`, and the plan would pay twice for one day.
 *
 * Returns null, never throws, for every «nothing to do» case: the day is already ready, somebody
 * else has it, both attempts are spent, it is the final day, the plan is not running. A job that
 * threw on those would fill the failed-jobs table with successful outcomes.
 */
final readonly class ClaimPlanDayHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private TransactionManager $tx,
    ) {}

    public function __invoke(ClaimPlanDay $command): ?PlanDayGenerationBrief
    {
        return $this->tx->run(function () use ($command): ?PlanDayGenerationBrief {
            $plan = $this->plans->findById(PlanId::fromString($command->planId));
            if ($plan === null || ! $plan->status()->generates()) {
                return null;
            }

            $day = $this->days->findByIndexForUpdate($plan->id(), $command->dayIndex);
            if ($day === null || ! $day->claim()) {
                return null;
            }
            $this->days->save($day);

            $outline = $plan->parsedOutline();
            if ($outline === null) {
                return null;
            }

            $brief = $day->roleBrief() ?? [];
            // The PLAN's language, not the account's — see LearningPlan::$supportLang.
            $support = $plan->supportLang();

            /** @var list<string> $checkpoints */
            $checkpoints = is_array($brief['checkpoints'] ?? null) ? array_values(array_filter(
                $brief['checkpoints'],
                static fn (mixed $c): bool => is_string($c),
            )) : [];

            $budget = is_int($brief['term_budget'] ?? null) ? $brief['term_budget'] : 0;
            // The day's split between lines and substitutions, computed HERE from the budget and
            // no longer frozen into the day row when the plan was scheduled. A day generated a
            // week after the plan was made has to be asked for the split its budget implies today.
            $phrases = (int) ceil(0.45 * $budget);

            return new PlanDayGenerationBrief(
                planId: $plan->id()->value,
                userId: $plan->userId()->value,
                planDayId: $day->id()->value,
                dayIndex: $day->dayIndex(),
                planTitle: $plan->title(),
                goalText: $plan->goalText(),
                dayTitle: $day->title(),
                supportLang: $support->value,
                targetLang: $plan->targetLang()->value,
                level: $plan->level()->value,
                termBudget: $budget,
                phraseCount: $phrases,
                wordCount: $budget - $phrases,
                checkpoints: $checkpoints,
                entities: $outline->entities,
                constraints: $outline->constraints,
                goalTerms: $outline->goalTerms,
                // What the prompt reads as «DAY (from the skeleton)». Built from the COMPUTED day,
                // not from the outline day: after A1 those can differ (a merged day, a dropped
                // ability), and the material has to be written for the day that actually exists.
                // Every OTHER day's checkpoints, for the coherence gate. Read here rather than in
                // Generation because the plan's days are Learning's rows and the claim already has
                // them open — and because a second module reading them would need its own opinion
                // about which day is «this» one.
                previousCheckpoints: $this->otherCheckpoints($plan->id(), $day->dayIndex()),
                // THE DAY AS THE PROMPT READS IT — four keys, assembled by the scheduler when the
                // plan was made ({@see ComputedDay::dayJson()}) and read back out of the day's own
                // snapshot. The checkpoints stand on the DAY and no longer inside the role: they
                // moved with v0.2, because a checkpoint belongs to the ability it proves, and a
                // day json that hid them under the interlocutor gave a scene with nobody to talk
                // to nothing to close.
                //
                // `term_budget` is deliberately not in here: it is a placeholder of its own,
                // because it is a fact about the learner's minutes rather than about the skeleton.
                dayJson: [
                    'index' => $day->dayIndex(),
                    'title' => $day->title(),
                    'scenes' => is_array($brief['scenes'] ?? null) ? $brief['scenes'] : [],
                    'checkpoints' => $checkpoints,
                ],
            );
        });
    }

    /**
     * Every checkpoint of every day of this plan EXCEPT the one being generated.
     *
     * The final day is skipped as well as the day itself: its checkpoints are the plan's own,
     * assembled by the server out of the teaching days, so counting them would make every day
     * collide with itself through the rehearsal.
     *
     * @return list<string>
     */
    private function otherCheckpoints(PlanId $planId, int $exceptDayIndex): array
    {
        $out = [];
        foreach ($this->days->listForPlan($planId) as $day) {
            if ($day->dayIndex() === $exceptDayIndex || $day->kind() === PlanDayKind::Final) {
                continue;
            }
            $brief = $day->roleBrief() ?? [];
            if (! is_array($brief['checkpoints'] ?? null)) {
                continue;
            }
            foreach ($brief['checkpoints'] as $checkpoint) {
                if (is_string($checkpoint) && $checkpoint !== '') {
                    $out[] = $checkpoint;
                }
            }
        }

        return $out;
    }
}
