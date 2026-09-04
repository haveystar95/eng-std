<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Планы в памяти — для юнит-тестов, которым план не нужен, но которые собирают руками обработчик,
 * умеющий про план спрашивать.
 *
 * Пустой по умолчанию, и это точное описание аккаунта без плана: `findActiveFor()` возвращает null,
 * а всё, что зависит от плана, молча ничего не делает — ровно так же, как в бою.
 */
final class InMemoryPlanRepository implements PlanRepository
{
    /** @var list<LearningPlan> */
    public array $plans = [];

    public function findById(PlanId $id): ?LearningPlan
    {
        foreach ($this->plans as $plan) {
            if ($plan->id()->equals($id)) {
                return $plan;
            }
        }

        return null;
    }

    public function findForUpdate(PlanId $id): ?LearningPlan
    {
        return $this->findById($id);
    }

    public function findActiveFor(UserId $userId): ?LearningPlan
    {
        foreach ($this->plans as $plan) {
            if ($plan->userId()->equals($userId) && $plan->status()->holdsTerms()) {
                return $plan;
            }
        }

        return null;
    }

    public function holdingPlanIdsFor(UserId $userId): array
    {
        $out = [];
        foreach ($this->plans as $plan) {
            if ($plan->userId()->equals($userId) && $plan->status()->holdsTerms()) {
                $out[] = $plan->id()->value;
            }
        }

        return $out;
    }

    public function listFor(UserId $userId, int $limit): array
    {
        $out = [];
        foreach ($this->plans as $plan) {
            if ($plan->userId()->equals($userId) && count($out) < $limit) {
                $out[] = $plan;
            }
        }

        return $out;
    }

    public function save(LearningPlan $plan): void
    {
        foreach ($this->plans as $index => $existing) {
            if ($existing->id()->equals($plan->id())) {
                $this->plans[$index] = $plan;

                return;
            }
        }

        $this->plans[] = $plan;
    }
}
