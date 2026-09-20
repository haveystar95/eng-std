<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\ValueObject\ConversationType;

/**
 * THE KNOBS OF THE TALK (наряд CONV-1) — turns, minutes and money, by what kind of talk it is.
 *
 * All three are config (`plan.conversation`), not constants, for the same reason the day's pace is:
 * they are tuned after the phone, and a number tuned in code is a number nobody can tune back
 * without a release. The turn limit is what the prompt is told as `TURNS_LEFT` — the role says
 * goodbye on its own when it reaches nought, which is why it is «после N ходов прощается сам» and
 * not «сервер обрывает».
 *
 * The money cap is the plan's protection against a talk that will not end: the learner is never cut
 * off mid-word — the cap makes the NEXT move the role's last (`ended_reason: limit`).
 *
 * `enabled` is not a knob of the talk but the switch that deals it at all — see the constant.
 */
final readonly class ConversationRules
{
    /** Moves of the scene per kind of talk — «day 4, rehearsal 10, review 4». */
    public const TURNS = ['day' => 4, 'rehearsal' => 10, 'review' => 4];

    /** «Около N минут» on the entry card and in the day window (кадры 37-1, 37-2, 37-5). */
    public const MINUTES = ['day' => 3, 'rehearsal' => 6, 'review' => 6];

    /** What one talk may spend on the model and the voice together, in dollars. */
    public const COST_CAP_USD = 0.08;

    /** How long the silence is before the hint chip comes up by itself (кадр 37-7). */
    public const HINT_DELAY_MS = 5000;

    /**
     * IS THE SIXTH STAGE DEALT AT ALL — the rollout switch, not a rule of the talk (`plan.conversation.enabled`).
     *
     * Off, a day is dealt the five stages of before the talk existed and walks them to the end; the
     * days already dealt WITH the talk keep it, because a day's composition is fixed when it opens
     * and nothing re-deals it. On is what the code does by itself — the switch exists so the server
     * may ship before the client that speaks.
     */
    public const ENABLED = true;

    /**
     * @param  array<string, int>  $turns  by {@see ConversationType} value
     * @param  array<string, int>  $minutes  by {@see ConversationType} value
     */
    public function __construct(
        private array $turns = self::TURNS,
        private array $minutes = self::MINUTES,
        public float $costCapUsd = self::COST_CAP_USD,
        public int $hintDelayMs = self::HINT_DELAY_MS,
        public bool $enabled = self::ENABLED,
    ) {}

    public function turnsFor(ConversationType $type): int
    {
        return max(1, $this->turns[$type->value] ?? self::TURNS[$type->value]);
    }

    public function minutesFor(ConversationType $type): int
    {
        return max(1, $this->minutes[$type->value] ?? self::MINUTES[$type->value]);
    }

    /** The seconds the talk adds to the day's «≈ N минут» — the stage has no cards to price. */
    public function secondsFor(ConversationType $type): int
    {
        return $this->minutesFor($type) * 60;
    }
}
