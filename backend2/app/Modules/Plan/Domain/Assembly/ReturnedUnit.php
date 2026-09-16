<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * A unit that failed twice on an earlier day and comes back: which scene it belongs to, which day it failed on — and
 * the card it failed as the last time (SESSION-1d): its kind, and the filler a phrase card was said with, so a frame
 * comes back as that kind said with another filler.
 */
final readonly class ReturnedUnit
{
    public function __construct(
        public PlanSceneId $sceneId,
        public UnitKind $kind,
        public string $ref,
        public PlanDayId $sourceDayId,
        public ?CardKind $failedAs = null,
        public ?int $failedFiller = null,
    ) {}
}
