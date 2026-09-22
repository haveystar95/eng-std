<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Service\DayPace;

/**
 * THE PRICE LIST A PLAN'S DAYS ARE RECKONED BY (наряд FIX-3 §2): the plan's own snapshot of `plan.pace` — taken when the
 * plan is made and again by `plan:repace` — over the config's current list, so a kind the snapshot does not name (one
 * added after it was taken) is priced as the config prices it rather than as nothing. A plan with no snapshot (none is
 * left after the migration that gave the plans made before it their old list) reads the config's list whole. Every «≈ N
 * мин» of a day and the ceiling of its «Фразы» ask here, and nowhere else.
 */
final readonly class PlanPaces
{
    public function __construct(private PlanConfig $config) {}

    public function for(Plan $plan): DayPace
    {
        return new DayPace([...$this->config->pace, ...($plan->pace() ?? [])]);
    }

    /** @return array<string, int> the list a plan made now is given — the config's */
    public function current(): array
    {
        return $this->config->pace;
    }
}
