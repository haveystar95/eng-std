<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Command;

use App\Modules\Admin\Application\Port\AdminAuditRecorder;
use App\Modules\Learning\Application\Command\SetExerciseModes;
use App\Modules\Learning\Application\Command\SetExerciseModesHandler;
use App\Modules\Learning\Application\Query\GetExerciseModeSettings;
use App\Modules\Learning\Application\Query\GetExerciseModeSettingsHandler;

/**
 * Writes the toggles through Learning and records what changed — like the tier mutation, the write
 * and its audit entry are one operation. A toggle that silently narrows what a user is trained with
 * is exactly the kind of change that needs a trace: the user cannot see it and would report it as
 * "the app stopped giving me listening".
 */
final readonly class ChangeExerciseModesHandler
{
    public function __construct(
        private SetExerciseModesHandler $set,
        private GetExerciseModeSettingsHandler $settings,
        private AdminAuditRecorder $audit,
    ) {}

    public function __invoke(ChangeExerciseModes $command): void
    {
        $before = ($this->settings)(new GetExerciseModeSettings($command->userId));

        ($this->set)(new SetExerciseModes($command->userId, $command->modes));

        $this->audit->record(
            $command->adminId,
            $command->userId === null ? 'learning.modes.global' : 'learning.modes.user',
            // Null target = the product default; the column is for the user a change was aimed at,
            // and padding a literal "global" into a char(26) user id would be a lie in the trail.
            $command->userId?->value,
            [
                'from' => $command->userId === null ? $before->global : $before->override,
                'to' => $command->modes,
            ],
        );
    }
}
