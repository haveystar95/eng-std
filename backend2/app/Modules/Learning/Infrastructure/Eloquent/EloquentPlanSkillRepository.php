<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Repository\PlanSkillRepository;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanSkillRecord;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Support\Facades\DB;

final class EloquentPlanSkillRepository implements PlanSkillRepository
{
    private const TABLE = 'plan_skills';

    public function replaceAll(PlanId $planId, array $skills): void
    {
        DB::table(self::TABLE)->where('plan_id', $planId->value)->delete();

        $now = now();
        $rows = [];
        foreach ($skills as $skill) {
            $rows[] = [
                'id' => $skill->id === '' ? Ulid::generate() : $skill->id,
                'plan_id' => $planId->value,
                // «s1.2» — what the day's cards name in `skill_ref`. A row id answers «which row»;
                // this answers «which promise», and only the second one may appear in a card.
                'skill_ref' => $skill->skillRef,
                'scene_index' => $skill->sceneIndex,
                'scene_title' => $skill->sceneTitle,
                // jsonb through the query builder: no model, no casts, so the encoding is explicit.
                'role' => $skill->role === null
                    ? null
                    : json_encode($skill->role, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'skill_index' => $skill->skillIndex,
                'outcome' => $skill->outcome,
                'checkpoint' => $skill->checkpoint,
                'est_terms' => $skill->estTerms,
                'topics' => json_encode($skill->topics, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'position' => $skill->position,
                'day_index' => $skill->dayIndex,
                'dropped' => $skill->dropped,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table(self::TABLE)->insert($rows);
        }
    }

    public function listForPlan(PlanId $planId): array
    {
        $out = [];
        foreach (DB::table(self::TABLE)->where('plan_id', $planId->value)->orderBy('position')->get() as $row) {
            $role = is_string($row->role) ? json_decode($row->role, true) : null;
            $topics = is_string($row->topics) ? json_decode($row->topics, true) : [];

            $out[] = new PlanSkillRecord(
                id: (string) $row->id,
                skillRef: (string) ($row->skill_ref ?? ''),
                sceneIndex: (int) $row->scene_index,
                sceneTitle: (string) $row->scene_title,
                /** @phpstan-ignore-next-line the column is written from the same shape one method up */
                role: is_array($role) ? $role : null,
                skillIndex: (int) $row->skill_index,
                outcome: (string) $row->outcome,
                checkpoint: (string) $row->checkpoint,
                estTerms: (int) $row->est_terms,
                topics: is_array($topics) ? array_values(array_map(static fn (mixed $t): string => (string) $t, $topics)) : [],
                position: (int) $row->position,
                dayIndex: $row->day_index === null ? null : (int) $row->day_index,
                dropped: (bool) $row->dropped,
            );
        }

        return $out;
    }
}
