<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * The SERVER's answer about the calendar — what goes into `learning_plans.computed`.
 *
 * Every number here was computed from the outline and the two facts the model never sees (the event
 * date and today). The model proposes days; this decides them. That split is the point of the whole
 * design: an LLM asked to count days off a calendar will produce a plausible number, and a plausible
 * number is exactly what a deadline cannot use.
 */
final readonly class ComputedPlan
{
    /**
     * @param  list<ComputedDay>  $days      every day of the plan, intro days then the final one
     * @param  list<PlanSkill>  $dropped     abilities that did NOT fit and are not taught. Never
     *                                       empty silently: the «срок мал» card is built from this.
     */
    public function __construct(
        public array $days,
        public int $need,
        public int $capacity,
        public int $maxDays,
        public int $introDays,
        public int $restDays,
        public bool $fits,
        public array $dropped,
        public DaySpacing $spacing,
        public bool $finalSameDay,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'need' => $this->need,
            'capacity' => $this->capacity,
            'max_days' => $this->maxDays,
            'intro_days' => $this->introDays,
            'rest_days' => $this->restDays,
            'fits' => $this->fits,
            'spacing' => $this->spacing->value,
            'final_same_day' => $this->finalSameDay,
            'dropped_skills' => array_map(
                static fn (PlanSkill $s): array => [
                    'outcome' => $s->outcome,
                    'est_terms' => $s->estTerms,
                    'source_day_index' => $s->sourceDayIndex,
                ],
                $this->dropped,
            ),
            'days' => array_map(
                static fn (ComputedDay $d): array => [
                    'index' => $d->index,
                    'kind' => $d->kind->value,
                    'title' => $d->title,
                    'scheduled_on' => $d->scheduledOn->format('Y-m-d'),
                    'term_budget' => $d->termBudget,
                    'phrase_count' => $d->phraseCount(),
                    'word_count' => $d->wordCount(),
                    'outcome' => $d->outcomes(),
                    'checkpoints' => $d->checkpoints,
                    'topics' => $d->topics,
                    'source_day_index' => $d->sourceDayIndex,
                ],
                $this->days,
            ),
        ];
    }
}
