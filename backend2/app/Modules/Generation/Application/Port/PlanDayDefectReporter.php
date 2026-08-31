<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

/**
 * THE LAST MEASURE, MADE VISIBLE — and, since v0.3, everything else the day got away with.
 *
 * Exactly one defect in a plan day is repaired instead of refused: a pronunciation hint that
 * cannot be written in the learner's alphabet is dropped and the day lives. Every other MECHANICAL
 * failure sends the day back for its one re-generation.
 *
 * v0.3 added a third category between the two — {@see warned()}: things that are wrong about the
 * SHAPE of the conversation (one formula too many, no question from the learner, no repair move)
 * and are not worth a second paid call. They are named by
 * {@see \App\Modules\Generation\Domain\Service\PlanDayValidator::warnings()}, and the counter names
 * are the Domain's, because the rule and its name belong together.
 *
 * That asymmetry is earned — under v0.1 a stray comma in one hint failed a whole 16-card day and
 * bought a second paid call whose second answer failed the same way (docs/research/
 * plan-sandbox-2026-08-29.md §7.2) — and it is also exactly the kind of decision that goes quiet.
 * A day silently missing five reading hints looks like a fine day until the owner opens a card and
 * cannot read it. So the drop is reported, counted, and expected to be RARE: a counter that climbs
 * is a prompt problem, not a tolerance to widen.
 */
interface PlanDayDefectReporter
{
    /** The name the counter is kept under, so the reader and the writer cannot drift apart. */
    public const TRANSLITERATION_DROPPED = 'plan_day_transliteration_dropped';

    /**
     * A card lost its reading, and the day was written anyway.
     *
     * @param  string  $reason  `unusable` — the model wrote something that is not the learner's
     *                          alphabet; `missing` — it wrote nothing where the two scripts differ
     *                          and a hint is mandatory
     */
    public function transliterationDropped(
        string $planId,
        int $dayIndex,
        string $text,
        ?string $raw,
        string $reason,
    ): void;

    /** How many hints have been dropped since the counter was last reset. For the report. */
    public function droppedTransliterations(): int;

    /**
     * The day was written, and this is what was wrong with it anyway.
     *
     * @param  string  $counter  one of the counter names on
     *                           {@see \App\Modules\Generation\Domain\Service\PlanDayValidator}
     *                           (`plan_day_formula_cap`, `plan_day_no_question`, `plan_day_no_repair`)
     */
    public function warned(string $planId, int $dayIndex, string $counter, string $detail): void;

    /** How many times this counter has been raised since it was last reset. For the report. */
    public function warnings(string $counter): int;
}
