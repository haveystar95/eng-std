<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\ValueObject\PlanDayId;
use App\Modules\Learning\Domain\ValueObject\PlanId;

final readonly class EloquentPlanDayRepository implements PlanDayRepository
{
    public function __construct(private PlanMapper $mapper) {}

    public function listForPlan(PlanId $planId): array
    {
        /** @var list<PlanDay> $days */
        $days = PlanDayModel::query()
            ->where('plan_id', $planId->value)
            ->orderBy('day_index')
            ->get()
            ->map(fn (PlanDayModel $row): PlanDay => $this->mapper->toDay($row))
            ->values()
            ->all();

        return $days;
    }

    public function findByIndex(PlanId $planId, int $dayIndex): ?PlanDay
    {
        $row = PlanDayModel::query()
            ->where('plan_id', $planId->value)
            ->where('day_index', $dayIndex)
            ->first();

        return $row instanceof PlanDayModel ? $this->mapper->toDay($row) : null;
    }

    public function findByIndexForUpdate(PlanId $planId, int $dayIndex): ?PlanDay
    {
        $row = PlanDayModel::query()
            ->where('plan_id', $planId->value)
            ->where('day_index', $dayIndex)
            ->lockForUpdate()
            ->first();

        return $row instanceof PlanDayModel ? $this->mapper->toDay($row) : null;
    }

    public function findById(PlanDayId $id): ?PlanDay
    {
        $row = PlanDayModel::query()->find($id->value);

        return $row instanceof PlanDayModel ? $this->mapper->toDay($row) : null;
    }

    public function save(PlanDay $day): void
    {
        PlanDayModel::query()->updateOrInsert(
            ['id' => $day->id()->value],
            [...$this->mapper->dayColumns($day), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function replaceAll(PlanId $planId, array $days): void
    {
        // Only ever reached on a DRAFT, where no day owns a collection — the entity refuses an
        // outline or a reschedule in any other state, so there is nothing here to orphan.
        PlanDayModel::query()->where('plan_id', $planId->value)->delete();

        $now = now();
        $rows = [];
        foreach ($days as $day) {
            $rows[] = [
                'id' => $day->id()->value,
                ...$this->mapper->dayColumns($day),
                // jsonb columns go through the query builder here (a bulk insert bypasses the
                // model's casts), so they are encoded explicitly rather than handed an array.
                'skills' => json_encode($day->skills(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'role_brief' => $day->roleBrief() === null
                    ? null
                    : json_encode($day->roleBrief(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            PlanDayModel::query()->insert($rows);
        }
    }
}
