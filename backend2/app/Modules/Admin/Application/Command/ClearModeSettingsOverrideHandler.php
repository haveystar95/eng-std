<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Command;

use App\Modules\Admin\Application\Port\AdminAuditRecorder;
use App\Modules\Admin\Application\Query\GetModeSettingsMatrix as AdminGetModeSettingsMatrix;
use App\Modules\Admin\Application\Query\GetModeSettingsMatrixHandler as AdminGetModeSettingsMatrixHandler;
use App\Modules\Learning\Application\Command\ClearModeOverride;
use App\Modules\Learning\Application\Command\ClearModeOverrideHandler;

final readonly class ClearModeSettingsOverrideHandler
{
    public function __construct(
        private ClearModeOverrideHandler $clear,
        private AdminGetModeSettingsMatrixHandler $matrix,
        private AdminAuditRecorder $audit,
    ) {}

    public function __invoke(ClearModeSettingsOverride $command): void
    {
        $before = $this->rowFor($command);

        ($this->clear)(new ClearModeOverride($command->userId, $command->mode));

        $this->audit->record(
            $command->adminId,
            'learning.mode_settings.reset',
            $command->userId->value,
            [
                'mode' => $command->mode,
                'from' => $before,
                'to' => $this->rowFor($command),
            ],
        );
    }

    /** @return array<string, mixed>|null */
    private function rowFor(ClearModeSettingsOverride $command): ?array
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
