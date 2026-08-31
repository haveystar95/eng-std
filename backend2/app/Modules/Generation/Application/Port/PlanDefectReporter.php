<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

/**
 * THE LAST MEASURE, MADE VISIBLE — and everything else a plan answer got away with.
 *
 * Covers BOTH plan prompts. It was `PlanDayDefectReporter` while only the day had anything to
 * report; the skeleton gained warnings the moment its own numbers stopped being fatal, and a second
 * port would have been a second way to do one thing. `$dayIndex` is null when the answer being
 * reported on is a skeleton, which has no day.
 *
 * Exactly one defect in a plan day is repaired instead of refused: a pronunciation hint that
 * cannot be written in the learner's alphabet is dropped and the day lives. Every other MECHANICAL
 * failure sends the answer back for its one re-generation.
 *
 * v0.3 added a third category between the two — {@see warned()}: things that are off about the
 * SHAPE of an answer (one formula too many, no question from the learner, thirteen abilities where
 * the prompt asked for twelve) and are not worth a second paid call. They are named by
 * {@see \App\Modules\Generation\Domain\Service\PlanDayValidator::warnings()} and
 * {@see \App\Modules\Generation\Domain\Service\PlanOutlineValidator::warnings()}, and the counter
 * names are the Domain's, because the rule and its name belong together.
 *
 * That asymmetry is earned — under v0.1 a stray comma in one hint failed a whole 16-card day and
 * bought a second paid call whose second answer failed the same way (docs/research/
 * plan-sandbox-2026-08-29.md §7.2) — and it is also exactly the kind of decision that goes quiet.
 * A day silently missing five reading hints looks like a fine day until the owner opens a card and
 * cannot read it. So the drop is reported, counted, and expected to be RARE: a counter that climbs
 * is a prompt problem, not a tolerance to widen.
 */
interface PlanDefectReporter
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
     * This is what was wrong with the shape of an answer, whether or not the answer was written.
     *
     * LOGGED ON EVERY ATTEMPT, COUNTED ONLY ON THE ONE THAT WAS ACCEPTED. The two halves answer
     * two different questions and neither answer is the other's. The log answers «what did the
     * model actually write?» — and a day refused for a fatal defect is exactly when that question
     * is hardest to answer, since the answer is thrown away and nothing else records its shape.
     * The counter answers «how often does a day the learner GOT come out weak?», and counting
     * refused attempts in it would make it climb every time the machine correctly said no.
     *
     * @param  int|null  $dayIndex  the day this is about, or null when it is about the skeleton
     * @param  string  $counter  one of the counter names on
     *                           {@see \App\Modules\Generation\Domain\Service\PlanDayValidator}
     *                           (`plan_day_formula_cap`, `plan_day_no_question`,
     *                           `plan_day_no_repair`, `plan_day_filler_mismatch`) or on
     *                           {@see \App\Modules\Generation\Domain\Service\PlanOutlineValidator}
     *                           (`plan_outline_skill_count`, `plan_outline_est_terms`)
     * @param  bool  $counted    true when this attempt was accepted and the answer was kept
     */
    public function warned(
        string $planId,
        ?int $dayIndex,
        string $counter,
        string $detail,
        bool $counted,
    ): void;

    /** How many times this counter has been raised since it was last reset. For the report. */
    public function warnings(string $counter): int;
}
