<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Application\Port\PlanListReader;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** The aggregate in three queries (plan, scenes, days); written back as upserts and deletions of what left. */
final class EloquentPlanRepository implements PlanListReader, PlanRepository, SceneLocator
{
    public function __construct(private readonly PlanMapper $mapper) {}

    public function findById(PlanId $id): ?Plan
    {
        $row = $this->query()->find($id->value);

        return $row === null ? null : $this->mapper->toDomain($row);
    }

    public function findOwned(PlanId $id, UserId $owner): ?Plan
    {
        $row = $this->query()->whereKey($id->value)->where('user_id', $owner->value)->first();

        return $row === null ? null : $this->mapper->toDomain($row);
    }

    public function findOwnedForUpdate(PlanId $id, UserId $owner): ?Plan
    {
        $row = $this->query()->whereKey($id->value)->where('user_id', $owner->value)->lockForUpdate()->first();

        return $row === null ? null : $this->mapper->toDomain($row);
    }

    public function findByIdForUpdate(PlanId $id): ?Plan
    {
        $row = $this->query()->whereKey($id->value)->lockForUpdate()->first();

        return $row === null ? null : $this->mapper->toDomain($row);
    }

    public function findLiveFor(UserId $owner): ?Plan
    {
        $row = $this->query()
            ->where('user_id', $owner->value)
            ->whereIn('status', [PlanStatus::Active->value, PlanStatus::Overdue->value])
            ->orderByDesc('created_at')
            ->first();

        return $row === null ? null : $this->mapper->toDomain($row);
    }

    public function allFor(UserId $owner): array
    {
        $rows = $this->query()
            ->where('user_id', $owner->value)
            ->where('status', '!=', PlanStatus::Deleted->value)
            ->orderByRaw("CASE WHEN status IN ('active','overdue') THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get();

        return array_values($rows->map(fn (PlanModel $row): Plan => $this->mapper->toDomain($row))->all());
    }

    public function planIdOf(PlanSceneId $sceneId): ?PlanId
    {
        $planId = PlanSceneModel::query()->whereKey($sceneId->value)->value('plan_id');

        return is_string($planId) ? PlanId::fromString($planId) : null;
    }

    public function save(Plan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            $now = now();
            PlanModel::query()->updateOrCreate(['id' => $plan->id()->value], $this->mapper->planColumns($plan));

            $sceneIds = array_map(static fn (PlanScene $s): string => $s->id()->value, $plan->scenes());
            $removed = PlanSceneModel::query()->where('plan_id', $plan->id()->value);
            if ($sceneIds !== []) {
                $removed->whereNotIn('id', $sceneIds);
            }
            $removed->delete();
            foreach ($plan->scenes() as $scene) {
                PlanSceneModel::query()->updateOrCreate(['id' => $scene->id()->value], $this->mapper->sceneColumns($scene, $plan->userId()));
            }

            $dayIds = array_map(static fn (PlanDay $d): string => $d->id()->value, $plan->days());
            $gone = PlanDayModel::query()->where('plan_id', $plan->id()->value);
            if ($dayIds !== []) {
                $gone->whereNotIn('id', $dayIds);
            }
            $gone->delete();
            foreach ($plan->days() as $day) {
                PlanDayModel::query()->updateOrCreate(['id' => $day->id()->value], $this->mapper->dayColumns($day, $plan->userId()));
            }
            unset($now);
        });
    }

    /** @return Builder<PlanModel> */
    private function query(): Builder
    {
        return PlanModel::query()->with(['scenes', 'days']);
    }
}
