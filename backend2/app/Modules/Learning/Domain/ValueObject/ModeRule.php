<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

use App\Modules\Learning\Domain\Service\LearningLadder;

/**
 * One row of the admission matrix: the EARLIEST point on the acquisition ladder at which a trainer
 * may be dealt, plus where its options come from.
 *
 * The threshold is expressed in the ladder's own coordinates — an acquisition, optionally a
 * learning step, optionally a number of post-graduation SUCCESSES — rather than as a bare rung
 * number. That is deliberate: it is how a person would say it («typing, once the word has
 * graduated and been recalled four times»), it is what the admin screen edits, and it means the
 * threshold and a pair's position are turned into a rung by the SAME pure function, so the two
 * cannot drift apart.
 *
 * The stored column and the wire key are still named `min_reps`. That name is historical — it was
 * written when the ladder read the scheduler's `reps` — and it is kept because the admin console
 * that edits it lives in another repository; the number it holds is a count of SUCCESSFUL reviews
 * ({@see LearningLadder}), and the rename stops at this boundary on purpose. Application DTOs
 * carrying the wire value keep the wire's name; Domain uses the honest one.
 */
final readonly class ModeRule
{
    public function __construct(
        public Acquisition $minAcquisition,
        public ?int $minLearningStep = null,
        public ?int $minSuccessfulReviews = null,
        public OptionsPolicy $optionsPolicy = OptionsPolicy::Standard,
    ) {}

    /**
     * The lowest rung this rule admits — the threshold read through {@see LearningLadder} exactly
     * as a real pair's position is.
     */
    public function minStep(): int
    {
        return LearningLadder::stepFor(
            $this->minAcquisition,
            $this->minSuccessfulReviews ?? 0,
            $this->minLearningStep ?? LearningLadder::FIRST_LADDER_STEP,
        ) ?? LearningLadder::STEP_ASSEMBLY;
    }
}
