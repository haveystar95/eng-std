<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/** «Перенести дату» / a different number of days. Null leaves that half as it is. */
final readonly class ReschedulePlan
{
    public function __construct(
        public PlanId $planId,
        public UserId $actorId,
        public ?DateTimeImmutable $eventDate,
        public bool $clearEventDate,
        public ?int $daysTotal,
    ) {}
}
