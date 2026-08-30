<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The learner moved the minutes, the date, or dropped a day. A1 again — NO model call.
 *
 * `dropDayIndex` removes one outline day's abilities from the plan before the arithmetic runs;
 * everything else re-packs around the hole. That is the difference between this and re-outlining:
 * the model's answer is untouched and still explains what the plan is.
 */
final readonly class ReschedulePlan
{
    public function __construct(
        public PlanId $planId,
        public UserId $actorId,
        public ?int $minutesPerDay = null,
        public ?string $eventDate = null,
        public ?int $dropDayIndex = null,
    ) {}
}
