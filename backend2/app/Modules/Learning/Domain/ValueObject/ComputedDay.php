<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

use DateTimeImmutable;

/**
 * ONE DAY OF THE PLAN, after the server has done the arithmetic — and since v0.4 that day IS a
 * scene (канон §2).
 *
 * What used to be here and is not: `scenes[]`, a list, because a day could hold two halves of two
 * situations. A day holds ONE, so the scene's own fields sit on the day directly — its title, its
 * вводка, the lines the other person opens with, the names of the scenario — and the row
 * `learning_plan_days` gets is a snapshot of exactly what P2 will be handed.
 */
final readonly class ComputedDay
{
    /**
     * @param  list<PlanSkill>  $skills      the abilities this day teaches, in order. Empty on the
     *                                       final day.
     * @param  list<string>  $checkpoints    what has to be heard on this day, in the same order as
     *                                       `skills` — on the final day, every checkpoint of the
     *                                       whole plan, assembled by the server.
     * @param  list<string>  $openingLines   what the interlocutor of this scene actually says
     * @param  list<string>  $entities       proper names of the scenario — filler, never cards
     * @param  list<string>  $topics         the areas the day draws substitution words from
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
        /** Which SCENE this day is. Null on the final day, which is no scene at all. */
        public ?int $sourceSceneIndex,
        /**
         * THE ВВОДКА — 2–3 sentences of the support language, shown above the day and in the plan
         * preview, written once by P1 and never by the day ({@see PlanScene::$intro}).
         */
        public string $intro = '',
        public array $openingLines = [],
        public array $entities = [],
    ) {}

    /** @return list<string> */
    public function outcomes(): array
    {
        return array_map(static fn (PlanSkill $s): string => $s->outcome, $this->skills);
    }

    /**
     * THE SCENE, AS THE PROMPT READS IT — assembled by the server and never by a model.
     *
     * The same five keys {@see \App\Modules\Learning\Application\Dto\PlanDayGenerationBrief::sceneJson()}
     * hands over, built here so the SNAPSHOT stored with the day and the JSON the model is shown
     * are one thing rather than two that have to be kept in step.
     *
     * @return array<string, mixed>
     */
    public function sceneJson(): array
    {
        return [
            'title' => $this->title,
            'intro' => $this->intro,
            'skills' => array_map(
                static fn (PlanSkill $s): array => [
                    'id' => $s->id,
                    'outcome' => $s->outcome,
                    'checkpoint' => $s->checkpoint,
                    'topics' => $s->topics,
                ],
                $this->skills,
            ),
            'opening_lines' => $this->openingLines,
            'entities' => $this->entities,
        ];
    }
}
