<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\PlanModeSettingsReader;
use App\Modules\Learning\Domain\Service\PlanStageLadder;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\PlanKnobs;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * The `scope='plan'` rows, read once per request.
 *
 * The knobs are stored per mode ({@see \App\Modules\Learning\Infrastructure\Migration\...add_plan_scope_to_mode_settings})
 * and a LEVEL's knob set is the merge of its rows. The merge is done here rather than in Domain
 * because it is a fact about how the rows were laid out, not about what a knob means.
 *
 * A level with no rows at all falls back to {@see PlanKnobs::shipped()} and says so loudly — the
 * rows are seeded by migration, so an empty level means somebody emptied them, and a plan that
 * silently ran on defaults would be a plan whose configuration screen lies.
 */
final class EloquentPlanModeSettingsReader implements PlanModeSettingsReader
{
    private const TABLE = 'learning_mode_settings';

    /** @var array<string, list<stdClass>>|null rows by level */
    private ?array $rows = null;

    public function knobsFor(PlanLevel $level): PlanKnobs
    {
        $rows = $this->forLevel($level);
        if ($rows === []) {
            Log::error('learning_mode_settings has no plan rows for a level — falling back to the shipped knobs', [
                'level' => $level->value,
            ]);

            return PlanKnobs::shipped($level);
        }

        $merged = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string) ($row->knobs ?? ''), true);
            if (is_array($decoded)) {
                $merged = [...$merged, ...$decoded];
            }
        }

        return PlanKnobs::fromArray($level, $merged);
    }

    public function openModesFor(PlanLevel $level): array
    {
        $open = [];
        foreach ($this->forLevel($level) as $row) {
            if (! (bool) $row->enabled) {
                continue;
            }
            $mode = ExerciseMode::tryFrom((string) $row->mode);
            if ($mode !== null) {
                $open[] = $mode;
            }
        }

        if ($open === []) {
            Log::error('learning_mode_settings has no open plan trainers for a level — falling back to the whole ladder', [
                'level' => $level->value,
            ]);

            return PlanStageLadder::allModes();
        }

        return $open;
    }

    /** @return list<stdClass> */
    private function forLevel(PlanLevel $level): array
    {
        if ($this->rows === null) {
            $byLevel = [];
            $rows = DB::table(self::TABLE)->where('scope', 'plan')->orderBy('position')->orderBy('mode')->get();
            foreach ($rows as $row) {
                $byLevel[(string) $row->level][] = $row;
            }
            $this->rows = $byLevel;
        }

        return $this->rows[$level->value] ?? [];
    }
}
