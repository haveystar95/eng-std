<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\EnabledModesWriter;
use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\Service\ModePassport;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ModeRule;
use App\Modules\Learning\Domain\ValueObject\OptionsPolicy;
use InvalidArgumentException;

/**
 * Same fail-fast contract as {@see SetModeAdmissionHandler} — a bad row is a trainer that silently
 * stops being dealt — plus the position check the admission-only write never needed to make.
 */
final readonly class SetModeSettingsRowHandler
{
    public function __construct(private EnabledModesWriter $writer) {}

    public function __invoke(SetModeSettingsRow $command): void
    {
        $mode = ExerciseMode::tryFrom($command->mode)
            ?? throw new InvalidArgumentException("Unknown exercise mode: {$command->mode}");
        $acquisition = Acquisition::tryFrom($command->minAcquisition)
            ?? throw new InvalidArgumentException("Unknown acquisition: {$command->minAcquisition}");
        $policy = OptionsPolicy::tryFrom($command->optionsPolicy)
            ?? throw new InvalidArgumentException("Unknown options policy: {$command->optionsPolicy}");

        // A threshold below the trainer's constructive minimum is not a stricter setting, it is
        // one that silently does nothing — see ModePassport.
        if (!ModePassport::meetsFloor($mode, $acquisition)) {
            throw new InvalidArgumentException(ModePassport::reasonFor($mode));
        }

        if ($command->position < 0) {
            throw new InvalidArgumentException('position cannot be negative.');
        }
        if ($command->minLearningStep !== null
            && ($command->minLearningStep < 0 || $command->minLearningStep > LearningLadder::STEP_RECOGNITION_REVERSE)) {
            throw new InvalidArgumentException('min_learning_step must be 0, 1 or 2.');
        }
        if ($command->minSuccessfulReviews !== null && $command->minSuccessfulReviews < 0) {
            throw new InvalidArgumentException('min_successful_reviews cannot be negative.');
        }
        // A reviews threshold is counted from GRADUATION, so it says nothing on a rung the pair
        // reaches before graduating. Storing one there would read as a stricter rule than it is.
        if ($command->minSuccessfulReviews !== null && $acquisition !== Acquisition::Graduated) {
            throw new InvalidArgumentException('min_successful_reviews only applies to a graduated threshold.');
        }

        $this->writer->setRow($command->userId, $mode, $command->enabled, $command->position, new ModeRule(
            minAcquisition: $acquisition,
            minLearningStep: $command->minLearningStep,
            minSuccessfulReviews: $command->minSuccessfulReviews,
            optionsPolicy: $policy,
        ));
    }
}
