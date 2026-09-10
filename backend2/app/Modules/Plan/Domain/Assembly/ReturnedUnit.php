<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/** A unit that failed twice on an earlier day and comes back: which scene it belongs to, and which day it failed on. */
final readonly class ReturnedUnit
{
    public function __construct(
        public PlanSceneId $sceneId,
        public UnitKind $kind,
        public string $ref,
        public PlanDayId $sourceDayId,
    ) {}
}
