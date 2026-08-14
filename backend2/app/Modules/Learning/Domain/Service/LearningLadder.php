<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\Acquisition;

/**
 * THE function that says which rung of the acquisition ladder a (user, term) pair stands on.
 *
 * One function, pure, no clock, no storage — because the CLIENT mirrors it. A ladder derived one
 * way on the server and another way in Dart is the same disease as two definitions of "mastered":
 * the phone deals a card the server would not have dealt, and nobody notices until the schedule is
 * already wrong. Hence the table-driven test, which is the contract both runtimes are held to.
 *
 * The rungs:
 *
 *   0  intro                 shown, not asked — no grade, no review row (see TermExposure)
 *   1  recognition forward   term → translation, tap an option (graded by IDENTITY, not text)
 *   2  recognition reverse   translation → term, tap an option
 *   3  assembly / choice     word_bank, cloze, scramble, pick_correct, ordinary multiple_choice
 *   4  + typed production    typing, listening
 *   5  + dictation           the whole sentence from hearing it alone
 *
 * Rungs 0–2 live in `learning_step`, not in `reps`, and that is not redundancy. A FAILED
 * recognition step is re-queued as the same step — but it is still a real retrieval, so it is
 * logged, and anything derived from the log alone would count it and push the pair up a rung it
 * has not earned. `learning_step` moves on success and only on success.
 *
 * Rungs 3–5 come from `reps`, which after graduation is exactly "how many times SM-2 has scheduled
 * this pair" — the ladder answers here reach the scheduler, so its own counter is the honest
 * measure and no third column is needed.
 */
final class LearningLadder
{
    public const STEP_INTRO = 0;
    public const STEP_RECOGNITION_FORWARD = 1;
    public const STEP_RECOGNITION_REVERSE = 2;
    public const STEP_ASSEMBLY = 3;
    public const STEP_TYPING = 4;
    public const STEP_DICTATION = 5;

    /**
     * `reps` thresholds for the graduated rungs. Deliberately counted from graduation, not from
     * the first ever answer: the recognition steps never reach the scheduler, so `reps` is still 0
     * the moment a pair graduates, and these numbers mean "successful SRS reviews since".
     */
    public const TYPING_MIN_REPS = 4;
    public const DICTATION_MIN_REPS = 6;

    /** The first `learning_step` a pair holds once it has been introduced. */
    public const FIRST_LADDER_STEP = self::STEP_RECOGNITION_FORWARD;

    /**
     * The rung this pair stands on, or NULL when it is outside the ladder.
     *
     * `null` is returned for a `known` pair — a triage self-assessment awaiting its verification
     * check, which is always typing regardless of any rung ({@see ExerciseSelector}). Callers must
     * treat null as "the matrix does not apply", never as step 0.
     *
     * @param  Acquisition  $acquisition  the ladder dimension (NOT the scheduler's LearningState)
     * @param  int  $reps                 the scheduler's own counter; only read once graduated
     * @param  int  $learningStep         the stored rung while still on the recognition steps
     */
    public static function stepFor(Acquisition $acquisition, int $reps, int $learningStep, bool $isKnown = false): ?int
    {
        if ($isKnown) {
            return null;
        }

        return match ($acquisition) {
            // A pair that has never been shown starts at the intro whatever its counters say. The
            // one way to get here with reps > 0 is a `known` mark being undone, which resets the
            // ladder on purpose: the pair was never actually taught, only claimed.
            Acquisition::New => self::STEP_INTRO,
            // Clamped, so a row written by a newer build (or a hand-edited one) can never point at
            // a rung this build does not deal.
            Acquisition::Learning => max(self::STEP_RECOGNITION_FORWARD, min(self::STEP_RECOGNITION_REVERSE, $learningStep)),
            Acquisition::Graduated => match (true) {
                $reps >= self::DICTATION_MIN_REPS => self::STEP_DICTATION,
                $reps >= self::TYPING_MIN_REPS => self::STEP_TYPING,
                default => self::STEP_ASSEMBLY,
            },
        };
    }

    /** Is this rung one of the two recognition steps, where an answer must not schedule? */
    public static function isRecognitionStep(?int $step): bool
    {
        return $step === self::STEP_RECOGNITION_FORWARD || $step === self::STEP_RECOGNITION_REVERSE;
    }
}
