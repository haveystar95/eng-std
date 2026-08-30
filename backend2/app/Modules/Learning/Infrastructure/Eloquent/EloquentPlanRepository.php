<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\ValueObject\UserId;

final readonly class EloquentPlanRepository implements PlanRepository
{
    public function __construct(private PlanMapper $mapper) {}

    public function findById(PlanId $id): ?LearningPlan
    {
        $row = PlanModel::query()->find($id->value);

        return $row instanceof PlanModel ? $this->mapper->toPlan($row) : null;
    }

    public function findForUpdate(PlanId $id): ?LearningPlan
    {
        $row = PlanModel::query()->lockForUpdate()->find($id->value);

        return $row instanceof PlanModel ? $this->mapper->toPlan($row) : null;
    }

    public function listFor(UserId $userId, int $limit): array
    {
        $rows = PlanModel::query()
            ->where('user_id', $userId->value)
            ->where('status', '!=', PlanStatus::Draft->value)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return array_values($rows->map($this->mapper->toPlan(...))->all());
    }

    public function findActiveFor(UserId $userId): ?LearningPlan
    {
        $row = PlanModel::query()
            ->where('user_id', $userId->value)
            ->whereIn('status', [PlanStatus::Active->value, PlanStatus::Paused->value])
            // A paused plan and an active one can coexist (only `active` is unique), and the
            // active one is the one the learner is doing. Newest first inside a status, so a
            // second paused plan does not shadow the one they put down yesterday.
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->first();

        return $row instanceof PlanModel ? $this->mapper->toPlan($row) : null;
    }

    public function holdingPlanIdsFor(UserId $userId): array
    {
        /** @var list<string> $ids */
        $ids = PlanModel::query()
            ->where('user_id', $userId->value)
            ->whereIn('status', [PlanStatus::Active->value, PlanStatus::Paused->value])
            ->pluck('id')
            ->all();

        return $ids;
    }

    public function save(LearningPlan $plan): void
    {
        PlanModel::query()->updateOrInsert(
            ['id' => $plan->id()->value],
            [...$this->mapper->planColumns($plan), 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
