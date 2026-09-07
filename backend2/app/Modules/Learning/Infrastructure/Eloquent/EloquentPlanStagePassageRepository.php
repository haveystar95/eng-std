<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Repository\PlanStagePassageRepository;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Support\Facades\DB;

final class EloquentPlanStagePassageRepository implements PlanStagePassageRepository
{
    private const TABLE = 'learning_plan_day_stage_passages';

    public function forPlan(PlanId $planId): array
    {
        $out = [];
        foreach (
            DB::table(self::TABLE)
                ->where('plan_id', $planId->value)
                ->orderBy('day_index')
                ->get(['day_index', 'stage']) as $row
        ) {
            $out[(int) $row->day_index][] = (string) $row->stage;
        }

        return $out;
    }

    public function record(PlanId $planId, array $rows, string $passedOn): void
    {
        if ($rows === []) {
            return;
        }

        $now = now();
        $insert = [];
        foreach ($rows as $row) {
            $insert[] = [
                'id' => Ulid::generate(),
                'plan_id' => $planId->value,
                'day_index' => $row['day_index'],
                'stage' => $row['stage'],
                'passed_on' => $passedOn,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // ИДЕМПОТЕНТНО НА УРОВНЕ БАЗЫ, а не проверкой перед вставкой: пишут отсюда четыре места
        // (конец присеста, запись прогона, сборка следующей посадки, сверка), и две из них могут
        // прийти одновременно. `insertOrIgnore` по уникальному ключу — то, что делает «событие
        // случается один раз» правдой без блокировок.
        DB::table(self::TABLE)->insertOrIgnore($insert);
    }
}
