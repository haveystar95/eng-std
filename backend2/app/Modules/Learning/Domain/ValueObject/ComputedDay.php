<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

use DateTimeImmutable;

/** One day of the plan, after the server has done the arithmetic. The row `learning_plan_days` gets. */
final readonly class ComputedDay
{
    /**
     * @param  list<PlanSkill>  $skills   the abilities this day teaches, in order. Empty on the final day.
     * @param  list<string>  $checkpoints what has to be heard on this day — on the final day, every
     *                                    checkpoint of the whole plan, assembled by the server.
     * @param  list<string>  $topics      the areas the day draws substitution words from
     */
    public function __construct(
        public int $index,
        public PlanDayKind $kind,
        public string $title,
        public DateTimeImmutable $scheduledOn,
        public int $termBudget,
        public array $skills,
        public array $checkpoints,
        public ?PlanRole $role,
        public array $topics,
        /** Which outline day this day's material came from, or null when it merges several. */
        public ?int $sourceDayIndex,
    ) {}

    /** @return list<string> */
    public function outcomes(): array
    {
        return array_map(static fn (PlanSkill $s): string => $s->outcome, $this->skills);
    }

    /**
     * How many REPLIES this day's material must contain: `ceil(0.45 × budget)`.
     *
     * A hard number handed to the model, not a band it is asked to hit. Two prompt iterations of
     * «сделай 40–50% реплик» produced 31.3% on a 16-term day; the same mechanic as an arithmetic
     * fact produced exactly 8 of 16 and 5 of 9 on the first run
     * (docs/research/plan-sandbox-2026-08-29.md §4). The formula and the canon's 40–50% band
     * disagree at odd budgets (9 → 5 → 55.6%), and the formula wins: it is the one that worked.
     * The validator's band is widened to 35–55% to match.
     */
    public function phraseCount(): int
    {
        return (int) ceil(0.45 * $this->termBudget);
    }

    public function wordCount(): int
    {
        return $this->termBudget - $this->phraseCount();
    }
}
