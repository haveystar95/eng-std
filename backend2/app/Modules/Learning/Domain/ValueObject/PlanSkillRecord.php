<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * ONE ROW OF `plan_skills` — an ability of the plan, with the scheduler's answer about it.
 *
 * {@see PlanSkill} is what the model said; this is what the plan STORED. The two are separate
 * because the second carries facts the first cannot know and that change over a plan's life: which
 * day this ability landed on, and whether it was dropped because the deadline could not hold it.
 * A7 recomputes both whenever the learner moves the date or falls behind, and rewrites these rows —
 * the days are then rebuilt from them, which is the direction the arrow points since v0.2.
 */
final readonly class PlanSkillRecord
{
    /**
     * @param  array{name: string, opening_lines: list<array{text: string, translation: string}>, if_silent: string}|null  $role
     *         the scene's interlocutor, repeated on every row of the scene — a scene is not a table
     *         and the role is never wanted apart from the abilities it is the interlocutor for
     * @param  list<string>  $topics
     */
    public function __construct(
        public string $id,
        public int $sceneIndex,
        public string $sceneTitle,
        public ?array $role,
        public int $skillIndex,
        public string $outcome,
        public string $checkpoint,
        public int $estTerms,
        public array $topics,
        /** P1's order across the whole plan, 0-based — and therefore the priority the tail is cut from. */
        public int $position,
        /** Which day it landed on, or null when it landed on none (dropped, or not yet scheduled). */
        public ?int $dayIndex,
        public bool $dropped,
    ) {}
}
