<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * How far a (user, term) pair has come along the ACQUISITION LADDER — a second dimension,
 * deliberately orthogonal to {@see LearningState}.
 *
 *   LearningState  answers WHEN the pair comes back  → owned by the scheduler (SM-2)
 *   Acquisition    answers WHAT it comes back AS     → owned by the mode admission matrix
 *
 * The two never read each other's fields. The scheduler does not know this enum exists; the
 * admission matrix never looks at `state`. That is what let the ladder be added without
 * re-deriving a single interval: every pair that already existed was backfilled to
 * `graduated` and not one SM-2 column was touched.
 *
 *   new        the pair has never been shown. Its first card is the intro (step 0).
 *   learning   introduced, working through the two recognition steps (`learning_step` 1, 2).
 *              Answers here are real retrievals — they are logged — but they schedule nothing.
 *   graduated  off the ladder's recognition rungs. From here the ordinary SRS machinery runs
 *              and the ladder only widens which trainers are admitted, by `reps`.
 */
enum Acquisition: string
{
    case New = 'new';
    case Learning = 'learning';
    case Graduated = 'graduated';

    /** Is the pair still on the recognition rungs, where a graded answer must not schedule? */
    public function isOnLadder(): bool
    {
        return $this !== self::Graduated;
    }
}
