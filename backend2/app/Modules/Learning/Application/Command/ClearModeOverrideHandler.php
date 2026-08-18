<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\EnabledModesWriter;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use InvalidArgumentException;

final readonly class ClearModeOverrideHandler
{
    public function __construct(private EnabledModesWriter $writer) {}

    public function __invoke(ClearModeOverride $command): void
    {
        $mode = ExerciseMode::tryFrom($command->mode)
            ?? throw new InvalidArgumentException("Unknown exercise mode: {$command->mode}");

        $this->writer->clearOverride($command->userId, $mode);
    }
}
