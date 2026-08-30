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
            $phrases = is_int($brief['phrase_count'] ?? null) ? $brief['phrase_count'] : (int) ceil(0.45 * $budget);

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
                dayJson: [
                    'index' => $day->dayIndex(),
                    'title' => $day->title(),
                    'term_budget' => $budget,
                    'outcome' => array_map(
                        static fn (array $s): mixed => $s['outcome'] ?? '',
                        $day->skills(),
                    ),
                    'role' => $brief['role'] ?? null,
                    'topics' => $brief['topics'] ?? [],
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
