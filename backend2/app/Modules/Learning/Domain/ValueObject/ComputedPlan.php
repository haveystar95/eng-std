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
        /**
         * Calendar days between one introduction day and the next: 1, 2 or 3.
         *
         * A NUMBER and no longer a two-valued «daily / every other day». The old enum could say
         * «через день» and nothing wider, so a plan with a month of room stacked its teaching into
         * the first week and then said nothing for three weeks. The step is
         * `clamp(floor(teaching_days / intro_days), 1, 3)` — spread the days over the room there
         * actually is, and stop at three, because a word met once and then left alone for four days
         * is a word met once.
         */
        public int $step,
        /**
         * WHY something was dropped, or null when nothing was.
         *
         * Two different facts wearing the same word. `deadline` — the event is too near, there are
         * not enough days. `cap` — there are plenty of days and the plan asked for more than
         * {@see \App\Modules\Learning\Domain\Service\PlanScheduler::MAX_INTRO_DAYS} of them,
         * which is a different sentence to put on the «срок мал» card and a different decision for
         * the learner (move the date vs. want less).
         */
        public ?string $dropReason,
        public bool $finalSameDay,
    ) {}

    /** Dropped because the plan wanted more introduction days than a plan is allowed to have. */
    public const DROP_CAP = 'cap';

    /** Dropped because the event is too near for what the plan asks. */
    public const DROP_DEADLINE = 'deadline';

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
            'step' => $this->step,
            'drop_reason' => $this->dropReason,
            'final_same_day' => $this->finalSameDay,
            'dropped_skills' => array_map(
                static fn (PlanSkill $s): array => [
                    'outcome' => $s->outcome,
                    'checkpoint' => $s->checkpoint,
                    'est_terms' => $s->estTerms,
                    'scene_index' => $s->sceneIndex,
                    'position' => $s->position,
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
                    'source_scene_index' => $d->sourceSceneIndex,
                ],
                $this->days,
            ),
        ];
    }
}
