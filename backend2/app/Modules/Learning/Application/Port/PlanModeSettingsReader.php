<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\PlanKnobs;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;

/**
 * The `scope='plan'` half of `learning_mode_settings`: the knobs a level runs on, and which
 * trainers of the plan ladder are open at that level.
 *
 * A separate port from {@see EnabledModesReader} even though it is the same table, because it
 * answers a different question for a different mechanism. The global reader answers «which trainers
 * does this USER have and at which rung of the acquisition ladder» — the plan reader answers «what
 * numbers does a plan at this LEVEL deal on». Merging them would put a `level` argument on every
 * ordinary session read for the sake of a caller that does not have one.
 */
interface PlanModeSettingsReader
{
    /** The six knobs for this level, merged from its rows and defaulted to the shipped table. */
    public function knobsFor(PlanLevel $level): PlanKnobs;

    /**
     * The plan-ladder trainers switched ON at this level. A mode with no row is CLOSED — the same
     * fail-closed rule the admission matrix uses, and for the same reason.
     *
     * @return list<ExerciseMode>
     */
    public function openModesFor(PlanLevel $level): array;
}
