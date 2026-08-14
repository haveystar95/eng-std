<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Command;

use App\Modules\Admin\Application\Port\AdminAuditRecorder;
use App\Modules\Learning\Application\Command\SetModeAdmission;
use App\Modules\Learning\Application\Command\SetModeAdmissionHandler;
use App\Modules\Learning\Application\Query\GetExerciseModeSettings;
use App\Modules\Learning\Application\Query\GetExerciseModeSettingsHandler;

/**
 * Writes one admission rule through Learning and records what changed — like the toggles and the
 * tier mutation, the write and its audit entry are one operation. Moving a threshold changes what
 * a learner is asked for weeks afterwards, and it is invisible to them: without a trail it would
 * be reported as "the app suddenly wants me to type everything" with nothing to check it against.
 */
final readonly class ChangeModeAdmissionHandler
{
    public function __construct(
        private SetModeAdmissionHandler $set,
        private GetExerciseModeSettingsHandler $settings,
        private AdminAuditRecorder $audit,
    ) {}

    public function __invoke(ChangeModeAdmission $command): void
    {
        $before = $this->ruleFor($command);

        ($this->set)(new SetModeAdmission(
            userId: $command->userId,
            mode: $command->mode,
            minAcquisition: $command->minAcquisition,
            minLearningStep: $command->minLearningStep,
            minReps: $command->minReps,
            optionsPolicy: $command->optionsPolicy,
        ));

        $this->audit->record(
            $command->adminId,
            $command->userId === null ? 'learning.admission.global' : 'learning.admission.user',
            // Null target = the product default; padding a literal "global" into a char(26) user id
            // would be a lie in the trail.
            $command->userId?->value,
            [
                'mode' => $command->mode,
                'from' => $before,
                'to' => $this->ruleFor($command),
            ],
        );
    }

    /** @return array<string, mixed>|null */
    private function ruleFor(ChangeModeAdmission $command): ?array
    {
        $view = ($this->settings)(new GetExerciseModeSettings($command->userId));
        foreach ($view->admission as $rule) {
            if ($rule['mode'] === $command->mode) {
                return $rule;
            }
        }

        return null;
    }
}
