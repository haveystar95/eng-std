<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\ValueObject\Acquisition;

/**
 * The ladder rung of a STORED progress row, for readers outside this module.
 *
 * It exists because the rung is not a column: it is derived from `acquisition`, `learning_step`,
 * `reps` and whether the pair is `known`, and {@see LearningLadder} is the one place that derivation
 * lives. A back-office report that re-wrote the expression over the same four columns would be a
 * third copy of the ladder next to the server's and the phone's — and the day the thresholds move,
 * the report would quietly keep showing the old rungs, which is worse than showing nothing.
 *
 * Reads the raw column values rather than Learning's enums on purpose: the caller is a reporting
 * projection that selected them straight out of Postgres and must not have to import this module's
 * Domain to ask the question. The Application layer is where that translation belongs.
 */
final class LadderStepResolver
{
    /**
     * @param  string  $acquisition   the `acquisition` column, verbatim
     * @param  int     $reps          the scheduler's counter
     * @param  int     $learningStep  the `learning_step` column
     * @param  bool    $isKnown       state = 'known' (a triage self-assessment, outside the ladder)
     * @return int|null  the rung 0–5, or null when the pair is outside the ladder
     */
    public function forStoredPair(string $acquisition, int $reps, int $learningStep, bool $isKnown): ?int
    {
        // An `acquisition` this build has never heard of can only come from a row a NEWER build
        // wrote, and the forward-compatible read of "further along than anything I know" is the far
        // end of the ladder — the same choice LearningLadder::clampRecognitionStep makes. Refusing
        // the row would drop a word out of the report entirely.
        $acquired = Acquisition::tryFrom($acquisition) ?? Acquisition::Graduated;

        return LearningLadder::stepFor($acquired, $reps, $learningStep, $isKnown);
    }
}
