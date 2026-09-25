<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\ValueObject\ConversationType;

/**
 * THE KNOBS OF THE TALK (наряд CONV-1; наряд FIX-3 §7) — turns, minutes and money.
 *
 * TURNS come from what the talk is FOR: the learner has as many moves as the talk has targets, and {@see SPARE_TURNS}
 * more — a greeting, a step aside, a question of their own (seven targets — nine moves). The role leads to the targets
 * one by one; a talk of three or four moves over seven targets asked for all seven and heard one (зал, день 2). The turn
 * limit is what the prompt is told as `TURNS_LEFT`: the role says goodbye on its own when it reaches nought.
 *
 * MINUTES are a hard stop, by kind of talk (`plan.conversation.minutes`: day 5, rehearsal 6, review 4 — config, tuned
 * after the phone): once the talk has taken them, the next move is the role's last, as with the money cap
 * (`ended_reason: limit`) — the learner is never cut off mid-word. The same minutes are the «около N минут» of the entry
 * card and the day window.
 *
 * The money cap is the plan's protection against a talk that will not end: the cap makes the NEXT move the role's last.
 *
 * The rollout switch that dealt a day WITHOUT the talk (`plan.conversation.enabled`, CONV-1) is gone with the column
 * that remembered it (наряд ACC-1 §3): the talk is simply part of every day.
 */
final readonly class ConversationRules
{
    /** The moves a talk has beyond one per target — a greeting, a step aside, a question of the learner's own. */
    public const SPARE_TURNS = 2;

    /**
     * THE MOVES A SCENE HAS beyond one per target of its own, in a talk over several scenes (наряд FIX-4 §4): the scene
     * closes when its targets are said or these moves are spent — 4 + 1 and 3 + 1 moves on the seven targets of a
     * rehearsal over two scenes, the talk's own 7 + 2.
     */
    public const SCENE_SPARE_TURNS = 1;

    /**
     * THE ROLE DOES NOT CLOSE THE TALK BEFORE ITS MOVES ARE SPENT (наряд FIX-3 §7: «„цели покрыты" концом не является»).
     * Both day talks of the live run were closed by the role with a move to go, once every target was said, on a line
     * that was no goodbye. An answer that ends the talk while moves are left is asked for once more with the reason; an
     * answer that insists — the learner said goodbye — ends it.
     */
    public const REDO_EARLY_END = 'early_end';

    /** An answer closed the talk with moves left and was asked for again. */
    public const CODE_EARLY_END = 'conversation.early_end';

    /** …and the answer asked for again closed it too: the talk ended there. */
    public const CODE_EARLY_END_KEPT = 'conversation.early_end_kept';

    /** The minutes a talk may take, by kind — the hard stop, and «около N минут» on the entry card and the day window. */
    public const MINUTES = ['day' => 5, 'rehearsal' => 6, 'review' => 4];

    /** What one talk may spend on the model and the voice together, in dollars. */
    public const COST_CAP_USD = 0.08;

    /** How long the silence is before the hint chip comes up by itself (кадр 37-7). */
    public const HINT_DELAY_MS = 5000;

    /**
     * HOW MANY TIMES A WALKED TALK MAY BE HELD AGAIN in one calendar day of the learner (наряд BACK-TAILS-2 §7) —
     * «Повторить разговор» of a passed day, each replay a model and a voice paid for. Past it the replay waits for the
     * learner's next midnight (409 `plan_conversation_replay_limit`).
     */
    public const REPLAYS_PER_DAY = 3;

    /**
     * @param  array<string, int>  $minutes  by {@see ConversationType} value
     */
    public function __construct(
        private array $minutes = self::MINUTES,
        public float $costCapUsd = self::COST_CAP_USD,
        public int $hintDelayMs = self::HINT_DELAY_MS,
        public int $replaysPerDay = self::REPLAYS_PER_DAY,
    ) {}

    /** The learner's moves in a talk over `$targets` targets — one each, and the spare ones. */
    public function turnsFor(int $targets): int
    {
        return max(1, $targets) + self::SPARE_TURNS;
    }

    /** The learner's moves in one scene of a talk over several, the scene having `$targets` targets of its own. */
    public function sceneTurnsFor(int $targets): int
    {
        return max(1, $targets) + self::SCENE_SPARE_TURNS;
    }

    /**
     * The learner's moves in a talk over several scenes: its scenes' moves together, so the last scene has the moves its
     * own targets give it (наряд FIX-4 §4) — on two scenes the talk's own `turnsFor()` (4 + 1 and 3 + 1 = 7 + 2), on
     * three one more.
     *
     * @param  list<int>  $targetsPerScene
     */
    public function turnsForScenes(array $targetsPerScene): int
    {
        return array_sum(array_map($this->sceneTurnsFor(...), $targetsPerScene));
    }

    public function minutesFor(ConversationType $type): int
    {
        return max(1, $this->minutes[$type->value] ?? self::MINUTES[$type->value]);
    }

    /** The seconds a talk of this kind may take — the day's «≈ N минут» for the stage, and the hard stop of the talk. */
    public function secondsFor(ConversationType $type): int
    {
        return $this->minutesFor($type) * 60;
    }
}
