<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Command;

use App\Modules\Admin\Application\Port\AdminAuditRecorder;
use App\Modules\Admin\Application\Query\GetModeSettingsMatrix as AdminGetModeSettingsMatrix;
use App\Modules\Admin\Application\Query\GetModeSettingsMatrixHandler as AdminGetModeSettingsMatrixHandler;
use App\Modules\Learning\Application\Command\SetModeSettingsRow;
use App\Modules\Learning\Application\Command\SetModeSettingsRowHandler;

/**
 * Writes one matrix row through Learning and records what changed — like the toggles and the
 * admission-only write, the write and its audit entry are one operation. A row here decides what a
 * learner is dealt for weeks afterwards and is invisible to them, so it leaves a trail like every
 * other mutation the panel makes.
 */
final readonly class ChangeModeSettingsRowHandler
{
    public function __construct(
        private SetModeSettingsRowHandler $set,
        private AdminGetModeSettingsMatrixHandler $matrix,
        private AdminAuditRecorder $audit,
    ) {}

    public function __invoke(ChangeModeSettingsRow $command): void
    {
        $before = $this->rowFor($command);

        ($this->set)(new SetModeSettingsRow(
            userId: $command->userId,
            mode: $command->mode,
            enabled: $command->enabled,
            position: $command->position,
            minAcquisition: $command->minAcquisition,
            minLearningStep: $command->minLearningStep,
            minSuccessfulReviews: $command->minSuccessfulReviews,
            optionsPolicy: $command->optionsPolicy,
        ));

        $this->audit->record(
            $command->adminId,
            $command->userId === null ? 'learning.mode_settings.global' : 'learning.mode_settings.user',
            $command->userId?->value,
            [
                'mode' => $command->mode,
                'from' => $before,
                'to' => $this->rowFor($command),
            ],
        );
    }

    /** @return array<string, mixed>|null */
    private function rowFor(ChangeModeSettingsRow $command): ?array
    {
        $rows = ($this->matrix)(new AdminGetModeSettingsMatrix($command->userId));
        foreach ($rows as $row) {
            if ($row['mode'] === $command->mode) {
                return $row;
            }
        }

        return null;
    }
}
