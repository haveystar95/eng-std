<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Repository\PlanTermStageRepository;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanTermStage;
use Illuminate\Support\Facades\DB;

final class EloquentPlanTermStageRepository implements PlanTermStageRepository
{
    private const TABLE = 'learning_plan_term_stages';

    public function forPlan(PlanId $planId): array
    {
        $out = [];
        foreach (DB::table(self::TABLE)->where('plan_id', $planId->value)->get() as $row) {
            $out[(string) $row->term_id] = new PlanTermStage(
                planId: (string) $row->plan_id,
                termId: (string) $row->term_id,
                saidInRun: (bool) $row->said_in_run,
                saidFast: (bool) $row->said_fast,
                // Postgres отдаёт `date` строкой `Y-m-d`; лестница плана меряет днями в этом же
                // виде, поэтому дата не разворачивается в объект и обратно.
                retrainedOn: $row->retrained_on === null ? null : substr((string) $row->retrained_on, 0, 10),
            );
        }

        return $out;
    }

    public function save(PlanTermStage $stage): void
    {
        // UPSERT по составному ключу, а не «прочитать и решить»: два устройства, догоняющих очередь
        // ответов, попадают сюда одновременно, и проверка перед записью развалилась бы ровно там.
        DB::table(self::TABLE)->upsert(
            [[
                'plan_id' => $stage->planId,
                'term_id' => $stage->termId,
                'said_in_run' => $stage->saidInRun,
                'said_fast' => $stage->saidFast,
                'retrained_on' => $stage->retrainedOn,
                'updated_at' => now(),
                'created_at' => now(),
            ]],
            ['plan_id', 'term_id'],
            ['said_in_run', 'said_fast', 'retrained_on', 'updated_at'],
        );
    }
}
