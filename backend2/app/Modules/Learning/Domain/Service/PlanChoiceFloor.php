<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

/**
 * THE FEWEST OPTIONS A PLAN'S CHOICE CARD MAY BE DEALT — and the one place that number lives.
 *
 * The level says how many options a card WANTS ({@see \App\Modules\Learning\Domain\ValueObject\PlanKnobs::$mcOptions}:
 * three at `zero`/`basic`, four from `conversational` up). Until PLAN-FIX-5 that number was also
 * the floor: a day that could not furnish the fourth same-shape, same-length option dropped the
 * choice card entirely.
 *
 * Measured on the owner's live day 1 (01.09), that was almost the whole day. Fourteen cards, four
 * options wanted, the length band (DECISIONS п. 215) narrow by design — and eleven of the fourteen
 * lost their recognition card. Stage A collapsed from «meet it → recognise it → put it together →
 * say it» to «meet it → say it», which is Д-14/Д-15 all over again, arrived at from a different
 * direction: not a trainer switched off, but a pool that could not fill the fourth slot.
 *
 * ## Three is a card; two is a coin toss
 *
 * So the level's number becomes a PREFERENCE and this is the floor. A card the day can furnish
 * three options for is dealt with three — one right and two wrong is a real retrieval, and it is
 * exactly what `zero` and `basic` learners have always been dealt, so nothing new had to be
 * invented to justify it. Below three the card still falls out whole, and for the reason it always
 * did: with one wrong answer the choice is between two, a coin toss scores 50 %, and the log gets a
 * correct answer nobody gave.
 *
 * ## Both readers ask THIS class
 *
 * The floor has to be one number in two places or the day breaks in a way that is hard to see:
 *
 *   {@see \App\Modules\Learning\Application\Service\PlanStandings} decides whether the checklist
 *   OWES a choice card — a step that is owed and cannot be dealt is a stage that never closes;
 *   {@see \App\Modules\Learning\Application\Service\StudyCardAssembler} decides whether it can
 *   actually build one.
 *
 * The first must never owe what the second cannot build. Two copies of «three» would satisfy that
 * until one of them moved.
 *
 * The number is configuration (`config/learning.php → plan.mc_min_options`), like the length band
 * and for the same reason: it is a product judgement about how hard a card should be, and the first
 * time it is wrong it should move without a deploy of the domain.
 */
final readonly class PlanChoiceFloor
{
    /** One right answer and two wrong ones. Below this a choice card is not a question. */
    public const DEFAULT_MIN_OPTIONS = 3;

    /**
     * Never fewer than this, whatever the configuration says — the same «one option is not a
     * question» rule the ordinary session keeps at `StudyCardAssembler::MIN_OPTIONS`.
     */
    private const ABSOLUTE_MIN = 2;

    public function __construct(private int $minOptions = self::DEFAULT_MIN_OPTIONS) {}

    /**
     * The floor for a card whose level would prefer `$preferred` options.
     *
     * Capped by the preference, because a level that asks for three has no floor of four to fall
     * to, and a floor above the ceiling would refuse every card in the level it was meant to help.
     */
    public function forPreferred(int $preferred): int
    {
        return max(self::ABSOLUTE_MIN, min($preferred, $this->minOptions));
    }
}
