<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Repository\PlanSceneRunRepository;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanSceneRun;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Support\Facades\DB;

final class EloquentPlanSceneRunRepository implements PlanSceneRunRepository
{
    private const TABLE = 'learning_plan_scene_runs';

    public function add(PlanSceneRun $run): void
    {
        DB::table(self::TABLE)->insert([
            'id' => Ulid::generate(),
            'plan_id' => $run->planId,
            'scene_index' => $run->sceneIndex,
            'day_index' => $run->dayIndex,
            'total' => $run->total,
            'said' => $run->said,
            'said_fast' => $run->saidFast,
            'skipped' => $run->skipped,
            'rescued' => $run->rescued,
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function forPlan(PlanId $planId): array
    {
        $out = [];
        foreach (
            DB::table(self::TABLE)
                ->where('plan_id', $planId->value)
                ->orderBy('completed_at')
                ->orderBy('id')
                ->get() as $row
        ) {
            $out[] = new PlanSceneRun(
                planId: (string) $row->plan_id,
                sceneIndex: (int) $row->scene_index,
                dayIndex: (int) $row->day_index,
                total: (int) $row->total,
                said: (int) $row->said,
                saidFast: (int) $row->said_fast,
                skipped: (int) $row->skipped,
                rescued: (int) $row->rescued,
            );
        }

        return $out;
    }
}
