<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

use App\Modules\Learning\Domain\Service\LearningLadder;

/**
 * One row of the admission matrix: the EARLIEST point on the acquisition ladder at which a trainer
 * may be dealt, plus where its options come from.
 *
 * The threshold is expressed in the ladder's own coordinates — an acquisition, optionally a
 * learning step, optionally a number of post-graduation reviews — rather than as a bare rung
 * number. That is deliberate: it is how a person would say it («typing, once the word has
 * graduated and been reviewed four times»), it is what the admin screen will edit, and it means
 * the threshold and a pair's position are turned into a rung by the SAME pure function, so the two
 * cannot drift apart.
 */
final readonly class ModeRule
{
    public function __construct(
        public Acquisition $minAcquisition,
        public ?int $minLearningStep = null,
        public ?int $minReps = null,
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
            $this->minReps ?? 0,
            $this->minLearningStep ?? LearningLadder::FIRST_LADDER_STEP,
        ) ?? LearningLadder::STEP_ASSEMBLY;
    }
}
