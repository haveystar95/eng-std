<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Learning\Application\Dto\ExerciseModeSettingsView;
use App\Modules\Learning\Application\Port\EnabledModesReader;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;

/**
 * The read side of the toggles, flattened to strings for callers outside Learning (the admin
 * panel). `available` comes from the enum itself, so a newly built mode appears in the panel the
 * moment it exists in code — switched off, per the release rule in CLAUDE.md.
 */
final readonly class GetExerciseModeSettingsHandler
{
    public function __construct(private EnabledModesReader $modes) {}

    public function __invoke(GetExerciseModeSettings $query): ExerciseModeSettingsView
    {
        $global = $this->wire($this->modes->globalDefault());

        if ($query->userId === null) {
            return new ExerciseModeSettingsView(
                available: $this->available(),
                global: $global,
                override: null,
                effective: $global,
            );
        }

        $override = $this->modes->overrideFor($query->userId);

        return new ExerciseModeSettingsView(
            available: $this->available(),
            global: $global,
            override: $override !== null ? $this->wire($override) : null,
            effective: $this->wire($this->modes->forUser($query->userId)),
        );
    }

    /** @return list<string> */
    private function available(): array
    {
        return array_map(static fn (ExerciseMode $m): string => $m->value, ExerciseMode::cases());
    }

    /** @return list<string> */
    private function wire(EnabledModes $modes): array
    {
        return array_map(static fn (ExerciseMode $m): string => $m->value, $modes->modes);
    }
}
