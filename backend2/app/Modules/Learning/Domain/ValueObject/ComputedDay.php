<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

use DateTimeImmutable;

/** One day of the plan, after the server has done the arithmetic. The row `learning_plan_days` gets. */
final readonly class ComputedDay
{
    /**
     * @param  list<PlanSkill>  $skills   the abilities this day teaches, in order. Empty on the final day.
     * @param  list<string>  $checkpoints what has to be heard on this day, in the same order as
     *                                    `skills` — on the final day, every checkpoint of the whole
     *                                    plan, assembled by the server.
     * @param  list<array{title: string, role: array{name: string, opening_lines: list<array{text: string, translation: string}>, if_silent: string}|null, skills: list<array{outcome: string, checkpoint_index: int, topics: list<string>}>}>  $scenes
     *         The day AS P2 READS IT. One entry per scene that has abilities on this day — so a
     *         scene split across two days appears in both, carrying only the abilities that landed
     *         there, and two short scenes merged into one day appear as two entries rather than as
     *         a conversation with two people pretending to be one.
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
        /** Which SCENE this day's material came from, or null when it merges several. */
        public ?int $sourceSceneIndex,
        public array $scenes = [],
    ) {}

    /** @return list<string> */
    public function outcomes(): array
    {
        return array_map(static fn (PlanSkill $s): string => $s->outcome, $this->skills);
    }

    /**
     * THE DAY, AS THE PROMPT READS IT.
     *
     * Assembled by the server and never by a model, which is the same rule the rest of this file
     * is about. Four keys and no fifth: the scenes with their people and their abilities, and the
     * day's checkpoints numbered 1..N straight through in ability order.
     *
     * The NUMBERING is why `checkpoint_index` sits on the ability rather than being left for the
     * reader to count. P2 marks each line with the checkpoint it closes and the validator checks
     * that every index 1..N is closed by one — so the index has to mean the same thing on both
     * sides of that exchange, and «the position of this ability in this day» is a fact only the
     * server has. The day's own `term_budget` is NOT here: it is a separate placeholder, because
     * it is a fact about the learner's minutes and not about the skeleton.
     *
     * @return array<string, mixed>
     */
    public function dayJson(): array
    {
        return [
            'index' => $this->index,
            'title' => $this->title,
            'scenes' => $this->scenes,
            'checkpoints' => $this->checkpoints,
        ];
    }
}
