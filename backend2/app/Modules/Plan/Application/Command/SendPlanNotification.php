<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Domain\ValueObject\NotificationKind;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/** Send one letter about one plan to one learner. `localDate` is the learner's date the letter belongs to. */
final readonly class SendPlanNotification
{
    public function __construct(
        public UserId $userId,
        public PlanId $planId,
        public NotificationKind $kind,
        public string $localDate,
        public ?int $dayNumber = null,
        public ?string $eventId = null,
        public ?int $from = null,
        public ?int $to = null,
    ) {}
}
