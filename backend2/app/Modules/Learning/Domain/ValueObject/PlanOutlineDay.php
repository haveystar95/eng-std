<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/** One introduction day as P1 wrote it, before the server has had an opinion about the calendar. */
final readonly class PlanOutlineDay
{
    /**
     * @param  list<string>  $outcome  2–3 abilities, each a thing the learner can DO out loud
     * @param  list<string>  $topics   the AREAS the day's substitution words come from — never words
     */
    public function __construct(
        public int $index,
        public string $title,
        public int $termBudget,
        public array $outcome,
        public ?PlanRole $role,
        public array $topics,
    ) {}

    /**
     * The day's abilities, priced.
     *
     * The budget is shared out evenly and the REMAINDER goes to the first abilities, so the sum is
     * exactly `termBudget` and never a term more. Every ability gets at least one term: a day whose
     * budget is smaller than its outcome list is a bad day, but silently pricing an ability at zero
     * would make the scheduler believe it is free.
     *
     * @return list<PlanSkill>
     */
    public function skills(): array
    {
        $count = count($this->outcome);
        if ($count === 0) {
            return [];
        }

        $base = intdiv($this->termBudget, $count);
        $remainder = $this->termBudget % $count;

        $skills = [];
        foreach ($this->outcome as $i => $outcome) {
            $skills[] = new PlanSkill(
                outcome: $outcome,
                estTerms: max(1, $base + ($i < $remainder ? 1 : 0)),
                checkpoint: $this->role?->checkpoints[$i] ?? null,
                sourceDayIndex: $this->index,
            );
        }

        return $skills;
    }
}
