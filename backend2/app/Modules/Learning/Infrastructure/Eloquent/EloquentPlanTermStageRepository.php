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
                choiceStreak: (int) $row->choice_streak,
                saidInRun: (bool) $row->said_in_run,
                saidFast: (bool) $row->said_fast,
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
                'choice_streak' => $stage->choiceStreak,
                'said_in_run' => $stage->saidInRun,
                'said_fast' => $stage->saidFast,
                'updated_at' => now(),
                'created_at' => now(),
            ]],
            ['plan_id', 'term_id'],
            ['choice_streak', 'said_in_run', 'said_fast', 'updated_at'],
        );
    }
}
