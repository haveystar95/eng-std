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
 * Rungs 0–2 live in `learning_step`, not in a counter, and that is not redundancy. A FAILED
 * recognition step is re-queued as the same step — but it is still a real retrieval, so it is
 * logged, and anything derived from the log alone would count it and push the pair up a rung it
 * has not earned. `learning_step` moves on success and only on success.
 *
 * Rungs 3–5 come from `successful_reviews`, and for exactly the same reason. They used to come
 * from the scheduler's `reps`, which counts how many times SM-2 has been CALLED — every branch of
 * it, `again` included. So four misses and two hits read as six, and because an `again` in learning
 * schedules the pair back immediately, a word nobody could remember rode its own failures up to
 * dictation. `reps` is an honest measure of one thing (it drives the mode rotation in
 * {@see ExerciseSelector}, and still does) and a dishonest measure of another; the ladder needed
 * the other, so it got its own column.
 *
 * The counter grows on a non-practice review of a GRADUATED pair that was answered correctly —
 * `hard` included, because «recalled it with a stumble» is a recall. On `again` it does not grow
 * and does not RESET: a rung, once earned, is not lost. That is a deliberate simplification next to
 * FSRS, which would model the decay; here the admission matrix only decides which trainers a word
 * may appear in, and demoting a word out of typing because of one bad evening buys nothing.
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
     * Thresholds for the graduated rungs, counted in SUCCESSFUL reviews since graduation.
     *
     * Counted from graduation rather than from the first ever answer, and that needs no arithmetic
     * to arrange: the recognition rungs are not graduated, so nothing on them increments the
     * counter, and it is still 0 the moment a pair graduates.
     */
    public const TYPING_MIN_SUCCESSES = 4;
    public const DICTATION_MIN_SUCCESSES = 6;

    /** The first `learning_step` a pair holds once it has been introduced. */
    public const FIRST_LADDER_STEP = self::STEP_RECOGNITION_FORWARD;

    /**
     * The rung a word OUTSIDE the pool is dealt at in a collection's free practice.
     *
     * Such a word has no rung of its own — nobody has decided to study it, so nothing about it has
     * been earned — and it must not be dealt the trainers a rung is for. It is dealt as a first
     * meeting is: the easy half of the matrix (choice and assembly), never typed production or
     * dictation, which ask the learner to reproduce a word they may be seeing for the first time.
     *
     * The assembly rung and not a recognition one, because recognition admits multiple_choice and
     * nothing else, and «зашёл в кафе, открыл тему» deserves the assembly trainers too. Mirrored on
     * the client as `LearningLadder.stepUnenrolledPractice`.
     */
    public const STEP_UNENROLLED_PRACTICE = self::STEP_ASSEMBLY;

    /**
     * The rung this pair stands on, or NULL when it is outside the ladder.
     *
     * `null` is returned for a `known` pair — a triage self-assessment awaiting its verification
     * check, which is always typing regardless of any rung ({@see ExerciseSelector}). Callers must
     * treat null as "the matrix does not apply", never as step 0.
     *
     * @param  Acquisition  $acquisition       the ladder dimension (NOT the scheduler's LearningState)
     * @param  int  $successfulReviews          correct non-practice reviews since graduation; only
     *                                          read once graduated, and NOT the scheduler's `reps`
     * @param  int  $learningStep               the stored rung while still on the recognition steps
     */
    public static function stepFor(Acquisition $acquisition, int $successfulReviews, int $learningStep, bool $isKnown = false): ?int
    {
        if ($isKnown) {
            return null;
        }

        return match ($acquisition) {
            // A pair that has never been shown starts at the intro whatever its counters say. The
            // one way to get here with a counter above 0 is a `known` mark being undone, which
            // resets the ladder on purpose: the pair was never actually taught, only claimed.
            Acquisition::New => self::STEP_INTRO,
            Acquisition::Learning => self::clampRecognitionStep($learningStep),
            Acquisition::Graduated => match (true) {
                $successfulReviews >= self::DICTATION_MIN_SUCCESSES => self::STEP_DICTATION,
                $successfulReviews >= self::TYPING_MIN_SUCCESSES => self::STEP_TYPING,
                default => self::STEP_ASSEMBLY,
            },
        };
    }

    /**
     * A stored `learning_step` read as one of the two recognition rungs.
     *
     * The column is CHECKed to 0…2, so a value outside the recognition range can only reach here
     * from a row a NEWER build wrote (or a hand-edited one) — and the forward-compatible answer is
     * the highest rung THIS build knows how to deal, never null and never a throw: refusing the row
     * would drop the pair out of the session entirely, and reading 7 literally would ask the layout
     * for a card that does not exist.
     *
     * A named function rather than the expression inline, because the session assembler needs the
     * same clamp and needs it non-nullable — two copies of a clamp is how the two runtimes started
     * disagreeing in the first place.
     */
    public static function clampRecognitionStep(int $learningStep): int
    {
        return max(self::STEP_RECOGNITION_FORWARD, min(self::STEP_RECOGNITION_REVERSE, $learningStep));
    }

    /** Is this rung one of the two recognition steps, where an answer must not schedule? */
    public static function isRecognitionStep(?int $step): bool
    {
        return $step === self::STEP_RECOGNITION_FORWARD || $step === self::STEP_RECOGNITION_REVERSE;
    }
}
