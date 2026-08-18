<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\EnabledModesWriter;
use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ModeRule;
use App\Modules\Learning\Domain\ValueObject\OptionsPolicy;
use InvalidArgumentException;

/**
 * The write side of the admission matrix. Everything unparseable fails at this boundary rather
 * than being stored: a bad row here is not one broken screen, it is a trainer that silently stops
 * being dealt, which is the kind of bug that is noticed a week later as "the app got easier".
 */
final readonly class SetModeAdmissionHandler
{
    public function __construct(private EnabledModesWriter $writer) {}

    public function __invoke(SetModeAdmission $command): void
    {
        $mode = ExerciseMode::tryFrom($command->mode)
            ?? throw new InvalidArgumentException("Unknown exercise mode: {$command->mode}");
        $acquisition = Acquisition::tryFrom($command->minAcquisition)
            ?? throw new InvalidArgumentException("Unknown acquisition: {$command->minAcquisition}");
        $policy = OptionsPolicy::tryFrom($command->optionsPolicy)
            ?? throw new InvalidArgumentException("Unknown options policy: {$command->optionsPolicy}");

        if ($command->minLearningStep !== null
            && ($command->minLearningStep < 0 || $command->minLearningStep > LearningLadder::STEP_RECOGNITION_REVERSE)) {
            throw new InvalidArgumentException('min_learning_step must be 0, 1 or 2.');
        }
        if ($command->minReps !== null && $command->minReps < 0) {
            throw new InvalidArgumentException('min_reps cannot be negative.');
        }
        // A reps threshold is counted from GRADUATION, so it says nothing on a rung the pair
        // reaches before graduating. Storing one there would read as a stricter rule than it is.
        if ($command->minReps !== null && $acquisition !== Acquisition::Graduated) {
            throw new InvalidArgumentException('min_reps only applies to a graduated threshold.');
        }

        $this->writer->setRule($command->userId, $mode, new ModeRule(
            minAcquisition: $acquisition,
            minLearningStep: $command->minLearningStep,
            minSuccessfulReviews: $command->minReps,
            optionsPolicy: $policy,
        ));
    }
}
