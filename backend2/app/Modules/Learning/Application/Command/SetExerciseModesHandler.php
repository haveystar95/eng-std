<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\EnabledModesWriter;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use InvalidArgumentException;

/**
 * The write side of the toggles. Takes strings (its callers live outside Learning) and turns them
 * into the Domain VO here — which is also where an unknown mode or an empty set is rejected, so a
 * bad panel request fails at the boundary instead of storing a row that breaks every card.
 */
final readonly class SetExerciseModesHandler
{
    public function __construct(private EnabledModesWriter $writer) {}

    public function __invoke(SetExerciseModes $command): void
    {
        if ($command->modes === null) {
            if ($command->userId === null) {
                // There is nothing above the global default to inherit from.
                throw new InvalidArgumentException('The global default cannot be unset.');
            }

            $this->writer->setOverrideFor($command->userId, null);

            return;
        }

        $modes = new EnabledModes(array_map(
            static fn (string $value): ExerciseMode => ExerciseMode::tryFrom($value)
                ?? throw new InvalidArgumentException("Unknown exercise mode: {$value}"),
            $command->modes,
        ));

        if ($command->userId === null) {
            $this->writer->setGlobalDefault($modes);

            return;
        }

        $this->writer->setOverrideFor($command->userId, $modes);
    }
}
