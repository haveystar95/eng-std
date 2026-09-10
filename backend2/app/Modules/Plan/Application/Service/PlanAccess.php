<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Exception\PlanNotFound;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** «This learner's plan, or 404» — the one lookup every command and query starts with. */
final readonly class PlanAccess
{
    public function __construct(private PlanRepository $plans) {}

    public function owned(PlanId $id, UserId $owner): Plan
    {
        $plan = $this->plans->findOwned($id, $owner);
        if ($plan === null || $plan->status() === PlanStatus::Deleted) {
            throw PlanNotFound::withId($id);
        }

        return $plan;
    }

    public function ownedForUpdate(PlanId $id, UserId $owner): Plan
    {
        $plan = $this->plans->findOwnedForUpdate($id, $owner);
        if ($plan === null || $plan->status() === PlanStatus::Deleted) {
            throw PlanNotFound::withId($id);
        }

        return $plan;
    }
}
